@props(['status'])
@php
    $classes = match ($status) {
        App\Enums\OrderStatus::Pending => 'bg-amber-100 text-amber-700',
        App\Enums\OrderStatus::Confirmed, App\Enums\OrderStatus::Packing => 'bg-blue-100 text-blue-700',
        App\Enums\OrderStatus::OnDelivery => 'bg-indigo-100 text-indigo-700',
        App\Enums\OrderStatus::Delivered => 'bg-green-100 text-green-600',
        App\Enums\OrderStatus::Cancelled, App\Enums\OrderStatus::Failed => 'bg-red-100 text-red-600',
        default => 'bg-zinc-100 text-zinc-600',
    };
@endphp
<span {{ $attributes->class(['inline-block whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold', $classes]) }}>{{ $status?->label() }}</span>
