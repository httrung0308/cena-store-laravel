@props(['request' => [], 'histories' => [], 'attachments' => [], 'allowedActions' => []])
<section class="space-y-5" aria-label="Chi tiết hậu mãi">
    <header><h2 class="text-xl font-semibold">Yêu cầu {{ $request['request_code'] ?? '' }}</h2>
        <p class="text-sm">Loại: {{ $request['type'] ?? '' }} · Trạng thái: {{ $request['status'] ?? '' }}</p>
        @if ($request['receive_by_at'] ?? null)<p class="text-sm">Hạn nhận: {{ $request['receive_by_at'] }}</p>@endif
        <p class="text-sm">{{ $request['customer_reason'] ?? '' }}</p>
    </header>
    <x-orders.timeline :entries="$histories" />
    <div class="space-y-2"><h3 class="font-semibold">Tệp đính kèm</h3>
        @forelse ($attachments as $entry)
            @if (is_string($entry['authorizedUrl'] ?? null) && $entry['authorizedUrl'] !== '')<a class="block text-sm underline" href="{{ $entry['authorizedUrl'] }}">Tệp {{ $entry['id'] ?? '' }}</a>
            @else<p class="text-sm text-zinc-500">Tệp chưa khả dụng.</p>@endif
        @empty<p class="text-sm text-zinc-500">Chưa có tệp đính kèm.</p>@endforelse
    </div>
    @if ($allowedActions['cancelUrl'] ?? null)<form action="{{ $allowedActions['cancelUrl'] }}" method="POST">@csrf<button type="submit" class="text-sm underline">Hủy yêu cầu</button></form>@endif
</section>
