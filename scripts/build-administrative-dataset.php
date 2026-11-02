<?php

declare(strict_types=1);

/*
 * Converts the Địa Chỉ Việt open data (tinh.json, xa.json) into database/data/administrative_units.json
 * and manifest.json (wire contract v1, docs/collaboration/data-source-proposal.md sections 3-4).
 *
 * Usage (download the three source files into a folder OUTSIDE the repository first):
 *   CENA_DATASET_VERSION=dvc-20260920 CENA_EFFECTIVE_AS_OF=2026-09-20 CENA_HCM_PROVINCE_CODE=79 \
 *   CENA_RETRIEVED_AT=2026-11-02 php scripts/build-administrative-dataset.php /path/to/raw
 *
 * Structural checks only. They do not prove the names or codes are legally correct.
 */

$root = dirname(__DIR__);
$raw = rtrim($argv[1] ?? '', '/\\');
if ($raw === '' || ! is_dir($raw)) {
    fwrite(STDERR, "Pass the folder that holds tinh.json, xa.json and giay-phep.txt.\n");
    exit(1);
}

function required(string $key): string
{
    $value = getenv($key);
    if (! is_string($value) || $value === '') {
        throw new RuntimeException("Missing owner-approved setting {$key}.");
    }

    return $value;
}

function isoDate(string $value): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (! preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value) || $date === false || $date->format('Y-m-d') !== $value || (int) $date->format('Y') < 1000) {
        throw new RuntimeException("Invalid date {$value}.");
    }

    return $value;
}

$version = required('CENA_DATASET_VERSION');
$effective = isoDate(required('CENA_EFFECTIVE_AS_OF'));
$retrieved = isoDate(required('CENA_RETRIEVED_AT'));
$hcmCode = required('CENA_HCM_PROVINCE_CODE');
if (! preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,49}\z/', $version) || ! preg_match('/\A[0-9]{2}\z/', $hcmCode) || $retrieved < $effective) {
    throw new RuntimeException('Version, HCM code or retrieved date is not valid.');
}

$sourceUrls = [
    'tinh.json' => 'https://diachiviet.com/wp-content/uploads/dvc/open-data/tinh.json',
    'xa.json' => 'https://diachiviet.com/wp-content/uploads/dvc/open-data/xa.json',
    'giay-phep.txt' => 'https://diachiviet.com/wp-content/uploads/dvc/open-data/giay-phep.txt',
];
$bytes = [];
foreach (array_keys($sourceUrls) as $name) {
    $content = file_get_contents($raw.'/'.$name);
    if ($content === false || $content === '') {
        throw new RuntimeException("Cannot read {$name}.");
    }
    $bytes[$name] = $content;
}

$decode = static function (string $json, array $columns): array {
    $data = json_decode(preg_replace('/\A\xEF\xBB\xBF/', '', $json), true, 16, JSON_THROW_ON_ERROR);
    if (! is_array($data) || ! is_array($data['cot'] ?? null) || ! is_array($data['dong'] ?? null) || $data['dong'] === []
        || array_diff($columns, $data['cot']) !== []) {
        throw new RuntimeException('Unexpected source layout; audit the downloaded file before converting.');
    }

    return $data['dong'];
};
$provinces = $decode($bytes['tinh.json'], ['ma_tinh', 'ten_day_du', 'so_don_vi_cap_xa']);
$wards = $decode($bytes['xa.json'], ['ma_xa', 'ten', 'loai', 'ma_tinh']);

$records = [];
$wardCounts = [];
$validName = static fn (mixed $name): bool => is_string($name) && mb_check_encoding($name, 'UTF-8')
    && mb_strlen($name) >= 1 && mb_strlen($name) <= 150 && trim($name) === $name;

foreach ($provinces as $row) {
    if (! is_string($row['ma_tinh'] ?? null) || ! preg_match('/\A[0-9]{2}\z/', $row['ma_tinh']) || ! $validName($row['ten_day_du'] ?? null)) {
        throw new RuntimeException('Invalid province row.');
    }
    $records[] = ['code' => $row['ma_tinh'], 'name' => $row['ten_day_du'], 'level' => 'PROVINCE', 'parent_code' => null];
}
foreach ($wards as $row) {
    // Ward "ten" already carries its type ("Phường …", "Xã …", "Đặc khu …"); the file has no ten_day_du column.
    if (! is_string($row['ma_xa'] ?? null) || ! preg_match('/\A[0-9]{5}\z/', $row['ma_xa']) || ! $validName($row['ten'] ?? null)
        || ! is_string($row['ma_tinh'] ?? null) || ! preg_match('/\A[0-9]{2}\z/', $row['ma_tinh'])
        || ! is_string($row['loai'] ?? null) || ! str_starts_with($row['ten'], $row['loai'].' ')) {
        throw new RuntimeException('Invalid ward row '.json_encode($row, JSON_UNESCAPED_UNICODE));
    }
    $records[] = ['code' => $row['ma_xa'], 'name' => $row['ten'], 'level' => 'WARD', 'parent_code' => $row['ma_tinh']];
    $wardCounts[$row['ma_tinh']] = ($wardCounts[$row['ma_tinh']] ?? 0) + 1;
}

$provinceCodes = array_column(array_filter($records, fn (array $r): bool => $r['level'] === 'PROVINCE'), 'code');
$allCodes = array_map(fn (array $r): string => 'c'.$r['code'], $records);
if (count($allCodes) !== count(array_unique($allCodes)) || ! in_array($hcmCode, $provinceCodes, true)) {
    throw new RuntimeException('Duplicate codes or HCM code missing.');
}
foreach ($records as $record) {
    if ($record['level'] === 'WARD' && ! in_array($record['parent_code'], $provinceCodes, true)) {
        throw new RuntimeException("Ward {$record['code']} has an unknown province.");
    }
}
foreach ($provinces as $row) {
    if (($wardCounts[$row['ma_tinh']] ?? 0) !== $row['so_don_vi_cap_xa']) {
        throw new RuntimeException("Published ward count disagrees for province {$row['ma_tinh']}.");
    }
}

usort($records, fn (array $a, array $b): int => [$a['level'] === 'PROVINCE' ? 0 : 1, $a['code']] <=> [$b['level'] === 'PROVINCE' ? 0 : 1, $b['code']]);
$records = array_map(fn (array $r): array => $r + [
    'dataset_version' => $version,
    'valid_from' => $effective,
    'valid_to' => null,
    'is_active' => true,
], $records);

$dataBytes = json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
$provinceCount = count($provinceCodes);
$manifest = [
    'schema_version' => 1,
    'dataset_version' => $version,
    'hcm_province_code' => $hcmCode,
    'effective_as_of' => $effective,
    'retrieved_at' => $retrieved,
    'source_urls' => array_values($sourceUrls),
    'legal_basis_urls' => [
        'https://vanban.chinhphu.vn/?docid=214409&pageid=27160',
        'https://vanban.chinhphu.vn/?classid=1&docid=218009&pageid=27160',
        'https://vanban.chinhphu.vn/?classid=1&docid=219325&orggroupid=1&pageid=27160',
        'https://vanban.chinhphu.vn/?docid=219328&pageid=27160',
    ],
    'license' => 'CC BY 4.0',
    'license_url' => 'https://creativecommons.org/licenses/by/4.0/',
    'attribution' => 'Nguồn: Địa Chỉ Việt (diachiviet.com), dữ liệu mở đơn vị hành chính, CC BY 4.0 — https://diachiviet.com/du-lieu-mo/. Cena Store chỉ chuyển đổi định dạng, không sửa tên/mã.',
    'province_count' => $provinceCount,
    'ward_count' => count($records) - $provinceCount,
    'record_count' => count($records),
    'file' => 'administrative_units.json',
    'sha256' => hash('sha256', $dataBytes),
    'source_sha256' => array_map(fn (string $content): string => hash('sha256', $content), $bytes),
];

$out = $root.'/database/data';
if (! is_dir($out) && ! mkdir($out, 0775, true)) {
    throw new RuntimeException('Cannot create database/data.');
}
file_put_contents($out.'/administrative_units.json', $dataBytes);
file_put_contents($out.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");

fwrite(STDOUT, "Wrote {$manifest['record_count']} records ({$manifest['province_count']} provinces, {$manifest['ward_count']} wards).\n");
