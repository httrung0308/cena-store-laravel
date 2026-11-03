<?php

namespace Tests\Feature;

use App\Models\ProductVariant;
use App\Models\StockImport;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class StockImportSpreadsheetTest extends TestCase
{
    use DatabaseTransactions;

    private User $staff;

    /** @var array<string, ProductVariant> */
    private array $variants;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->staff()->create();
        foreach (['XLS-RUN-40', 'XLS-RUN-41', 'XLS-RUN-42'] as $sku) {
            $this->variants[$sku] = ProductVariant::factory()->create(['sku' => $sku]);
        }
    }

    public function test_excel_file_fills_the_draft_form_without_saving_anything(): void
    {
        // Columns in another order, an extra column, rich-text header, numbers stored as numbers and an untouched row.
        $file = $this->xlsx([
            ['Ghi chú', ['rich' => ['Giá nhập', '/đôi']], 'Số lượng', 'SKU'],
            ['lô tháng 11', 1050000, 12, 'XLS-RUN-40'],
            ['', '', '', 'XLS-RUN-41'],
            ['', '980000', '6.0', 'XLS-RUN-42'],
        ]);

        $this->actingAs($this->staff)->post(route('admin.stock-imports.spreadsheet'), ['file' => $file])
            ->assertRedirect(route('admin.stock-imports.create'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'Đã đọc 2 dòng'))
            ->assertSessionHasInput('items', [
                ['variant_id' => (string) $this->variants['XLS-RUN-40']->id, 'quantity' => '12', 'unit_cost_amount' => '1050000'],
                ['variant_id' => (string) $this->variants['XLS-RUN-42']->id, 'quantity' => '6', 'unit_cost_amount' => '980000'],
            ]);
        $this->assertSame(0, StockImport::query()->count());
        $this->assertSame(0, (int) ProductVariant::query()->sum('stock_qty'));
    }

    public function test_csv_rows_with_problems_are_listed_and_the_good_rows_are_kept(): void
    {
        $csv = "\xEF\xBB\xBFSKU;Sản phẩm;Số lượng;Giá nhập/đôi (₫)\n"
            ."XLS-RUN-40;Runner;5;1.050.000 ₫\n"
            ."NOPE-1;?;2;100000\n"
            ."XLS-RUN-40;Runner;1;1050000\n"
            ."XLS-RUN-41;Runner;1,5;900000\n"
            .";Runner;3;900000\n"
            ."XLS-RUN-42;Runner;4;-5\n";

        $this->actingAs($this->staff)->post(route('admin.stock-imports.spreadsheet'), ['file' => UploadedFile::fake()->createWithContent('nhap.csv', $csv)])
            ->assertRedirect(route('admin.stock-imports.create'))
            ->assertSessionHasInput('items', [['variant_id' => (string) $this->variants['XLS-RUN-40']->id, 'quantity' => '5', 'unit_cost_amount' => '1050000']])
            ->assertSessionHasErrorsIn('spreadsheet', [0, 1, 2, 3, 4]);
        $errors = session('errors')->getBag('spreadsheet')->all();
        $this->assertSame([
            'Dòng 6: thiếu SKU.',
            'Dòng 3: không có SKU "NOPE-1".',
            'Dòng 4: SKU XLS-RUN-40 đã có ở dòng 2.',
            'Dòng 5: số lượng "1,5" phải là số nguyên từ 1 trở lên.',
            'Dòng 7: giá nhập "-5" phải là số tiền không âm, ví dụ 1050000.',
        ], $errors);

        $this->followingRedirects()->post(route('admin.stock-imports.spreadsheet'), ['file' => UploadedFile::fake()->createWithContent('nhap.csv', $csv)])->assertOk()
            ->assertSee('Nhập nhanh từ file Excel')->assertSee('data-spreadsheet-errors', false)->assertSee('Dòng 3: không có SKU &quot;NOPE-1&quot;.', false);
    }

    public function test_unreadable_or_unsupported_files_are_refused_with_a_clear_message(): void
    {
        $this->actingAs($this->staff);
        $this->post(route('admin.stock-imports.spreadsheet'), ['file' => UploadedFile::fake()->createWithContent('cu.xls', 'old')])
            ->assertSessionHasErrors(['file' => 'Chỉ nhận file .xlsx hoặc .csv. Với file .xls cũ, hãy mở bằng Excel và lưu lại thành .xlsx.']);
        $upload = fn (string $name, string $content) => $this->followingRedirects()
            ->post(route('admin.stock-imports.spreadsheet'), ['file' => UploadedFile::fake()->createWithContent($name, $content)])->assertOk();
        $upload('hong.xlsx', 'not a zip')->assertSee('File không phải bảng tính Excel (.xlsx) hợp lệ.');
        $upload('lech.csv', "Mã;Tên\nA;B\n")->assertSee('Không tìm thấy dòng tiêu đề có đủ 3 cột SKU, Số lượng và Giá nhập.');
        $upload('dai.csv', "SKU,Số lượng,Giá nhập\n".str_repeat("XLS-RUN-40,1,1000\n", 101))
            ->assertSee('File có 101 dòng, mỗi phiếu nhập tối đa 100 dòng. Hãy tách thành nhiều file.');
        $this->assertSame(0, StockImport::query()->count());

        $this->actingAs(User::factory()->customer()->create());
        $this->post(route('admin.stock-imports.spreadsheet'), ['file' => UploadedFile::fake()->createWithContent('x.csv', 'a')])->assertForbidden();
    }

    /**
     * Builds an .xlsx the way Excel stores it: shared strings (one rich-text), numeric cells and an empty row.
     *
     * @param  list<list<string|int|array{rich: list<string>}>>  $rows
     */
    private function xlsx(array $rows): UploadedFile
    {
        $shared = [];
        $sheet = '';
        foreach ($rows as $r => $row) {
            $sheet .= '<row r="'.($r + 1).'">';
            foreach ($row as $c => $value) {
                $ref = chr(65 + $c).($r + 1);
                if ($value === '') {
                    continue;
                }
                if (is_int($value)) {
                    $sheet .= '<c r="'.$ref.'"><v>'.$value.'</v></c>';

                    continue;
                }
                $shared[] = is_array($value)
                    ? '<si>'.implode('', array_map(fn (string $run) => '<r><t>'.htmlspecialchars($run).'</t></r>', $value['rich'])).'</si>'
                    : '<si><t>'.htmlspecialchars($value).'</t></si>';
                $sheet .= '<c r="'.$ref.'" t="s"><v>'.(count($shared) - 1).'</v></c>';
            }
            $sheet .= '</row>';
        }
        $path = tempnam(sys_get_temp_dir(), 'xlsx-test');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $ns = 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
        $zip->addFromString('xl/workbook.xml', '<workbook '.$ns.' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Nhập" sheetId="1" r:id="rId7"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId7" Type="worksheet" Target="worksheets/data.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<sst '.$ns.'>'.implode('', $shared).'</sst>');
        $zip->addFromString('xl/worksheets/data.xml', '<worksheet '.$ns.'><sheetData>'.$sheet.'</sheetData></worksheet>');
        $zip->close();

        return new UploadedFile($path, 'nhap-kho.xlsx', null, null, true);
    }
}
