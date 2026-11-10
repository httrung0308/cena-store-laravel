<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\CouponService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CouponController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:50']]);
        $coupons = Coupon::query()->withCount(['couponRedemptions as active_redemptions_count' => fn ($query) => $query->whereIn('status', ['RESERVED', 'CONSUMED'])])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($inner) => $inner->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))
            ->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.coupons.index', ['coupons' => $coupons, 'search' => $filters['search'] ?? '']);
    }

    public function create(): View
    {
        return view('admin.coupons.form', ['coupon' => new Coupon, 'types' => CouponType::cases()]);
    }

    public function edit(Coupon $coupon): View
    {
        return view('admin.coupons.form', ['coupon' => $coupon, 'types' => CouponType::cases()]);
    }

    public function store(Request $request, CouponService $coupons): RedirectResponse
    {
        $coupons->save($request->user(), null, $this->validated($request));

        return to_route('admin.coupons.index')->with('status', 'Đã tạo mã coupon.');
    }

    public function update(Request $request, Coupon $coupon, CouponService $coupons): RedirectResponse
    {
        $coupons->save($request->user(), $coupon, $this->validated($request));

        return to_route('admin.coupons.index')->with('status', 'Đã lưu coupon.');
    }

    public function deactivate(Request $request, Coupon $coupon, CouponService $coupons): RedirectResponse
    {
        $coupons->deactivate($request->user(), $coupon);

        return to_route('admin.coupons.index')->with('status', 'Đã tắt coupon.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $type = (string) $request->input('type');
        $boundCoupon = $request->route('coupon');
        $ignoreId = $boundCoupon instanceof Coupon ? $boundCoupon->id : null;
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/D', Rule::unique('coupons', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['required', 'integer', 'min:'.($type === CouponType::FreeShipping->value ? '0' : '1'), 'max:'.($type === CouponType::Percentage->value ? '100' : ($type === CouponType::FreeShipping->value ? '0' : '1000000000'))],
            'min_order_amount' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'max_discount_amount' => ['exclude_unless:type,PERCENTAGE', 'nullable', 'integer', 'min:1', 'max:1000000000'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['required', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['max_discount_amount'] ??= null;
        $data['usage_limit'] ??= null;
        $data['starts_at'] ??= null;
        $data['expires_at'] ??= null;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
