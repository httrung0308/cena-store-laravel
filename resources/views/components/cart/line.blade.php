@props(['line' => [], 'updateUrl' => null, 'removeUrl' => null, 'canUpdate' => false, 'canRemove' => false])
@php
    $line = is_array($line) ? $line : [];
    $lineTotal = $line['line_total'] ?? null;
    $unitPrice = $line['unit_price'] ?? null;
@endphp
<article {{ $attributes->class(['flex flex-col justify-between gap-3 border-b border-zinc-200 py-4 sm:flex-row']) }}>
    <div class="space-y-1"><h3 class="font-semibold">{{ $line['name'] ?? 'Sản phẩm' }}</h3>
        <p class="text-sm text-zinc-600">{{ $line['color_name'] ?? '' }} · Size {{ $line['size_value'] ?? '' }} · SL {{ $line['quantity'] ?? '' }}</p>
        @if (($line['unavailable'] ?? false) === true)<p role="status" class="text-sm text-red-500">Sản phẩm hiện không khả dụng.</p>@endif
    </div>
    <div class="space-y-1 text-sm"><p>Đơn giá: {{ is_int($unitPrice) ? number_format($unitPrice, 0, ',', '.').' ₫' : 'Chưa có giá' }}</p>
        <p class="font-semibold">Thành tiền: {{ is_int($lineTotal) ? number_format($lineTotal, 0, ',', '.').' ₫' : 'Chưa có giá' }}</p>
        @if ($canUpdate && $updateUrl)<form action="{{ $updateUrl }}" method="POST">@csrf @method('PATCH')<button type="submit" class="underline">Cập nhật</button></form>@endif
        @if ($canRemove && $removeUrl)<form action="{{ $removeUrl }}" method="POST">@csrf @method('DELETE')<button type="submit" class="underline">Xóa</button></form>@endif
    </div>
</article>
