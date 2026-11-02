<?php

namespace App\Services;

use App\Enums\AdministrativeUnitLevel;
use App\Models\Address;
use App\Models\AdministrativeUnit;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Address book rules (WF-04). Every write locks the owner's user row first, so concurrent
 * "set default" requests serialise and the user keeps at most one default address
 * (also enforced by the default_owner_id unique column).
 */
final class AddressService
{
    public function __construct(private readonly BusinessClock $clock) {}

    /**
     * @param  array{recipient_name: string, phone: string, province_code: string, ward_code: string, street_address: string, is_default: bool}  $data
     */
    public function create(User $owner, array $data): Address
    {
        return DB::transaction(function () use ($owner, $data): Address {
            $this->lockOwner($owner);
            [$province, $ward] = $this->resolveLocation($data['province_code'], $data['ward_code']);
            $isFirst = ! Address::query()->where('user_id', $owner->id)->exists();
            $makeDefault = $data['is_default'] || $isFirst;
            if ($makeDefault) {
                $this->clearDefault($owner);
            }

            $address = new Address;
            $address->user_id = $owner->id;
            $this->fill($address, $data, $province, $ward);
            $address->is_default = $makeDefault;
            $address->created_at = $this->clock->now();
            $address->updated_at = $this->clock->now();
            $address->save();

            return $address;
        });
    }

    /**
     * Updates the address and re-takes the location snapshot. Unticking "default" on the current default
     * is ignored: the user always keeps one default while they have addresses.
     *
     * @param  array{recipient_name: string, phone: string, province_code: string, ward_code: string, street_address: string, is_default: bool}  $data
     */
    public function update(User $owner, int $addressId, array $data): Address
    {
        return DB::transaction(function () use ($owner, $addressId, $data): Address {
            $this->lockOwner($owner);
            $address = $this->ownedForUpdate($owner, $addressId);
            [$province, $ward] = $this->resolveLocation($data['province_code'], $data['ward_code']);
            if ($data['is_default'] && ! $address->is_default) {
                $this->clearDefault($owner);
                $address->is_default = true;
            }
            $this->fill($address, $data, $province, $ward);
            $address->updated_at = $this->clock->now();
            $address->save();

            return $address;
        });
    }

    public function setDefault(User $owner, int $addressId): Address
    {
        return DB::transaction(function () use ($owner, $addressId): Address {
            $this->lockOwner($owner);
            $address = $this->ownedForUpdate($owner, $addressId);
            if (! $address->is_default) {
                $this->clearDefault($owner);
                $address->is_default = true;
                $address->updated_at = $this->clock->now();
                $address->save();
            }

            return $address;
        });
    }

    /**
     * Soft-deletes the address. A deleted default hands the flag to the remaining address with the smallest id.
     */
    public function delete(User $owner, int $addressId): void
    {
        DB::transaction(function () use ($owner, $addressId): void {
            $this->lockOwner($owner);
            $address = $this->ownedForUpdate($owner, $addressId);
            $wasDefault = $address->is_default;
            $address->is_default = false;
            $address->updated_at = $this->clock->now();
            $address->save();
            $address->delete();

            if ($wasDefault) {
                $next = Address::query()->where('user_id', $owner->id)->orderBy('id')->lockForUpdate()->first();
                if ($next !== null) {
                    $next->is_default = true;
                    $next->updated_at = $this->clock->now();
                    $next->save();
                }
            }
        });
    }

    public function currentDatasetVersion(): string
    {
        return (string) config('store.shipping.dataset_version');
    }

    private function lockOwner(User $owner): void
    {
        User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
    }

    /** Another user's (or a deleted) address is reported as not found, never as forbidden. */
    private function ownedForUpdate(User $owner, int $addressId): Address
    {
        return Address::query()->where('user_id', $owner->id)->whereKey($addressId)->lockForUpdate()->firstOrFail();
    }

    private function clearDefault(User $owner): void
    {
        Address::query()->where('user_id', $owner->id)->where('is_default', true)
            ->update(['is_default' => false, 'updated_at' => $this->clock->now()]);
    }

    /**
     * @return array{AdministrativeUnit, AdministrativeUnit}
     */
    private function resolveLocation(string $provinceCode, string $wardCode): array
    {
        $version = $this->currentDatasetVersion();
        $province = AdministrativeUnit::query()->where('dataset_version', $version)->where('is_active', true)
            ->where('level', AdministrativeUnitLevel::Province)->where('code', $provinceCode)->first();
        if ($province === null) {
            throw ValidationException::withMessages(['province_code' => 'Tỉnh / thành phố không có trong danh mục hiện hành.']);
        }
        $ward = AdministrativeUnit::query()->where('dataset_version', $version)->where('is_active', true)
            ->where('level', AdministrativeUnitLevel::Ward)->where('code', $wardCode)->where('parent_id', $province->id)->first();
        if ($ward === null) {
            throw ValidationException::withMessages(['ward_code' => 'Phường / xã không thuộc tỉnh / thành phố đã chọn.']);
        }

        return [$province, $ward];
    }

    /**
     * @param  array{recipient_name: string, phone: string, street_address: string}  $data
     */
    private function fill(Address $address, array $data, AdministrativeUnit $province, AdministrativeUnit $ward): void
    {
        $address->recipient_name = $data['recipient_name'];
        $address->phone = $data['phone'];
        $address->street_address = $data['street_address'];
        $address->province_id = $province->id;
        $address->ward_id = $ward->id;
        $address->province_code_snapshot = $province->code;
        $address->province_name_snapshot = $province->name;
        $address->ward_code_snapshot = $ward->code;
        $address->ward_name_snapshot = $ward->name;
        $address->dataset_version = $province->dataset_version;
        $address->legacy_district_text = null;
    }
}
