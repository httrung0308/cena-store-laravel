@props(['address' => [], 'editUrl' => null, 'deleteUrl' => null, 'setDefaultUrl' => null])
@php($address = is_array($address) ? $address : [])
<article {{ $attributes->class(['rounded-2xl border-2 border-zinc-200 p-6 transition hover:border-zinc-300']) }}>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1 break-words">
            <h3 class="text-xl font-semibold">{{ $address['recipient_name'] ?? 'Địa chỉ' }}</h3>
            <p class="text-lg text-zinc-600">{{ $address['phone'] ?? '' }}</p>
            <p class="mt-2 text-base">{{ $address['street_address'] ?? '' }}, {{ $address['ward_name_snapshot'] ?? '' }}, {{ $address['province_name_snapshot'] ?? '' }}</p>
            @if (($address['is_default'] ?? false) === true)
                <span class="mt-3 inline-block rounded-lg bg-green-100 px-4 py-2 text-sm font-semibold text-green-600">Mặc định</span>
            @endif
            @if (($address['outdated'] ?? false) === true)
                <p role="status" class="mt-3 text-xs text-amber-700">Địa chỉ được lưu theo danh mục địa giới cũ. Vui lòng sửa và chọn lại tỉnh / phường.</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-3">
            @if ($setDefaultUrl && ! ($address['is_default'] ?? false))<form action="{{ $setDefaultUrl }}" method="POST">@csrf @method('PATCH')<button type="submit" class="rounded-xl border border-green-200 bg-green-50 px-5 py-2 text-sm font-semibold text-green-700 transition hover:bg-green-100">Đặt mặc định</button></form>@endif
            @if ($editUrl)<a href="{{ $editUrl }}" class="rounded-xl border border-blue-200 bg-blue-50 px-5 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-100">Sửa</a>@endif
            @if ($deleteUrl)<form action="{{ $deleteUrl }}" method="POST">@csrf @method('DELETE')<button type="submit" class="rounded-xl border border-red-200 bg-red-50 px-5 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100">Xóa</button></form>@endif
        </div>
    </div>
</article>
