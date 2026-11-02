<?php

namespace App\Support;

use DateTimeImmutable;
use JsonException;
use RuntimeException;

/**
 * Reads and validates the province/ward snapshot in database/data (wire contract v1,
 * docs/collaboration/data-source-proposal.md sections 3-4). Validation is structural:
 * it proves the files are complete and untouched, not that every name is legally correct.
 */
final class AdministrativeDataset
{
    private const MANIFEST_FIELDS = [
        'schema_version', 'dataset_version', 'hcm_province_code', 'effective_as_of', 'retrieved_at',
        'source_urls', 'legal_basis_urls', 'license', 'license_url', 'attribution',
        'province_count', 'ward_count', 'record_count', 'file', 'sha256',
    ];

    private const RECORD_FIELDS = ['code', 'name', 'level', 'parent_code', 'dataset_version', 'valid_from', 'valid_to', 'is_active'];

    private const VERSION_PATTERN = '/\A[A-Za-z0-9][A-Za-z0-9._-]{0,49}\z/';

    /**
     * @param  array<string, mixed>  $manifest
     * @param  list<array{code: string, name: string, level: string, parent_code: ?string, dataset_version: string, valid_from: string, valid_to: null, is_active: true}>  $records
     */
    private function __construct(public readonly array $manifest, public readonly array $records) {}

    public static function fromDirectory(?string $directory = null): self
    {
        $directory ??= database_path('data');
        $manifest = self::decode(self::read($directory.'/manifest.json'), 'manifest.json');
        if (! is_array($manifest) || array_is_list($manifest)) {
            throw new RuntimeException('manifest.json must be a JSON object.');
        }
        self::validateManifest($manifest);

        $bytes = self::read($directory.'/'.$manifest['file']);
        if (! hash_equals($manifest['sha256'], hash('sha256', $bytes))) {
            throw new RuntimeException('administrative_units.json does not match the manifest checksum.');
        }
        $records = self::decode($bytes, 'administrative_units.json');
        if (! is_array($records) || ! array_is_list($records) || $records === []) {
            throw new RuntimeException('administrative_units.json must be a non-empty JSON array.');
        }

        return new self($manifest, self::validateRecords($records, $manifest));
    }

    public function version(): string
    {
        return $this->manifest['dataset_version'];
    }

    private static function read(string $path): string
    {
        $bytes = is_file($path) ? file_get_contents($path) : false;
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Cannot read '.basename($path).'.');
        }

        return $bytes;
    }

    private static function decode(string $bytes, string $name): mixed
    {
        if (str_starts_with($bytes, "\xEF\xBB\xBF") || ! mb_check_encoding($bytes, 'UTF-8')) {
            throw new RuntimeException("{$name} must be UTF-8 without BOM.");
        }
        try {
            return json_decode($bytes, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException("{$name} is not valid JSON.");
        }
    }

    /** @param array<string, mixed> $manifest */
    private static function validateManifest(array $manifest): void
    {
        $keys = array_keys($manifest);
        $extra = array_diff($keys, [...self::MANIFEST_FIELDS, 'source_sha256']);
        $missing = array_diff(self::MANIFEST_FIELDS, $keys);
        if ($extra !== [] || $missing !== []) {
            throw new RuntimeException('manifest.json has missing or unknown fields: '.implode(', ', [...$missing, ...$extra]).'.');
        }

        $httpsList = static fn (mixed $list): bool => is_array($list) && array_is_list($list) && $list !== [] && array_filter($list, fn (mixed $url): bool => ! self::isHttpsUrl($url)) === [];
        $text = static fn (mixed $value): bool => is_string($value) && trim($value) !== '' && mb_check_encoding($value, 'UTF-8');
        $count = static fn (mixed $value, int $min): bool => is_int($value) && $value >= $min;

        $valid = $manifest['schema_version'] === 1
            && is_string($manifest['dataset_version']) && preg_match(self::VERSION_PATTERN, $manifest['dataset_version'])
            && is_string($manifest['hcm_province_code']) && preg_match('/\A[0-9]{2}\z/', $manifest['hcm_province_code'])
            && self::isDate($manifest['effective_as_of']) && self::isDate($manifest['retrieved_at'])
            && $manifest['retrieved_at'] >= $manifest['effective_as_of']
            && $httpsList($manifest['source_urls']) && $httpsList($manifest['legal_basis_urls'])
            && $text($manifest['license']) && self::isHttpsUrl($manifest['license_url']) && $text($manifest['attribution'])
            && $count($manifest['province_count'], 1) && $count($manifest['ward_count'], 1) && $count($manifest['record_count'], 2)
            && $manifest['record_count'] === $manifest['province_count'] + $manifest['ward_count']
            && $manifest['file'] === 'administrative_units.json'
            && is_string($manifest['sha256']) && preg_match('/\A[0-9a-f]{64}\z/', $manifest['sha256']);

        if (array_key_exists('source_sha256', $manifest)) {
            $hashes = $manifest['source_sha256'];
            $valid = $valid && is_array($hashes) && $hashes !== [] && ! array_is_list($hashes)
                && array_filter($hashes, fn (mixed $hash, mixed $label): bool => ! is_string($label) || $label === '' || ! is_string($hash) || ! preg_match('/\A[0-9a-f]{64}\z/', $hash), ARRAY_FILTER_USE_BOTH) === [];
        }

        if (! $valid) {
            throw new RuntimeException('manifest.json does not follow wire contract v1.');
        }
    }

    /**
     * @param  list<mixed>  $records
     * @param  array<string, mixed>  $manifest
     * @return list<array{code: string, name: string, level: string, parent_code: ?string, dataset_version: string, valid_from: string, valid_to: null, is_active: true}>
     */
    private static function validateRecords(array $records, array $manifest): array
    {
        $provinces = [];
        $codes = [];
        $wardParents = [];
        foreach ($records as $index => $record) {
            if (! is_array($record) || array_is_list($record) || array_diff(array_keys($record), self::RECORD_FIELDS) !== [] || count($record) !== count(self::RECORD_FIELDS)) {
                throw new RuntimeException("Record #{$index} must have exactly the 8 contract fields.");
            }
            $level = $record['level'];
            $code = $record['code'];
            $name = $record['name'];
            $valid = is_string($code) && is_string($name) && mb_check_encoding($name, 'UTF-8')
                && mb_strlen($name) >= 1 && mb_strlen($name) <= 150 && trim($name) === $name
                && $record['dataset_version'] === $manifest['dataset_version']
                && $record['valid_from'] === $manifest['effective_as_of']
                && $record['valid_to'] === null && $record['is_active'] === true
                && match ($level) {
                    'PROVINCE' => preg_match('/\A[0-9]{2}\z/', $code) === 1 && $record['parent_code'] === null,
                    'WARD' => preg_match('/\A[0-9]{5}\z/', $code) === 1 && is_string($record['parent_code']) && preg_match('/\A[0-9]{2}\z/', $record['parent_code']) === 1,
                    default => false,
                };
            if (! $valid) {
                throw new RuntimeException("Record #{$index} ({$level} ".(is_string($code) ? $code : '?').') is not valid.');
            }
            // Keys are prefixed so codes such as "01" never become integer array keys.
            if (isset($codes['c'.$code])) {
                throw new RuntimeException("Duplicate code {$code}.");
            }
            $codes['c'.$code] = true;
            if ($level === 'PROVINCE') {
                $provinces['c'.$code] = true;
            } else {
                $wardParents[$code] = $record['parent_code'];
            }
        }

        foreach ($wardParents as $ward => $parent) {
            if (! isset($provinces['c'.$parent])) {
                throw new RuntimeException("Ward {$ward} points to unknown province {$parent}.");
            }
        }
        if (count($provinces) !== $manifest['province_count'] || count($wardParents) !== $manifest['ward_count'] || count($records) !== $manifest['record_count']) {
            throw new RuntimeException('Record counts do not match the manifest.');
        }
        if (! isset($provinces['c'.$manifest['hcm_province_code']])) {
            throw new RuntimeException('hcm_province_code is not a province in this snapshot.');
        }

        return $records;
    }

    private static function isDate(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value)) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value && (int) $date->format('Y') >= 1000;
    }

    private static function isHttpsUrl(mixed $value): bool
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($value);

        return ($parts['scheme'] ?? null) === 'https' && ! isset($parts['user']) && ! isset($parts['pass']) && isset($parts['host']);
    }
}
