@props(['addresses' => []])
<div {{ $attributes->class(['space-y-4']) }}>
    @forelse ($addresses as $entry)
        <x-account.address-card :address="$entry['address'] ?? []" :edit-url="$entry['editUrl'] ?? null" :delete-url="$entry['deleteUrl'] ?? null" :set-default-url="$entry['setDefaultUrl'] ?? null" />
    @empty
        <x-ui.feedback state="empty" title="Chưa có địa chỉ" />
    @endforelse
</div>
