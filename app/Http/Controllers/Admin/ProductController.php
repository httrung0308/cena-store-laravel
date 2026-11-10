<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\InventoryHistoryQueryService;
use App\Services\ProductManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'deleted', 'all'])],
        ]);
        $query = match ($data['status'] ?? null) {
            'deleted' => Product::onlyTrashed(),
            'all' => Product::withTrashed(),
            default => Product::query(),
        };
        $products = $query->with(['category', 'colors' => fn ($q) => $q->orderBy('display_order')->orderBy('id'), 'colors.thumbnail'])
            ->withCount('variants')
            ->withSum('variants as total_stock', 'stock_qty')
            ->when(($data['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($data['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($data['search'] ?? null, fn ($q, $search) => $q->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')
                    ->orWhereHas('variants', fn ($variants) => $variants->withTrashed()->where('sku', 'like', '%'.$search.'%'));
            }))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.products.index', compact('products'));
    }

    public function show(Request $request, Product $product, InventoryHistoryQueryService $historyQueryService): View
    {
        $filters = $request->validate([
            'variant_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'action' => ['nullable', Rule::enum(InventoryAction::class)],
            'reference_type' => ['nullable', Rule::enum(InventoryReferenceType::class)],
            'reference_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $colors = $historyQueryService->productColorsWithVariants($product);
        $variants = $colors->flatMap(fn ($color) => $color->variants);
        $selectedVariant = null;

        if (isset($filters['variant_id'])) {
            $selectedVariant = $variants->firstWhere('id', (int) $filters['variant_id']);
            abort_if($selectedVariant === null, 404);
        }

        $movements = $selectedVariant === null ? null : $historyQueryService->movements($selectedVariant, $filters);
        $reconciliation = $selectedVariant === null ? null : $historyQueryService->reconciliation($selectedVariant);

        return view('admin.products.show', [
            'product' => $product,
            'colors' => $colors,
            'selectedVariant' => $selectedVariant,
            'movements' => $movements,
            'reconciliation' => $reconciliation,
            'actions' => InventoryAction::cases(),
            'referenceTypes' => InventoryReferenceType::cases(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Product);
    }

    public function edit(Product $product): View
    {
        return $this->form($product);
    }

    private function form(Product $product): View
    {
        $colors = $product->exists ? $product->colors()->with(['variants', 'images'])->orderBy('display_order')->get()->map(fn ($c) => [
            'id' => $c->id, 'color_name' => $c->color_name, 'primary_hex' => $c->primary_hex, 'style_color_code' => $c->style_color_code, 'color_key' => $c->color_key, 'is_active' => $c->is_active,
            'variants' => $c->variants->map(fn ($v) => ['id' => $v->id, 'sku' => $v->sku, 'size_value' => $v->size_value, 'price_override' => $v->price_override, 'is_active' => $v->is_active])->all(),
            'images' => $c->images->map(fn ($i) => ['id' => $i->id, 'external_url' => $i->external_url, 'alt_text' => $i->alt_text, 'thumbnail' => $i->is_thumbnail, 'existingUrl' => $i->storage_path ? Storage::disk('public')->url($i->storage_path) : $i->external_url])->all()])->all() : [];

        return view('admin.products.form', ['product' => $product, 'categories' => Category::orderBy('display_order')->get(['id', 'name'])->toArray(), 'colors' => $colors]);
    }

    public function store(ProductRequest $request, ProductManagementService $service): RedirectResponse
    {
        $product = $service->save($request->user(), null, $request->validated());

        return to_route('admin.products.edit', $product)->with('status', 'Đã tạo sản phẩm, màu, size và ảnh. Tồn kho bắt đầu từ0.');
    }

    public function update(ProductRequest $request, Product $product, ProductManagementService $service): RedirectResponse
    {
        $service->save($request->user(), $product, $request->validated());

        return to_route('admin.products.edit', $product)->with('status', 'Đã lưu sản phẩm.');
    }

    public function deactivate(Request $request, Product $product, ProductManagementService $service): RedirectResponse
    {
        $service->deactivate($request->user(), $product);

        return to_route('admin.products.index')->with('status', 'Đã ngừng bán; chứng từ và lịch sử vẫn được giữ.');
    }
}
