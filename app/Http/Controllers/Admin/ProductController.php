<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductManagementService;
use App\Services\ProductSpreadsheetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function show(Request $request, Product $product): View
    {
        $product->load([
            'category.parent',
            'classifications' => fn ($q) => $q->orderBy('classification_group')->orderBy('sort_order')->orderBy('name'),
            'colors' => fn ($q) => $q->orderBy('display_order')->orderBy('id'),
            'colors.images' => fn ($q) => $q->orderByDesc('is_thumbnail')->orderBy('display_order')->orderBy('id'),
            'colors.variants' => fn ($q) => $q->orderBy('size_numeric')->orderBy('id'),
        ]);

        $canViewCost = $request->user()?->hasRole(RoleSlug::Admin) ?? false;

        $blockingReasons = [];
        if (! $product->is_active) {
            $blockingReasons[] = 'Sản phẩm đang ở trạng thái Ngừng bán.';
        }
        $categoryActive = (bool) $product->category?->is_active
            && ($product->category?->parent_id === null || (bool) $product->category?->parent?->is_active);
        if (! $categoryActive) {
            $blockingReasons[] = 'Danh mục (hoặc danh mục cha) đang bị ẩn.';
        }

        $hasSellableColor = $product->colors->contains(function ($color) {
            return $color->is_active
                && $color->images->isNotEmpty()
                && $color->variants->contains('is_active', true);
        });
        if (! $hasSellableColor) {
            $blockingReasons[] = 'Chưa có phối màu nào đang bán kèm ảnh và ít nhất một size đang mở bán.';
        }

        $isPubliclyVisible = $blockingReasons === [];

        $allVariants = $product->colors->flatMap->variants;
        $totals = [
            'colors' => $product->colors->count(),
            'variants' => $allVariants->count(),
            'stock' => (int) $allVariants->sum('stock_qty'),
            'reserved' => (int) $allVariants->sum('reserved_qty'),
            'quarantine' => (int) $allVariants->sum('quarantine_qty'),
        ];

        return view('admin.products.show', [
            'product' => $product,
            'canViewCost' => $canViewCost,
            'isPubliclyVisible' => $isPubliclyVisible,
            'blockingReasons' => $blockingReasons,
            'totals' => $totals,
        ]);
    }

    public function edit(Product $product): View
    {
        return $this->form($product);
    }

    public function duplicate(Product $product): View
    {
        $clone = new Product;
        $clone->name = $product->name.' (Bản sao)';

        $baseSlug = Str::slug($clone->name);
        $candidateSlug = $baseSlug;
        $counter = 1;
        while (Product::where('slug', $candidateSlug)->exists()) {
            $candidateSlug = sprintf('%s-%02d', $baseSlug, $counter++);
        }
        $clone->slug = $candidateSlug;
        $clone->brand_line = $product->brand_line;
        $clone->category_id = $product->category_id;
        $clone->description = $product->description;
        $clone->base_price = $product->base_price;
        $clone->promotion_price = $product->promotion_price;
        $clone->warranty_months = $product->warranty_months;
        $clone->exchange_days = $product->exchange_days;
        $clone->is_active = false;
        $clone->is_featured = false;

        $colors = $product->colors()->with(['variants', 'images'])->orderBy('display_order')->get()->map(function ($c) use ($clone) {
            return [
                'id' => null,
                'color_name' => $c->color_name,
                'primary_hex' => $c->primary_hex,
                'style_color_code' => $c->style_color_code,
                'color_key' => $c->color_key,
                'is_active' => $c->is_active,
                'variants' => $c->variants->map(function ($v) use ($clone, $c) {
                    return [
                        'id' => null,
                        'sku' => ProductManagementService::generateSku($clone->slug, $c->style_color_code, $v->size_value),
                        'size_value' => $v->size_value,
                        'price_override' => $v->price_override,
                        'is_active' => $v->is_active,
                    ];
                })->all(),
                'images' => $c->images->map(function ($i) {
                    return [
                        'id' => null,
                        'external_url' => $i->external_url,
                        'clone_source_path' => $i->storage_path,
                        'alt_text' => $i->alt_text,
                        'thumbnail' => $i->is_thumbnail,
                        'existingUrl' => $i->storage_path ? Storage::disk('public')->url($i->storage_path) : $i->external_url,
                    ];
                })->all(),
            ];
        })->all();

        $classifications = Classification::query()
            ->orderBy('classification_group')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $selectedClassificationIds = $product->classifications()->pluck('classifications.id')->all();

        return view('admin.products.form', [
            'product' => $clone,
            'categories' => $this->categoryOptions($clone->category_id),
            'colors' => $colors,
            'classifications' => $classifications,
            'selectedClassificationIds' => $selectedClassificationIds,
            'isDuplicate' => true,
            'originalProduct' => $product,
        ]);
    }

    public function searchVariants(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $variants = ProductVariant::query()
            ->with(['color.product', 'color.thumbnail'])
            ->where(function ($query) use ($q) {
                $query->where('sku', 'like', "%{$q}%")
                    ->orWhere('size_value', 'like', "%{$q}%")
                    ->orWhereHas('color', function ($cq) use ($q) {
                        $cq->where('color_name', 'like', "%{$q}%")
                            ->orWhere('style_color_code', 'like', "%{$q}%")
                            ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$q}%"));
                    });
            })
            ->limit(20)
            ->get()
            ->map(function (ProductVariant $v) {
                $product = $v->color?->product;
                $color = $v->color;
                $thumb = $color?->thumbnail;
                $thumbUrl = null;
                if ($thumb) {
                    $thumbUrl = $thumb->storage_path ? Storage::disk('public')->url($thumb->storage_path) : $thumb->external_url;
                }

                return [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'product_name' => $product?->name ?? 'Sản phẩm',
                    'color_name' => $color?->color_name ?? '',
                    'style_color_code' => $color?->style_color_code ?? '',
                    'size_value' => $v->size_value,
                    'price' => $v->price_override ?? $product?->base_price ?? 0,
                    'stock_qty' => $v->stock_qty,
                    'is_active' => $v->is_active,
                    'thumbnail_url' => $thumbUrl,
                    'display_label' => "{$v->sku} — {$product?->name} ({$color?->color_name} / Size {$v->size_value})",
                ];
            });

        return response()->json($variants);
    }

    public function template(Request $request): BinaryFileResponse|Response
    {
        $format = strtolower((string) $request->query('format', 'csv'));
        if ($format === 'xlsx') {
            $path = ProductSpreadsheetService::templateXlsx();

            return response()->download($path, 'mau_nhap_san_pham.xlsx')->deleteFileAfterSend();
        }

        $csv = ProductSpreadsheetService::templateCsv();

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="mau_nhap_san_pham.csv"',
        ]);
    }

    public function previewImport(Request $request, ProductSpreadsheetService $spreadsheet): View|JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv,txt'],
        ]);

        $file = $validated['file'];
        $extension = $file->getClientOriginalExtension();
        $res = $spreadsheet->parse($file->getRealPath(), $extension);

        if ($request->expectsJson()) {
            return response()->json($res);
        }

        if (! empty($res['errors'])) {
            return back()->withErrors(['import_errors' => $res['errors']])->withInput();
        }

        // Store preview in session for the confirmation step
        session()->flash('import_preview', $res['products']);

        return back()->with('import_preview', $res['products'])->with('status', 'Đã đọc thành công '.count($res['products']).' sản phẩm. Vui lòng kiểm tra lại trước khi lưu.');
    }

    public function storeImport(Request $request, ProductSpreadsheetService $spreadsheet, ProductManagementService $pmService): RedirectResponse
    {
        $productsJson = $request->input('products_json');
        if (! $productsJson) {
            return back()->withErrors(['import_errors' => ['Không tìm thấy dữ liệu import đã xem trước.']]);
        }

        $products = json_decode((string) $productsJson, true);
        if (! is_array($products) || empty($products)) {
            return back()->withErrors(['import_errors' => ['Dữ liệu import không hợp lệ.']]);
        }

        $result = $spreadsheet->import($request->user(), $products, $pmService);

        return to_route('admin.products.index')->with('status', "Đã import thành công {$result['created_count']} sản phẩm! Lưu ý: Sản phẩm import chưa có ảnh nên được đặt ở trạng thái Ngừng bán. Vui lòng bấm Sửa để bổ sung ảnh.");
    }

    private function form(Product $product): View
    {
        $documentedIds = app(ProductManagementService::class)->documentedVariantIds($product);
        $colors = $product->exists ? $product->colors()->with(['variants', 'images'])->orderBy('display_order')->get()->map(fn ($c) => [
            'id' => $c->id, 'color_name' => $c->color_name, 'primary_hex' => $c->primary_hex, 'style_color_code' => $c->style_color_code, 'color_key' => $c->color_key, 'is_active' => $c->is_active,
            'variants' => $c->variants->map(fn ($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'size_value' => $v->size_value,
                'price_override' => $v->price_override,
                'is_active' => $v->is_active,
                'documented' => in_array($v->id, $documentedIds, true),
            ])->all(),
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
            'categories' => $this->categoryOptions($product->category_id),
            'colors' => $colors,
            'classifications' => $classifications,
            'selectedClassificationIds' => $selectedClassificationIds,
            'isDuplicate' => false,
        ]);
    }

    /** @return list<array{id: int, name: string, label: string, parent_id: int|null, is_active: bool}> */
    private function categoryOptions(?int $selectedCategoryId = null): array
    {
        $all = Category::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'is_active']);

        $byId = $all->keyBy('id');

        return $all
            ->filter(fn (Category $cat) => $cat->is_active || $cat->id === $selectedCategoryId)
            ->map(function (Category $cat) use ($byId): array {
                $parent = $cat->parent_id ? $byId->get($cat->parent_id) : null;
                $label = $parent ? "{$parent->name} › {$cat->name}" : $cat->name;

                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'label' => $label,
                    'parent_id' => $cat->parent_id,
                    'is_active' => (bool) $cat->is_active,
                ];
            })
            ->values()
            ->all();
    }

    public function store(ProductRequest $request, ProductManagementService $service): RedirectResponse|JsonResponse
    {
        $product = $service->save($request->user(), null, $request->validated());
        $message = 'Đã tạo sản phẩm, màu, size và ảnh. Tồn kho bắt đầu từ 0.';

        if ($request->expectsJson()) {
            session()->flash('status', $message);

            return response()->json([
                'message' => $message,
                'redirect' => route('admin.products.edit', $product),
                'product_id' => $product->id,
            ], 201);
        }

        return to_route('admin.products.edit', $product)->with('status', $message);
    }

    public function update(ProductRequest $request, Product $product, ProductManagementService $service): RedirectResponse|JsonResponse
    {
        $service->save($request->user(), $product, $request->validated());
        $message = 'Đã lưu sản phẩm.';

        if ($request->expectsJson()) {
            session()->flash('status', $message);

            return response()->json([
                'message' => $message,
                'redirect' => route('admin.products.edit', $product),
                'product_id' => $product->id,
            ]);
        }

        return to_route('admin.products.edit', $product)->with('status', $message);
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
