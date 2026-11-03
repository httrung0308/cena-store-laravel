<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads the first sheet of an .xlsx workbook or a .csv file into rows of trimmed strings, without third-party
 * libraries (ext-zip is already required). Only stored values are read: formulas give their last saved result,
 * styles and other sheets are ignored. Numeric cells come back as their stored decimal text, e.g. "1050000".
 */
final class SpreadsheetReader
{
    /** Uncompressed XML parts larger than this are refused (protects against zip bombs). */
    private const MAX_PART_BYTES = 20 * 1024 * 1024;

    /**
     * @return list<list<string>> Rows in sheet order; each row is indexed from column A = 0, gaps filled with "".
     *
     * @throws RuntimeException When the file cannot be read as the given type.
     */
    public static function rows(string $path, string $extension): array
    {
        return match (strtolower($extension)) {
            'xlsx' => self::xlsx($path),
            'csv', 'txt' => self::csv($path),
            default => throw new RuntimeException('Chỉ đọc được file .xlsx hoặc .csv.'),
        };
    }

    /** @return list<list<string>> */
    private static function xlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('File không phải bảng tính Excel (.xlsx) hợp lệ.');
        }
        try {
            $shared = [];
            $strings = self::part($zip, 'xl/sharedStrings.xml', false);
            if ($strings !== null) {
                foreach ($strings->si as $item) {
                    $shared[] = self::text($item);
                }
            }
            $sheet = self::part($zip, self::firstSheetPath($zip), true);
        } finally {
            $zip->close();
        }

        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $column = self::columnIndex((string) $cell['r']) ?? count($cells);
                $type = (string) $cell['t'];
                $value = match ($type) {
                    's' => $shared[(int) $cell->v] ?? '',
                    'inlineStr' => self::text($cell->is),
                    'b' => (string) $cell->v === '1' ? 'TRUE' : 'FALSE',
                    default => (string) $cell->v,
                };
                $cells[$column] = trim($value);
            }
            $rowNumber = (int) $row['r'] ?: count($rows) + 1;
            $rows[$rowNumber - 1] = $cells === [] ? [] : array_replace(array_fill(0, max(array_keys($cells)) + 1, ''), $cells);
        }
        if ($rows === []) {
            return [];
        }

        return array_replace(array_fill(0, max(array_keys($rows)) + 1, []), $rows);
    }

    /** @return list<list<string>> */
    private static function csv(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $first = (string) ($lines[0] ?? '');
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn (string $candidate): int => substr_count($first, $candidate))->first();

        $rows = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $rows[] = $row === [null] ? [] : array_map(fn (?string $value): string => trim((string) $value), $row);
        }
        fclose($handle);

        return $rows;
    }

    /** Resolves the first worksheet through workbook.xml and its relationships; falls back to sheet1.xml. */
    private static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = self::part($zip, 'xl/workbook.xml', false);
        $relations = self::part($zip, 'xl/_rels/workbook.xml.rels', false);
        if ($workbook !== null && $relations !== null && isset($workbook->sheets->sheet[0])) {
            $id = (string) $workbook->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            foreach ($relations->Relationship as $relation) {
                if ((string) $relation['Id'] === $id) {
                    $target = ltrim((string) $relation['Target'], '/');

                    return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private static function part(ZipArchive $zip, string $name, bool $required): ?SimpleXMLElement
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            if ($required) {
                throw new RuntimeException('Không tìm thấy trang tính trong file Excel.');
            }

            return null;
        }
        if ($stat['size'] > self::MAX_PART_BYTES) {
            throw new RuntimeException('File Excel quá lớn.');
        }
        $xml = simplexml_load_string((string) $zip->getFromName($name), SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        if ($xml === false) {
            throw new RuntimeException('File Excel bị hỏng, không đọc được.');
        }

        return $xml;
    }

    /** Plain text of a shared or inline string, including rich-text runs. */
    private static function text(SimpleXMLElement $node): string
    {
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $text = '';
        foreach ($node->r as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    /** "C12" → 2 (zero-based column index). */
    private static function columnIndex(string $reference): ?int
    {
        if (! preg_match('/^([A-Z]+)[0-9]*$/', strtoupper($reference), $match)) {
            return null;
        }
        $index = 0;
        foreach (str_split($match[1]) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
