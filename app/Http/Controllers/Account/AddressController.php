<?php

namespace App\Http\Controllers\Account;

use App\Enums\AdministrativeUnitLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AddressRequest;
use App\Models\Address;
use App\Models\AdministrativeUnit;
use App\Services\AddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function __construct(private readonly AddressService $addresses) {}

    public function index(Request $request): View
    {
        $currentVersion = $this->addresses->currentDatasetVersion();
        $entries = $request->user()->addresses()->orderByDesc('is_default')->orderBy('id')->get()
            ->map(fn (Address $address): array => [
                'address' => [
                    'recipient_name' => $address->recipient_name,
                    'phone' => $address->phone,
                    'street_address' => $address->street_address,
                    'ward_name_snapshot' => $address->ward_name_snapshot,
                    'province_name_snapshot' => $address->province_name_snapshot,
                    'is_default' => $address->is_default,
                    'outdated' => $address->dataset_version !== $currentVersion,
                ],
                'editUrl' => route('account.addresses.edit', $address->id),
                'deleteUrl' => route('account.addresses.destroy', $address->id),
                'setDefaultUrl' => route('account.addresses.default', $address->id),
            ]);

        return view('account.addresses.index', ['addresses' => $entries->all()]);
    }

    public function create(): View
    {
        return view('account.addresses.form', [
            'address' => null,
            'provinces' => $this->provinceOptions(),
        ]);
    }

    public function store(AddressRequest $request): RedirectResponse
    {
        $address = $this->addresses->create($request->user(), $request->address());
        if ($request->input('return_to') === 'checkout') {
            return redirect()->route('checkout.show', ['address' => $address->id])->with('status', 'Đã thêm địa chỉ.');
        }

        return redirect()->route('account.addresses.index')->with('status', 'Đã thêm địa chỉ.');
    }

    public function edit(Request $request, int $address): View
    {
        $model = $request->user()->addresses()->findOrFail($address);

        return view('account.addresses.form', [
            'address' => $model,
            'provinces' => $this->provinceOptions(),
        ]);
    }

    public function update(AddressRequest $request, int $address): RedirectResponse
    {
        $this->addresses->update($request->user(), $address, $request->address());

        return redirect()->route('account.addresses.index')->with('status', 'Đã cập nhật địa chỉ.');
    }

    public function destroy(Request $request, int $address): RedirectResponse
    {
        $this->addresses->delete($request->user(), $address);

        return redirect()->route('account.addresses.index')->with('status', 'Đã xóa địa chỉ.');
    }

    public function makeDefault(Request $request, int $address): RedirectResponse
    {
        $this->addresses->setDefault($request->user(), $address);

        return redirect()->route('account.addresses.index')->with('status', 'Đã đặt làm địa chỉ mặc định.');
    }

    /** @return list<array{code: string, name: string, dataset_version: string}> */
    private function provinceOptions(): array
    {
        return AdministrativeUnit::query()
            ->where('dataset_version', $this->addresses->currentDatasetVersion())
            ->where('is_active', true)
            ->where('level', AdministrativeUnitLevel::Province)
            ->orderBy('name')
            ->get(['code', 'name', 'dataset_version'])
            ->map(fn (AdministrativeUnit $unit): array => ['code' => $unit->code, 'name' => $unit->name, 'dataset_version' => $unit->dataset_version])
            ->all();
    }
}
