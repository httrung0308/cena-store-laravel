@props(['address' => [], 'editUrl' => null, 'deleteUrl' => null, 'setDefaultUrl' => null])
@php($address = is_array($address) ? $address : [])
<article {{ $attributes->class(['rounded-2xl border border-border bg-surface-card p-6 shadow-card transition-colors duration-150 hover:border-accent/40']) }}>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1 break-words">
            <h3 class="text-lg font-bold font-serif text-main sm:text-xl">{{ $address['recipient_name'] ?? 'Địa chỉ' }}</h3>
            <p class="text-sm font-medium text-muted mt-0.5">{{ $address['phone'] ?? '' }}</p>
            <p class="mt-2 text-sm text-main/80 leading-relaxed">{{ $address['street_address'] ?? '' }}, {{ $address['ward_name_snapshot'] ?? '' }}, {{ $address['province_name_snapshot'] ?? '' }}</p>
            @if (($address['is_default'] ?? false) === true)
                <span class="mt-3 inline-block rounded-full bg-success-bg border border-success/30 px-3 py-1 text-xs font-semibold text-success-text shadow-subtle">Mặc định</span>
            @endif
            @if (($address['outdated'] ?? false) === true)
                <p role="status" class="mt-3 text-xs font-medium text-warning-text bg-warning-bg/50 border border-warning/30 p-2.5 rounded-xl">Địa chỉ được lưu theo danh mục địa giới cũ. Vui lòng sửa và chọn lại tỉnh / phường.</p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($setDefaultUrl && ! ($address['is_default'] ?? false))
                <form action="{{ $setDefaultUrl }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn-secondary px-3.5 py-1.5 text-xs font-semibold hover:border-success hover:text-success">Đặt mặc định</button>
                </form>
            @endif
            @if ($editUrl)
                <a href="{{ $editUrl }}" class="btn-secondary px-3.5 py-1.5 text-xs font-semibold hover:border-accent hover:text-accent">Sửa</a>
            @endif
            @if ($deleteUrl)
                <form action="{{ $deleteUrl }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-secondary px-3.5 py-1.5 text-xs font-semibold hover:border-error hover:text-error">Xóa</button>
                </form>
            @endif
        </div>
    </div>
</article>
