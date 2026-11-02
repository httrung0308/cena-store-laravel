<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupplierRequest;
use App\Models\Supplier;
use App\Services\ProductManagementTaxonomyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:150'], 'active' => ['nullable', 'in:0,1']]);
        $query = Supplier::query()->when($data['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))->when(isset($data['active']), fn ($q) => $q->where('is_active', $data['active']));
        $paginator = $query->orderBy('id')->paginate(15)->withQueryString();

        return view('admin.suppliers.index', ['paginator' => $paginator, 'search' => $data['search'] ?? '']);
    }

    public function create(): View
    {
        return $this->form(new Supplier);
    }

    public function edit(Supplier $supplier): View
    {
        return $this->form($supplier);
    }

    private function form(Supplier $record): View
    {
        return view('admin.suppliers.form', ['record' => $record]);
    }

    public function store(SupplierRequest $request, ProductManagementTaxonomyService $service): RedirectResponse
    {
        $service->saveSupplier($request->user(), null, $request->validated());

        return to_route('admin.suppliers.index')->with('status', 'Đã tạo bản ghi.');
    }

    public function update(SupplierRequest $request, Supplier $supplier, ProductManagementTaxonomyService $service): RedirectResponse
    {
        $service->saveSupplier($request->user(), $supplier, $request->validated());

        return to_route('admin.suppliers.index')->with('status', 'Đã lưu thay đổi.');
    }

    public function deactivate(Request $request, Supplier $supplier, ProductManagementTaxonomyService $service): RedirectResponse
    {
        $service->deactivate($request->user(), $supplier);

        return to_route('admin.suppliers.index')->with('status', 'Đã ngừng hiển thị; lịch sử được giữ nguyên.');
    }
}
