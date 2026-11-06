@props(['entries' => []])
@php
    $timeZone = new \DateTimeZone('Asia/Ho_Chi_Minh');
    $visibleEntries = array_values(array_filter((array) $entries, static fn ($entry) => is_array($entry) && ($entry['visibility'] ?? null) === 'CUSTOMER'));
@endphp
<section aria-label="Lịch sử trạng thái" class="space-y-4">
    <h2 class="text-lg font-bold font-serif text-main">Lịch sử cập nhật</h2>
    <ol class="relative space-y-5 border-l border-border pl-5">
        @if (count($visibleEntries) === 0)
            <li class="text-sm text-muted">Chưa có lịch sử công khai.</li>
        @else
            @foreach ($visibleEntries as $entry)
                @php
                    $date = null;
                    $timestamp = $entry['created_at'] ?? null;
                    if (is_string($timestamp) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $timestamp)) {
                        try {
                            $parsed = new \DateTimeImmutable($timestamp);
                            $parseErrors = \DateTimeImmutable::getLastErrors();
                            if ($parseErrors === false || ($parseErrors['warning_count'] === 0 && $parseErrors['error_count'] === 0)) {
                                $date = $parsed->setTimezone($timeZone)->format('d/m/Y H:i:s.u');
                            }
                        } catch (\Exception $exception) {
                            $date = null;
                        }
                    }
                @endphp
                <li class="relative space-y-1 text-sm"><span class="absolute -left-[1.45rem] top-2 h-2.5 w-2.5 rounded-full bg-accent ring-4 ring-surface"></span>
                    <p class="font-semibold text-main">{{ $entry['status_label'] ?? $entry['status'] ?? '' }}</p>
                    @if ($date)<time datetime="{{ $entry['created_at'] ?? '' }}" class="text-xs text-muted">{{ $date }} (VN)</time>@endif
                    @if (isset($entry['actor_name_snapshot']))<p class="text-xs text-muted">{{ $entry['actor_name_snapshot'] }}</p>@endif
                    @if (isset($entry['note']))<p class="text-xs text-main/90">{{ $entry['note'] }}</p>@endif
                </li>
            @endforeach
        @endif
    </ol>
</section>
