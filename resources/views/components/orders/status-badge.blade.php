@props(['status'])
@php
    $classes = match ($status) {
        App\Enums\OrderStatus::Pending => 'bg-warning-bg text-warning-text border border-warning/30',
        App\Enums\OrderStatus::Confirmed, App\Enums\OrderStatus::Packing => 'bg-surface text-main border border-border',
        App\Enums\OrderStatus::OnDelivery => 'bg-accent-sub/10 text-accent-sub border border-accent-sub/20',
        App\Enums\OrderStatus::Delivered => 'bg-success-bg text-success-text border border-success/30',
        App\Enums\OrderStatus::Cancelled, App\Enums\OrderStatus::Failed => 'bg-error-bg text-error-text border border-error/30',
        default => 'bg-surface text-muted border border-border',
    };
@endphp
<span {{ $attributes->class(['inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold shadow-subtle', $classes]) }}>{{ $status?->label() }}</span>
