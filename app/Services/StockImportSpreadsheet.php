<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Support\SpreadsheetReader;
use App\Support\XlsxWriter;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns an uploaded .xlsx/.csv sheet into stock-import lines for the draft form, and builds the matching template.
 * Columns are found by header name (SKU, Số lượng, Giá nhập/đôi); any other column is ignored, so the template's
 * product/colour/size columns can stay. Rows without a quantity and a cost are skipped, so an untouched template
 * line is not an error. Nothing is saved here: the lines go back into the form for review.
 */
class StockImportSpreadsheet
{
    public const MAX_LINES = 100;

    public const HEADER = ['SKU', 'Sản phẩm', 'Màu', 'Size', 'Số lượng', 'Giá nhập/đôi (₫)'];

    /** @var array<string, list<string>> Normalised header spellings accepted for each field. */
    private const ALIASES = [
        'sku' => ['sku', 'masku', 'mahang', 'ma'],
        'quantity' => ['soluong', 'sl', 'quantity', 'qty', 'soluongnhap'],
        'unit_cost_amount' => ['gianhap', 'gianhapdoi', 'gianhapdoid', 'dongia', 'gianhapvnd', 'unitcost', 'cost', 'gia'],
    ];

    /**
     * @return array{items: list<array{variant_id: string, quantity: string, unit_cost_amount: string}>, errors: list<string>}
     */
    public function parse(string $path, string $extension): array
    {
        try {
            $rows = SpreadsheetReader::rows($path, $extension);
        } catch (RuntimeException $exception) {
            return ['items' => [], 'errors' => [$exception->getMessage()]];
        }

        [$headerIndex, $columns] = $this->header($rows);
        if ($headerIndex === null) {
            return ['items' => [], 'errors' => ['Không tìm thấy dòng tiêu đề có các cột SKU, Số lượng và Giá nhập. Hãy dùng file mẫu.']];
        }

        $lines = [];
        $errors = [];
        foreach (array_slice($rows, $headerIndex + 1, null, true) as $index => $row) {
            $line = $index + 1;
            $sku = trim($row[$columns['sku']] ?? '');
            $quantity = trim($row[$columns['quantity']] ?? '');
            $cost = trim($row[$columns['unit_cost_amount']] ?? '');
            if ($quantity === '' && $cost === '') {
                continue;
            }
            if ($sku === '') {
                $errors[] = "Dòng {$line}: thiếu SKU.";

                continue;
            }
            $lines[] = ['line' => $line, 'sku' => $sku, 'quantity' => $this->whole($quantity), 'cost' => $this->whole($cost), 'rawQuantity' => $quantity, 'rawCost' => $cost];
        }

        if ($lines === [] && $errors === []) {
            return ['items' => [], 'errors' => ['File chưa có dòng nào điền số lượng và giá nhập.']];
        }
        if (count($lines) > self::MAX_LINES) {
            return ['items' => [], 'errors' => ['File có '.count($lines).' dòng, mỗi phiếu nhập tối đa '.self::MAX_LINES.' dòng. Hãy tách thành nhiều file.']];
        }

        $variants = ProductVariant::query()->whereIn('sku', array_unique(array_column($lines, 'sku')))->pluck('id', 'sku');
        $seen = [];
        $items = [];
        foreach ($lines as $entry) {
            $line = $entry['line'];
            $variantId = $variants[$entry['sku']] ?? null;
            $problems = [];
            if ($variantId === null) {
                $problems[] = 'không có SKU "'.$entry['sku'].'"';
            } elseif (isset($seen[$variantId])) {
                $problems[] = 'SKU '.$entry['sku'].' đã có ở dòng '.$seen[$variantId];
            }
            if ($entry['quantity'] === null || $entry['quantity'] < 1) {
                $problems[] = 'số lượng "'.$entry['rawQuantity'].'" phải là số nguyên từ 1 trở lên';
            }
            if ($entry['cost'] === null) {
                $problems[] = 'giá nhập "'.$entry['rawCost'].'" phải là số tiền không âm, ví dụ 1050000';
            }
            if ($problems !== []) {
                $errors[] = "Dòng {$line}: ".implode('; ', $problems).'.';

                continue;
            }
            $seen[$variantId] = $line;
            $items[] = ['variant_id' => (string) $variantId, 'quantity' => (string) $entry['quantity'], 'unit_cost_amount' => (string) $entry['cost']];
        }

        return ['items' => $items, 'errors' => $errors];
    }

    /** Writes the template: every active size with its product, colour and size, quantity and cost left blank. */
    public function template(string $path): void
    {
        $rows = ProductVariant::query()
            ->join('product_colors', 'product_colors.id', '=', 'product_variants.product_color_id')
            ->join('products', 'products.id', '=', 'product_colors.product_id')
            ->where('product_variants.is_active', true)->whereNull('product_variants.deleted_at')
            ->whereNull('product_colors.deleted_at')->whereNull('products.deleted_at')
            ->orderBy('products.name')->orderBy('product_colors.color_name')->orderBy('product_variants.size_numeric')->orderBy('product_variants.id')
            ->get(['product_variants.sku', 'products.name', 'product_colors.color_name', 'product_variants.size_value'])
            ->map(fn (ProductVariant $variant): array => [$variant->sku, $variant->name, $variant->color_name, (string) $variant->size_value, null, null])
            ->all();

        XlsxWriter::write($path, 'Phiếu nhập', self::HEADER, $rows, [24, 34, 22, 8, 12, 18]);
    }

    /**
     * Finds the first row that names all three required columns.
     *
     * @param  list<list<string>>  $rows
     * @return array{0: ?int, 1: array<string, int>}
     */
    private function header(array $rows): array
    {
        foreach (array_slice($rows, 0, 20, true) as $index => $row) {
            $columns = [];
            foreach ($row as $column => $title) {
                $name = preg_replace('/[^a-z0-9]/', '', Str::ascii(mb_strtolower($title)));
                foreach (self::ALIASES as $field => $aliases) {
                    if (! isset($columns[$field]) && in_array($name, $aliases, true)) {
                        $columns[$field] = $column;
                    }
                }
            }
            if (count($columns) === count(self::ALIASES)) {
                return [$index, $columns];
            }
        }

        return [null, []];
    }

    /**
     * Whole non-negative number from a cell: "12", "12.0" (Excel numbers), "1.050.000", "1,050,000" or "1 050 000 ₫".
     */
    private function whole(string $value): ?int
    {
        $value = trim(str_replace(['₫', 'đ', 'Đ', 'vnd', 'VND', ' ', "\u{00A0}"], '', $value));
        if (preg_match('/^[0-9]{1,3}([.,][0-9]{3})+$/D', $value)) {
            $value = str_replace(['.', ','], '', $value);
        } elseif (preg_match('/^([0-9]+)\.0+$/D', $value, $match)) {
            $value = $match[1];
        }
        if (! preg_match('/^[0-9]{1,15}$/D', $value)) {
            return null;
        }

        return (int) $value;
    }
}
