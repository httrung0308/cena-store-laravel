<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GenderGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Services\ProductManagementTaxonomyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:150'], 'active' => ['nullable', 'in:0,1']]);
        $query = Category::query()->when($data['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))->when(isset($data['active']), fn ($q) => $q->where('is_active', $data['active']));
        $paginator = $query->orderBy('id')->paginate(15)->withQueryString();

        return view('admin.categories.index', ['paginator' => $paginator, 'search' => $data['search'] ?? '']);
    }

    public function create(): View
    {
        return $this->form(new Category);
    }

    public function edit(Category $category): View
    {
        return $this->form($category);
    }

    private function form(Category $record): View
    {
        return view('admin.categories.form', ['record' => $record, 'parents' => Category::whereNull('parent_id')->where('id', '!=', $record->id ?? 0)->orderBy('display_order')->get(['id', 'name'])->toArray(), 'genderOptions' => array_map(fn ($gender) => ['value' => $gender->value, 'label' => $gender->label()], GenderGroup::cases())]);
    }

    public function store(CategoryRequest $request, ProductManagementTaxonomyService $service): RedirectResponse
    {
        $service->saveCategory($request->user(), null, $request->validated());

        return to_route('admin.categories.index')->with('status', 'Đã tạo bản ghi.');
    }

    public function update(CategoryRequest $request, Category $category, ProductManagementTaxonomyService $service): RedirectResponse
    {
        $service->saveCategory($request->user(), $category, $request->validated());

        return to_route('admin.categories.index')->with('status', 'Đã lưu thay đổi.');
    }

    public function deactivate(Request $request, Category $category, ProductManagementTaxonomyService $service): RedirectResponse
    {
        $service->deactivate($request->user(), $category);

        return to_route('admin.categories.index')->with('status', 'Đã ngừng hiển thị; lịch sử được giữ nguyên.');
    }
}
