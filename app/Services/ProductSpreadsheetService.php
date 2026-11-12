<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\Category;
use App\Models\Product;
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
        'category' => ['danhmuc', 'category', 'tendanhmuc', 'categoryname'],
        'brand_line' => ['thuonghieu', 'brand', 'brandline', 'dongthuonghieu'],
        'base_price' => ['giagoc', 'gia', 'baseprice', 'price', 'giaban'],
        'promotion_price' => ['giakhuyenmai', 'giakm', 'promotionprice', 'giasale'],
        'warranty_months' => ['baohanh', 'warranty', 'warrantymonths', 'thangbaohanh'],
        'exchange_days' => ['doisize', 'exchange', 'exchangedays', 'ngaydoisize'],
        'description' => ['mota', 'description', 'motasanpham'],
        'color_name' => ['tenmau', 'colorname', 'color', 'mau'],
        'style_color_code' => ['mamau', 'stylecode', 'stylecolorcode', 'colorcode', 'maphoimau'],
        'primary_hex' => ['hex', 'mahex', 'colorhex', 'hexcode'],
        'color_key' => ['nhommau', 'colorkey', 'palette'],
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
                'errors' => ['File thiếu các cột bắt buộc: Tên sản phẩm, Danh mục, Giá gốc, Tên màu, Mã màu, Dải size.'],
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
            $colorCode = trim($row[$columns['style_color_code'] ?? -1] ?? '');
            $hex = trim($row[$columns['primary_hex'] ?? -1] ?? '');
            $colorKey = trim($row[$columns['color_key'] ?? -1] ?? '');
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
                    'promotion_price' => $promotionPrice ?: null,
                    'warranty_months' => $warranty !== '' ? (int) $warranty : 6,
                    'exchange_days' => $exchange !== '' ? (int) $exchange : 7,
                    'description' => $desc,
                    'colors' => [],
                ];
            } else {
                if ($categoryName !== '') {
                    $rawProducts[$currentProductName]['category_raw'] = $categoryName;
                }
                if ($basePrice !== '') {
                    $rawProducts[$currentProductName]['base_price'] = $basePrice;
                }
            }

            $rawProducts[$currentProductName]['colors'][] = [
                'line' => $line,
                'color_name' => $colorName,
                'style_color_code' => $colorCode,
                'primary_hex' => $hex ?: '#000000',
                'color_key' => $colorKey ?: 'black',
                'sizes_raw' => $sizesRaw,
                'price_override' => $priceOverride ?: null,
            ];
        }

        if (empty($rawProducts)) {
            return ['products' => [], 'errors' => ['File không có dữ liệu sản phẩm hợp lệ.']];
        }

        $validatedProducts = [];
        $paletteKeys = array_keys(config('store.palette', []));

        foreach ($rawProducts as $productName => $pData) {
            $pLine = $pData['line'];
            $catKey = Str::lower($pData['category_raw']);
            $matchedCategory = $categories->get($catKey) ?? $categoriesBySlug->get($catKey);

            if (! $matchedCategory) {
                $errors[] = "Dòng {$pLine}: Không tìm thấy danh mục '{$pData['category_raw']}' trong hệ thống.";

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

            $colorsPayload = [];
            $seenColorCodes = [];

            foreach ($pData['colors'] as $cData) {
                $cLine = $cData['line'];
                if (empty($cData['color_name'])) {
                    $errors[] = "Dòng {$cLine}: Thiếu tên màu.";

                    continue;
                }
                if (empty($cData['style_color_code'])) {
                    $errors[] = "Dòng {$cLine}: Thiếu mã phối màu (style code).";

                    continue;
                }

                $colorCodeUpper = strtoupper($cData['style_color_code']);
                if (isset($seenColorCodes[$colorCodeUpper])) {
                    $errors[] = "Dòng {$cLine}: Mã màu '{$colorCodeUpper}' bị lặp trong sản phẩm '{$productName}'.";

                    continue;
                }
                $seenColorCodes[$colorCodeUpper] = true;

                $hex = $cData['primary_hex'];
                if (! preg_match('/^#[A-Fa-f0-9]{6}$/D', $hex)) {
                    $hex = '#000000';
                }

                $cKey = Str::lower($cData['color_key']);
                if (! in_array($cKey, $paletteKeys, true)) {
                    $cKey = 'black';
                }

                // Parse sizes: e.g. "39-43" or "39, 40, 40.5, 41"
                $sizesRaw = $cData['sizes_raw'];
                $parsedSizes = [];

                if (str_contains($sizesRaw, '-')) {
                    [$start, $end] = explode('-', $sizesRaw, 2);
                    try {
                        $parsedSizes = ShoeSizeSequence::between(trim($start), trim($end));
                    } catch (\Throwable) {
                        $errors[] = "Dòng {$cLine}: Dải size '{$sizesRaw}' không hợp lệ.";

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

                            continue 2;
                        }
                    }
                }

                if (empty($parsedSizes)) {
                    $errors[] = "Dòng {$cLine}: Phối màu '{$cData['color_name']}' chưa có size hợp lệ.";

                    continue;
                }

                $priceOverride = null;
                if ($cData['price_override'] !== null) {
                    $cleanOverride = preg_replace('/[^0-9]/', '', (string) $cData['price_override']);
                    if ($cleanOverride) {
                        $priceOverride = (int) $cleanOverride;
                    }
                }

                $colorsPayload[] = [
                    'color_name' => $cData['color_name'],
                    'style_color_code' => $colorCodeUpper,
                    'color_key' => $cKey,
                    'primary_hex' => $hex,
                    'is_active' => false,
                    'variants' => array_map(fn ($sz) => [
                        'size_value' => $sz,
                        'sku' => '', // Will be generated automatically
                        'price_override' => $priceOverride,
                        'is_active' => false,
                    ], array_unique($parsedSizes)),
                    'images' => [],
                ];
            }

            if (empty($colorsPayload)) {
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
        $headers = [
            'Tên sản phẩm',
            'Danh mục',
            'Dòng thương hiệu',
            'Giá gốc',
            'Giá khuyến mãi',
            'Bảo hành (tháng)',
            'Đổi size (ngày)',
            'Mô tả',
            'Tên màu',
            'Mã màu',
            'Mã hex',
            'Nhóm màu',
            'Dải size',
            'Giá riêng',
        ];

        $sampleRows = [
            [
                'Giày Chạy Bộ Cena Pegasus 40',
                'Giày Nam',
                'Pegasus',
                '1850000',
                '1650000',
                '6',
                '7',
                'Đệm khí êm ái, thoáng khí tối đa',
                'Đen Trắng',
                'BLK',
                '#000000',
                'black',
                '39-43',
                '',
            ],
            [
                'Giày Chạy Bộ Cena Pegasus 40',
                'Giày Nam',
                'Pegasus',
                '1850000',
                '1650000',
                '6',
                '7',
                'Đệm khí êm ái, thoáng khí tối đa',
                'Trắng Cam',
                'WHT',
                '#FFFFFF',
                'white',
                '40, 40.5, 41, 42',
                '',
            ],
            [
                'Giày Sneaker Cena Air Court Classic',
                'Giày Unisex',
                'Court',
                '1200000',
                '',
                '6',
                '7',
                'Phong cách retro cổ điển đi hàng ngày',
                'Trắng Xanh',
                'WHT-BLU',
                '#1E3A8A',
                'blue',
                '38-42',
                '',
            ],
        ];

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);
        foreach ($sampleRows as $row) {
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
        $headers = ['Tên sản phẩm', 'Danh mục', 'Dòng thương hiệu', 'Giá gốc', 'Giá khuyến mãi', 'Bảo hành (tháng)', 'Đổi size (ngày)', 'Mô tả', 'Tên màu', 'Mã màu', 'Mã hex', 'Nhóm màu', 'Dải size', 'Giá riêng'];
        $rowsData = [
            $headers,
            ['Giày Chạy Bộ Cena Pegasus 40', 'Giày Nam', 'Pegasus', '1850000', '1650000', '6', '7', 'Đệm khí êm ái', 'Đen Trắng', 'BLK', '#000000', 'black', '39-43', ''],
            ['Giày Chạy Bộ Cena Pegasus 40', 'Giày Nam', 'Pegasus', '1850000', '1650000', '6', '7', 'Đệm khí êm ái', 'Trắng Cam', 'WHT', '#FFFFFF', 'white', '40, 40.5, 41, 42', ''],
            ['Giày Sneaker Cena Air Court Classic', 'Giày Unisex', 'Court', '1200000', '', '6', '7', 'Phong cách retro', 'Trắng Xanh', 'WHT-BLU', '#1E3A8A', 'blue', '38-42', ''],
        ];

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

            if (isset($found['name'], $found['category'], $found['base_price'], $found['color_name'], $found['style_color_code'], $found['sizes'])) {
                return [$index, $found];
            }
        }

        return [null, []];
    }
}
