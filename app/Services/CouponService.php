<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Enums\RoleSlug;
use App\Models\Coupon;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CouponService
{
    /** @param array{code: string, name: string, type: string, value: int, min_order_amount: int, max_discount_amount: ?int, usage_limit: ?int, per_user_limit: int, starts_at: ?string, expires_at: ?string, is_active: bool} $data */
    public function save(User $admin, ?Coupon $coupon, array $data): Coupon
    {
        abort_unless($admin->hasRole(RoleSlug::Admin), 403);

        return DB::transaction(function () use ($coupon, $data): Coupon {
            if ($coupon !== null) {
                $coupon = Coupon::query()->whereKey($coupon->id)->lockForUpdate()->firstOrFail();
            } else {
                $coupon = new Coupon;
            }
            $coupon->code = mb_strtoupper(trim($data['code']));
            $coupon->name = trim($data['name']);
            $coupon->type = CouponType::from($data['type']);
            $coupon->value = $data['value'];
            $coupon->min_order_amount = $data['min_order_amount'];
            $coupon->max_discount_amount = $data['max_discount_amount'];
            $coupon->usage_limit = $data['usage_limit'];
            $coupon->per_user_limit = $data['per_user_limit'];
            $coupon->starts_at = $data['starts_at'] === null ? null : CarbonImmutable::parse($data['starts_at'], (string) config('store.business_timezone'))->utc();
            $coupon->expires_at = $data['expires_at'] === null ? null : CarbonImmutable::parse($data['expires_at'], (string) config('store.business_timezone'))->utc();
            $coupon->is_active = $data['is_active'];
            $coupon->save();

            return $coupon;
        });
    }

    public function setActive(User $admin, Coupon $coupon, bool $active): Coupon
    {
        abort_unless($admin->hasRole(RoleSlug::Admin), 403);

        return DB::transaction(function () use ($coupon, $active): Coupon {
            $locked = Coupon::query()->whereKey($coupon->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['is_active' => $active])->save();

            return $locked->refresh();
        });
    }

    public function deactivate(User $admin, Coupon $coupon): void
    {
        $this->setActive($admin, $coupon, false);
    }
}
