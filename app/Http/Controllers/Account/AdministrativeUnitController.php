<?php

namespace App\Http\Controllers\Account;

use App\Enums\AdministrativeUnitLevel;
use App\Http\Controllers\Controller;
use App\Models\AdministrativeUnit;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public lookup for the address picker. Returns only the current snapshot (store.shipping.dataset_version)
 * and only public fields; codes stay strings so leading zeros survive.
 */
class AdministrativeUnitController extends Controller
{
    public function provinces(Request $request): JsonResponse
    {
        $provinces = $this->currentSnapshot()
            ->where('level', AdministrativeUnitLevel::Province)
            ->orderBy('name')
            ->get(['code', 'name', 'dataset_version'])
            ->map(fn (AdministrativeUnit $unit): array => [
                'code' => $unit->code,
                'name' => $unit->name,
                'dataset_version' => $unit->dataset_version,
            ]);

        return ApiResponse::success($request, $provinces->values());
    }

    public function wards(Request $request, string $provinceCode): JsonResponse
    {
        abort_unless(preg_match('/\A[0-9]{2}\z/', $provinceCode) === 1, 404);
        $province = $this->currentSnapshot()
            ->where('level', AdministrativeUnitLevel::Province)
            ->where('code', $provinceCode)
            ->firstOrFail(['id', 'code']);

        $wards = $this->currentSnapshot()
            ->where('level', AdministrativeUnitLevel::Ward)
            ->where('parent_id', $province->id)
            ->orderBy('name')
            ->get(['code', 'name', 'dataset_version'])
            ->map(fn (AdministrativeUnit $unit): array => [
                'code' => $unit->code,
                'name' => $unit->name,
                'parent_code' => $province->code,
                'dataset_version' => $unit->dataset_version,
            ]);

        return ApiResponse::success($request, $wards->values());
    }

    /** @return Builder<AdministrativeUnit> */
    private function currentSnapshot(): Builder
    {
        return AdministrativeUnit::query()
            ->where('dataset_version', (string) config('store.shipping.dataset_version'))
            ->where('is_active', true);
    }
}
