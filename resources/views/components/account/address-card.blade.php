@props(['address' => [], 'editUrl' => null, 'deleteUrl' => null, 'setDefaultUrl' => null])
@php($address = is_array($address) ? $address : [])
<article {{ $attributes->class(['space-y-3 border border-zinc-200 p-5 text-sm']) }}>
    <div class="flex flex-wrap items-start justify-between gap-2"><h3 class="text-lg font-semibold">{{ $address['recipient_name'] ?? 'Địa chỉ' }}</h3>
        @if (($address['is_default'] ?? false) === true)<span class="border border-black px-2 py-1 text-xs">Mặc định</span>@endif
    </div>
    <p>{{ $address['phone'] ?? '' }}</p>
    <p>{{ $address['street_address'] ?? '' }}, {{ $address['ward_name_snapshot'] ?? '' }}, {{ $address['province_name_snapshot'] ?? '' }}</p>
    @if (($address['outdated'] ?? false) === true)
        <p role="status" class="text-xs text-amber-700">Địa chỉ được lưu theo danh mục địa giới cũ. Vui lòng sửa và chọn lại tỉnh / phường.</p>
    @endif
    <div class="flex flex-wrap gap-3">
        @if ($editUrl)<a href="{{ $editUrl }}" class="text-sm underline">Sửa</a>@endif
        @if ($deleteUrl)<form action="{{ $deleteUrl }}" method="POST">@csrf @method('DELETE')<button type="submit" class="underline">Xóa</button></form>@endif
        @if ($setDefaultUrl && ! ($address['is_default'] ?? false))<form action="{{ $setDefaultUrl }}" method="POST">@csrf @method('PATCH')<button type="submit" class="underline">Đặt mặc định</button></form>@endif
    </div>
</article>
