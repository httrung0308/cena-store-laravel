<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StockImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StockImportRequest;
use App\Models\ProductVariant;
use App\Models\StockImport;
use App\Models\Supplier;
use App\Services\StockImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
