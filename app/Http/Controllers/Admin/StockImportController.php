<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StockImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StockImportRequest;
use App\Models\ProductVariant;
use App\Models\StockImport;
use App\Models\Supplier;
use App\Services\StockImportService;
use App\Services\StockImportSpreadsheet;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockImportController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', 'in:DRAFT,POSTED,CANCELLED']]);
        $imports = StockImport::when($data['search'] ?? null, fn ($q, $s) => $q->where('import_code', 'like', '%'.$s.'%'))->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.inventory.imports.index', compact('imports'));
    }

    public function create(): View
    {
        return $this->form(new StockImport);
    }

    public function edit(StockImport $stock_import): View
    {
        abort_unless($stock_import->status === StockImportStatus::Draft, 409);

        return $this->form($stock_import);
    }

    private function form(StockImport $import): View
    {
        return view('admin.inventory.imports.form', ['import' => $import, 'commandKey' => (string) Str::uuid(), 'suppliers' => Supplier::orderBy('name')->get(),
            'variants' => ProductVariant::with('color.product')->orderBy('sku')->get(),
            'items' => $import->exists ? $import->items()->orderBy('variant_id')->get()->map(fn ($i) => ['variant_id' => $i->variant_id, 'quantity' => $i->quantity, 'unit_cost_amount' => $i->unit_cost_amount])->all() : [['variant_id' => '', 'quantity' => 1, 'unit_cost_amount' => '']]]);
    }

    /**
     * Reads an uploaded .xlsx/.csv and sends its lines back to the new-draft form for review; nothing is saved
     * until the user submits that form. Rows that cannot be used are listed under the upload box.
     */
    public function spreadsheet(Request $request, StockImportSpreadsheet $spreadsheet): RedirectResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'max:2048', 'extensions:xlsx,csv']], [
            'file.required' => 'Chọn file Excel (.xlsx) hoặc .csv để nhập.',
            'file.max' => 'File tối đa 2 MB.',
            'file.extensions' => 'Chỉ nhận file .xlsx hoặc .csv. Với file .xls cũ, hãy mở bằng Excel và lưu lại thành .xlsx.',
        ]);
        $file = $data['file'];
        $result = $spreadsheet->parse((string) $file->getRealPath(), $file->getClientOriginalExtension());

        $redirect = to_route('admin.stock-imports.create')->withInput($result['items'] === [] ? [] : ['items' => $result['items']]);
        if ($result['errors'] !== []) {
            $redirect->withErrors($result['errors'], 'spreadsheet');
        }
        if ($result['items'] !== []) {
            $redirect->with('status', 'Đã đọc '.count($result['items']).' dòng từ '.$file->getClientOriginalName().'. Chọn nhà cung cấp, kiểm tra lại rồi bấm “Lưu phiếu nháp”.');
        }

        return $redirect;
    }

    public function template(Request $request): BinaryFileResponse|Response
    {
        $format = strtolower((string) $request->query('format', 'csv'));
        $sampleSku1 = ProductVariant::query()->value('sku') ?? 'CENA-PEG40-BLK-40';
        $sampleSku2 = ProductVariant::query()->skip(1)->value('sku') ?? 'CENA-PEG40-BLK-41';

        if ($format === 'xlsx') {
            $tempPath = tempnam(sys_get_temp_dir(), 'cena_stock_tmpl_').'.xlsx';
            $zip = new \ZipArchive;
            $zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>';
            $zip->addFromString('[Content_Types].xml', $contentTypes);

            $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
            $zip->addFromString('_rels/.rels', $rels);

            $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="NhapKho" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';
            $zip->addFromString('xl/workbook.xml', $workbook);

            $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>';
            $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

            $rowsData = [
                ['SKU', 'Số lượng', 'Giá nhập'],
                [$sampleSku1, '10', '950000'],
                [$sampleSku2, '15', '950000'],
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

            return response()->download($tempPath, 'mau_nhap_kho.xlsx')->deleteFileAfterSend();
        }

        $bom = "\xEF\xBB\xBF";
        $csv = $bom."SKU,Số lượng,Giá nhập\n{$sampleSku1},10,950000\n{$sampleSku2},15,950000\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="mau_nhap_kho.csv"',
        ]);
    }

    public function show(StockImport $stock_import): View
    {
        return view('admin.inventory.imports.show', ['import' => $stock_import->load(['items', 'createdBy', 'postedBy'])]);
    }

    public function store(StockImportRequest $request, StockImportService $service): RedirectResponse
    {
        $import = $service->saveDraft($request->user(), null, $request->validated());

        return to_route('admin.stock-imports.show', $import)->with('status', 'Đã lưu phiếu nháp. Chưa thay đổi tồn kho.');
    }

    public function update(StockImportRequest $request, StockImport $stock_import, StockImportService $service): RedirectResponse
    {
        $import = $service->saveDraft($request->user(), $stock_import, $request->validated());

        return to_route('admin.stock-imports.show', $import)->with('status', 'Đã cập nhật phiếu nháp.');
    }

    public function post(Request $request, StockImport $stock_import, StockImportService $service): RedirectResponse
    {
        $service->post($request->user(), $stock_import);

        return to_route('admin.stock-imports.show', $stock_import)->with('status', 'Phiếu đã ghi sổ. Tồn kho được cập nhật đúng một lần.');
    }

    public function cancel(Request $request, StockImport $stock_import, StockImportService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $service->cancel($request->user(), $stock_import, $data['reason']);

        return to_route('admin.stock-imports.show', $stock_import)->with('status', 'Đã hủy nháp; tồn kho không đổi.');
    }
}
