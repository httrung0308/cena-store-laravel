<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Product;
use App\Services\ProductManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:150']]);
        $products = Product::with(['category', 'colors' => fn ($q) => $q->orderBy('display_order')->orderBy('id'), 'colors.thumbnail'])->withCount('variants')->withSum('variants as total_stock', 'stock_qty')->when($data['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.products.index', compact('products'));
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

        $classifications = Classification::query()
            ->orderBy('classification_group')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $selectedClassificationIds = $product->exists
            ? $product->classifications()->pluck('classifications.id')->all()
            : [];

        return view('admin.products.form', [
            'product' => $product,
            'categories' => Category::orderBy('display_order')->get(['id', 'name'])->toArray(),
            'colors' => $colors,
            'classifications' => $classifications,
            'selectedClassificationIds' => $selectedClassificationIds,
        ]);
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

    public function activate(Request $request, Product $product, ProductManagementService $service): RedirectResponse|JsonResponse
    {
        $updated = $service->setActive($request->user(), $product, true);
        $message = 'Đã mở bán lại sản phẩm.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'is_active' => $updated->is_active]);
        }

        return back(fallback: route('admin.products.index'))->with('status', $message);
    }

    public function deactivate(Request $request, Product $product, ProductManagementService $service): RedirectResponse|JsonResponse
    {
        $updated = $service->setActive($request->user(), $product, false);
        $message = 'Đã ngừng bán; chứng từ và lịch sử vẫn được giữ.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'is_active' => $updated->is_active]);
        }

        return back(fallback: route('admin.products.index'))->with('status', $message);
    }
}
