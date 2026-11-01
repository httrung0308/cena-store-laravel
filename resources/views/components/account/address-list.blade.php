@props(['addresses' => []])
<div {{ $attributes->class(['grid gap-4 md:grid-cols-2']) }}>
    @forelse ($addresses as $entry)
        <x-account.address-card :address="$entry['address'] ?? []" :edit-url="$entry['editUrl'] ?? null" :delete-url="$entry['deleteUrl'] ?? null" :set-default-url="$entry['setDefaultUrl'] ?? null" />
    @empty
        <x-ui.feedback state="empty" title="Chưa có địa chỉ" />
    @endforelse
</div>
