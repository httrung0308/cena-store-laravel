<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\User;
use App\Support\ShoeSize;
use App\Support\ShoeSizeSequence;
use App\Support\SpreadsheetReader;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

final class ProductSpreadsheetService
{
    public const MAX_ROWS = 200;

    /** @var array<string, list<string>> */
    private const ALIASES = [
        'name' => ['tensanpham', 'tensp', 'ten', 'productname', 'name'],
        'category' => ['doituong', 'danhmuc', 'category', 'tendanhmuc', 'categoryname'],
        'brand_line' => ['thuonghieu', 'brand', 'brandline', 'dongthuonghieu'],
        'base_price' => ['giagoc', 'gia', 'baseprice', 'price', 'giaban'],
        'promotion_price' => ['giakhuyenmai', 'giakm', 'promotionprice', 'giasale'],
        'warranty_months' => ['baohanh', 'warranty', 'warrantymonths', 'thangbaohanh'],
        'exchange_days' => ['doisize', 'exchange', 'exchangedays', 'ngaydoisize'],
        'description' => ['mota', 'description', 'motasanpham'],
        'color_name' => ['tenmau', 'colorname', 'color', 'mau'],
        'style_color_code' => ['maphoimau', 'mamau', 'stylecode', 'stylecolorcode', 'colorcode'],
        'sizes' => ['daisize', 'size', 'sizes', 'kichco'],
        'price_override' => ['giarieng', 'priceoverride'],
    ];

    /**
     * Parse uploaded excel or csv spreadsheet.
     *
     * @return array{products: list<array<string, mixed>>, errors: list<string>}
     */
    public function parse(string $path, string $extension): array
    {
        try {
            $rows = SpreadsheetReader::rows($path, $extension);
        } catch (RuntimeException $exception) {
            return ['products' => [], 'errors' => [$exception->getMessage()]];
        }

        [$headerIndex, $columns] = $this->header($rows);
        if ($headerIndex === null) {
            return [
                'products' => [],
                'errors' => ['File thiếu các cột bắt buộc: Tên sản phẩm, Đối tượng, Giá gốc, Tên màu, Dải size.'],
            ];
        }

        $categories = Category::whereNull('deleted_at')->get()->keyBy(fn ($c) => Str::lower($c->name));
        $categoriesBySlug = Category::whereNull('deleted_at')->get()->keyBy(fn ($c) => Str::lower($c->slug));

        $rawProducts = [];
        $currentProductName = null;
        $errors = [];
        $dataRows = array_slice($rows, $headerIndex + 1, null, true);

        if (count($dataRows) > self::MAX_ROWS) {
            return [
                'products' => [],
                'errors' => ['File có quá '.self::MAX_ROWS.' dòng. Vui lòng chia nhỏ file để import.'],
            ];
        }

        foreach ($dataRows as $index => $row) {
            $line = $index + 1;
            $name = trim($row[$columns['name'] ?? -1] ?? '');
            if ($name !== '') {
                $currentProductName = $name;
            }

            if (! $currentProductName) {
                continue;
            }

            $categoryName = trim($row[$columns['category'] ?? -1] ?? '');
            $basePrice = trim($row[$columns['base_price'] ?? -1] ?? '');
            $promotionPrice = trim($row[$columns['promotion_price'] ?? -1] ?? '');
            $colorName = trim($row[$columns['color_name'] ?? -1] ?? '');
            $colorCode = isset($columns['style_color_code']) ? trim($row[$columns['style_color_code']] ?? '') : '';
            $sizesRaw = trim($row[$columns['sizes'] ?? -1] ?? '');
            $brandLine = trim($row[$columns['brand_line'] ?? -1] ?? '');
            $desc = trim($row[$columns['description'] ?? -1] ?? '');
            $warranty = trim($row[$columns['warranty_months'] ?? -1] ?? '');
            $exchange = trim($row[$columns['exchange_days'] ?? -1] ?? '');
            $priceOverride = trim($row[$columns['price_override'] ?? -1] ?? '');

            if ($colorName === '' && $colorCode === '' && $sizesRaw === '') {
                continue;
            }

            if (! isset($rawProducts[$currentProductName])) {
                $rawProducts[$currentProductName] = [
                    'line' => $line,
                    'name' => $currentProductName,
                    'category_raw' => $categoryName,
                    'brand_line' => $brandLine,
                    'base_price' => $basePrice,
                    'promotion_price' => $promotionPrice !== '' ? $promotionPrice : null,
                    'warranty_months' => $warranty !== '' ? (int) $warranty : 6,
                    'exchange_days' => $exchange !== '' ? (int) $exchange : 7,
                    'description' => $desc,
                    'has_conflict' => false,
                    'colors' => [],
                ];
            } else {
                $existing = $rawProducts[$currentProductName];
                $productConflicts = $this->detectProductFieldConflicts($existing, [
                    'category_raw' => $categoryName,
                    'brand_line' => $brandLine,
                    'base_price' => $basePrice,
                    'promotion_price' => $promotionPrice,
                    'warranty' => $warranty,
                    'exchange' => $exchange,
                    'description' => $desc,
                ]);
                if ($productConflicts !== []) {
                    $rawProducts[$currentProductName]['has_conflict'] = true;
                    foreach ($productConflicts as $fieldLabel) {
                        $errors[] = "Dòng {$line}: Trường '{$fieldLabel}' xung đột với dòng đầu tiên (dòng {$existing['line']}) của sản phẩm '{$currentProductName}'.";
                    }
                }
            }

            $rawProducts[$currentProductName]['colors'][] = [
                'line' => $line,
                'color_name' => $colorName,
                'style_color_code' => $colorCode,
                'sizes_raw' => $sizesRaw,
                'price_override' => $priceOverride !== '' ? $priceOverride : null,
            ];
        }

        if (empty($rawProducts)) {
            return ['products' => [], 'errors' => ['File không có dữ liệu sản phẩm hợp lệ.']];
        }

        $validatedProducts = [];
        $usedColorCodes = ProductColor::withTrashed()
            ->pluck('style_color_code')
            ->mapWithKeys(fn (string $code): array => [strtoupper($code) => true])
            ->all();

        foreach ($rawProducts as $productName => $pData) {
            $pLine = $pData['line'];
            $productHasError = (bool) $pData['has_conflict'];
            $catKey = Str::lower($pData['category_raw']);
            $matchedCategory = $categories->get($catKey) ?? $categoriesBySlug->get($catKey);

            if (! $matchedCategory) {
                $errors[] = "Dòng {$pLine}: Không tìm thấy đối tượng '{$pData['category_raw']}' trong hệ thống.";

                continue;
            }

            $cleanBasePrice = preg_replace('/[^0-9]/', '', (string) $pData['base_price']);
            if (! $cleanBasePrice || (int) $cleanBasePrice < 1) {
                $errors[] = "Dòng {$pLine}: Giá gốc '{$pData['base_price']}' không hợp lệ (phải là số nguyên > 0).";

                continue;
            }

            $cleanPromoPrice = null;
            if ($pData['promotion_price'] !== null) {
                $cleanPromoPrice = (int) preg_replace('/[^0-9]/', '', (string) $pData['promotion_price']);
                if ($cleanPromoPrice >= (int) $cleanBasePrice || $cleanPromoPrice <= 0) {
                    $errors[] = "Dòng {$pLine}: Giá khuyến mãi '{$pData['promotion_price']}' phải nhỏ hơn giá gốc và lớn hơn 0.";

                    continue;
                }
            }

            $colorsByCode = [];
            $codeByColorName = [];

            foreach ($pData['colors'] as $cData) {
                $cLine = $cData['line'];
                if ($cData['color_name'] === '') {
                    $errors[] = "Dòng {$cLine}: Thiếu tên màu.";
                    $productHasError = true;

                    continue;
                }

                $colorNameKey = mb_strtolower(trim($cData['color_name']));
                $rawCode = trim($cData['style_color_code']);

                if ($rawCode !== '') {
                    if (! preg_match('/^[A-Za-z0-9_-]{1,50}$/D', $rawCode)) {
                        $errors[] = "Dòng {$cLine}: Mã phối màu '{$rawCode}' chỉ được gồm chữ cái, số, dấu gạch dưới hoặc gạch nối (tối đa 50 ký tự).";
                        $productHasError = true;

                        continue;
                    }

                    $colorCodeUpper = strtoupper($rawCode);
                    if (isset($codeByColorName[$colorNameKey]) && $codeByColorName[$colorNameKey] !== $colorCodeUpper) {
                        $errors[] = "Dòng {$cLine}: Tên màu '{$cData['color_name']}' xung đột mã phối màu ('{$colorCodeUpper}' khác '{$codeByColorName[$colorNameKey]}') trong sản phẩm '{$productName}'.";
                        $productHasError = true;

                        continue;
                    }

                    if (isset($colorsByCode[$colorCodeUpper]) && $colorsByCode[$colorCodeUpper]['color_name_key'] !== $colorNameKey) {
                        $errors[] = "Dòng {$cLine}: Mã phối màu '{$colorCodeUpper}' bị trùng cho nhiều tên màu khác nhau ('{$cData['color_name']}' khác '{$colorsByCode[$colorCodeUpper]['color_name']}') trong sản phẩm '{$productName}'.";
                        $productHasError = true;

                        continue;
                    }
                } elseif (isset($codeByColorName[$colorNameKey])) {
                    $colorCodeUpper = $codeByColorName[$colorNameKey];
                } else {
                    $colorCodeUpper = $this->generateUniqueStyleColorCode($cData['color_name'], $usedColorCodes);
                }

                $codeByColorName[$colorNameKey] = $colorCodeUpper;
                $usedColorCodes[$colorCodeUpper] = true;

                // Parse sizes: e.g. "39-43" or "39, 40, 40.5, 41"
                $sizesRaw = $cData['sizes_raw'];
                $parsedSizes = [];

                if (str_contains($sizesRaw, '-')) {
                    [$start, $end] = explode('-', $sizesRaw, 2);
                    try {
                        $parsedSizes = ShoeSizeSequence::between(trim($start), trim($end));
                    } catch (\Throwable) {
                        $errors[] = "Dòng {$cLine}: Dải size '{$sizesRaw}' không hợp lệ.";
                        $productHasError = true;

                        continue;
                    }
                } else {
                    $parts = preg_split('/[,;\s]+/', $sizesRaw) ?: [];
                    foreach ($parts as $part) {
                        $trimmed = trim($part);
                        if ($trimmed === '') {
                            continue;
                        }
                        try {
                            $parsedSizes[] = ShoeSize::normalize($trimmed);
                        } catch (DomainException) {
                            $errors[] = "Dòng {$cLine}: Size '{$trimmed}' không hợp lệ.";
                            $productHasError = true;

                            continue 2;
                        }
                    }
                }

                if (empty($parsedSizes)) {
                    $errors[] = "Dòng {$cLine}: Phối màu '{$cData['color_name']}' chưa có size hợp lệ.";
                    $productHasError = true;

                    continue;
                }

                $priceOverride = null;
                if ($cData['price_override'] !== null) {
                    $cleanOverride = preg_replace('/[^0-9]/', '', (string) $cData['price_override']);
                    if ($cleanOverride) {
                        $priceOverride = (int) $cleanOverride;
                    }
                }

                if (! isset($colorsByCode[$colorCodeUpper])) {
                    $colorsByCode[$colorCodeUpper] = [
                        'color_name' => $cData['color_name'],
                        'color_name_key' => $colorNameKey,
                        'style_color_code' => $colorCodeUpper,
                        'is_active' => false,
                        'variants' => [],
                        'images' => [],
                        'seen_sizes' => [],
                    ];
                }

                foreach (array_unique($parsedSizes) as $sz) {
                    if (isset($colorsByCode[$colorCodeUpper]['seen_sizes'][$sz])) {
                        $errors[] = "Dòng {$cLine}: Size '{$sz}' bị lặp trong màu '{$cData['color_name']}' của sản phẩm '{$productName}'.";
                        $productHasError = true;

                        continue;
                    }
                    $colorsByCode[$colorCodeUpper]['seen_sizes'][$sz] = true;
                    $colorsByCode[$colorCodeUpper]['variants'][] = [
                        'size_value' => $sz,
                        'sku' => '', // Will be generated automatically
                        'price_override' => $priceOverride,
                        'is_active' => false,
                    ];
                }
            }

            $colorsPayload = array_map(static function (array $color): array {
                unset($color['color_name_key'], $color['seen_sizes']);

                return $color;
            }, array_values($colorsByCode));

            if ($productHasError || empty($colorsPayload)) {
                continue;
            }

            $validatedProducts[] = [
                'name' => $productName,
                'category_id' => $matchedCategory->id,
                'category_name' => $matchedCategory->name,
                'brand_line' => $pData['brand_line'] ?: null,
                'base_price' => (int) $cleanBasePrice,
                'promotion_price' => $cleanPromoPrice,
                'warranty_months' => $pData['warranty_months'],
                'exchange_days' => $pData['exchange_days'],
                'description' => $pData['description'] ?: null,
                'colors' => $colorsPayload,
                'total_variants' => array_sum(array_map(fn ($c) => count($c['variants']), $colorsPayload)),
            ];
        }

        return [
            'products' => $validatedProducts,
            'errors' => $errors,
        ];
    }

    /**
     * Save parsed products to database without images, sets is_active = false.
     *
     * @param  list<array<string, mixed>>  $products
     * @return array{created_count: int, products: list<Product>}
     */
    public function import(User $actor, array $products, ProductManagementService $pmService): array
    {
        $created = [];

        foreach ($products as $pData) {
            $baseSlug = Str::slug($pData['name']);
            $candidateSlug = $baseSlug;
            $counter = 1;
            while (Product::where('slug', $candidateSlug)->exists()) {
                $candidateSlug = sprintf('%s-%02d', $baseSlug, $counter++);
            }

            $payload = [
                'name' => $pData['name'],
                'slug' => $candidateSlug,
                'category_id' => $pData['category_id'],
                'brand_line' => $pData['brand_line'] ?? null,
                'description' => $pData['description'] ?? null,
                'base_price' => $pData['base_price'],
                'promotion_price' => $pData['promotion_price'] ?? null,
                'warranty_months' => $pData['warranty_months'] ?? 6,
                'exchange_days' => $pData['exchange_days'] ?? 7,
                'is_active' => 0,
                'is_featured' => 0,
                'colors' => $pData['colors'],
            ];

            $product = $pmService->save($actor, null, $payload, true);
            $created[] = $product;
        }

        return [
            'created_count' => count($created),
            'products' => $created,
        ];
    }

    /**
     * Generate template CSV content with UTF-8 BOM for perfect Excel Vietnamese rendering.
     */
    public static function templateCsv(): string
    {
        $bom = "\xEF\xBB\xBF";
        $template = self::templateData();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $template['headers']);
        foreach ($template['rows'] as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $bom.$csvContent;
    }

    /**
     * Generates a basic valid .xlsx spreadsheet template.
     */
    public static function templateXlsx(): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'cena_tmpl_').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Content Types
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // Rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // Workbook
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="MauNhapSanPham" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);

        // Workbook Rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // Sheet data
        $template = self::templateData();
        $rowsData = array_merge([$template['headers']], $template['rows']);

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>';
        foreach ($rowsData as $rIdx => $row) {
            $rowNum = $rIdx + 1;
            $sheetXml .= "<row r=\"{$rowNum}\">";
            foreach ($row as $cIdx => $val) {
                $colLetter = chr(65 + $cIdx);
                $escaped = htmlspecialchars((string) $val, ENT_XML1, 'UTF-8');
                $sheetXml .= "<c r=\"{$colLetter}{$rowNum}\" t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";
            }
            $sheetXml .= '</row>';
        }
        $sheetXml .= '  </sheetData>
</worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        return $tempPath;
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    private static function templateData(): array
    {
        return [
            'headers' => [
                'Tên sản phẩm',
                'Đối tượng',
                'Dòng thương hiệu',
                'Giá gốc',
                'Giá khuyến mãi',
                'Bảo hành (tháng)',
                'Đổi size (ngày)',
                'Mô tả',
                'Tên màu',
                'Mã phối màu',
                'Dải size',
                'Giá riêng',
            ],
            'rows' => [
                [
                    'Giày Chạy Bộ Cena Pegasus 40',
                    'Nam',
                    'Pegasus',
                    '1850000',
                    '1650000',
                    '6',
                    '7',
                    'Đệm khí êm ái, thoáng khí tối đa',
                    'Đen Trắng',
                    'BLK',
                    '39-43',
                    '',
                ],
                [
                    'Giày Chạy Bộ Cena Pegasus 40',
                    'Nam',
                    'Pegasus',
                    '1850000',
                    '1650000',
                    '6',
                    '7',
                    'Đệm khí êm ái, thoáng khí tối đa',
                    'Trắng Cam',
                    '',
                    '40, 40.5, 41, 42',
                    '',
                ],
                [
                    'Giày Sneaker Cena Air Court Classic',
                    'Unisex',
                    'Court',
                    '1200000',
                    '',
                    '6',
                    '7',
                    'Phong cách retro cổ điển đi hàng ngày',
                    'Trắng Xanh',
                    'WHT-BLU',
                    '38-42',
                    '',
                ],
            ],
        ];
    }

    /**
     * Detect product-level field conflicts between a continuation row and the product's first row.
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, string>  $row
     * @return list<string>
     */
    private function detectProductFieldConflicts(array $existing, array $row): array
    {
        $conflicts = [];

        if ($row['category_raw'] !== '' && Str::lower($row['category_raw']) !== Str::lower((string) $existing['category_raw'])) {
            $conflicts[] = 'Đối tượng';
        }
        if ($row['brand_line'] !== '' && $row['brand_line'] !== (string) $existing['brand_line']) {
            $conflicts[] = 'Dòng thương hiệu';
        }
        if ($row['base_price'] !== '') {
            $rowBase = preg_replace('/[^0-9]/', '', $row['base_price']);
            $existingBase = preg_replace('/[^0-9]/', '', (string) $existing['base_price']);
            if ($rowBase !== $existingBase) {
                $conflicts[] = 'Giá gốc';
            }
        }
        if ($row['promotion_price'] !== '') {
            $rowPromo = preg_replace('/[^0-9]/', '', $row['promotion_price']);
            $existingPromo = $existing['promotion_price'] !== null ? preg_replace('/[^0-9]/', '', (string) $existing['promotion_price']) : '';
            if ($rowPromo !== $existingPromo) {
                $conflicts[] = 'Giá khuyến mãi';
            }
        }
        if ($row['warranty'] !== '' && (int) $row['warranty'] !== (int) $existing['warranty_months']) {
            $conflicts[] = 'Bảo hành (tháng)';
        }
        if ($row['exchange'] !== '' && (int) $row['exchange'] !== (int) $existing['exchange_days']) {
            $conflicts[] = 'Đổi size (ngày)';
        }
        if ($row['description'] !== '' && $row['description'] !== (string) $existing['description']) {
            $conflicts[] = 'Mô tả';
        }

        return $conflicts;
    }

    /**
     * Generate a unique style_color_code matching ^[A-Za-z0-9_-]+$ from color_name.
     *
     * @param  array<string, bool>  $usedColorCodes
     */
    private function generateUniqueStyleColorCode(string $colorName, array $usedColorCodes): string
    {
        $ascii = Str::ascii($colorName);
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', trim($ascii)) ?? '');
        $base = trim(substr(trim($base, '-_'), 0, 40), '-_');
        if ($base === '') {
            $base = 'COLOR';
        }

        $candidate = $base;
        $counter = 1;
        while (isset($usedColorCodes[$candidate])) {
            $candidate = sprintf('%s-%02d', $base, $counter++);
        }

        return $candidate;
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array{0: int|null, 1: array<string, int>}
     */
    private function header(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $normalized = array_map(fn ($cell) => Str::lower(preg_replace('/[^a-zA-Z0-9]/', '', Str::ascii((string) $cell))), $row);
            $found = [];

            foreach (self::ALIASES as $key => $aliases) {
                foreach ($aliases as $alias) {
                    $pos = array_search($alias, $normalized, true);
                    if ($pos !== false) {
                        $found[$key] = $pos;
                        break;
                    }
                }
            }

            if (isset($found['name'], $found['category'], $found['base_price'], $found['color_name'], $found['sizes'])) {
                return [$index, $found];
            }
        }

        return [null, []];
    }
}
