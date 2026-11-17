@extends('layouts.admin')

@section('title', $completed ? 'Công Việc Đã Hoàn Thành' : 'Trung Tâm Công Việc Vận Hành')
@section('eyebrow', 'VẬN HÀNH CỬA HÀNG')

@php
    $timezone = (string) config('store.business_timezone', 'Asia/Ho_Chi_Minh');
    $activeChips = [];
    if (filled($filters['search'] ?? null)) {
        $activeChips[] = [
            'label' => 'Từ khóa: "'.$filters['search'].'"',
            'url' => request()->fullUrlWithQuery(['search' => null, 'page' => null]),
        ];
    }
    if ($activeType !== \App\Services\OperationsQueueService::TYPE_ALL && isset($queueDefinitions[$activeType])) {
        $activeChips[] = [
            'label' => 'Hàng đợi: '.$queueDefinitions[$activeType]['label'],
            'url' => request()->fullUrlWithQuery(['type' => null, 'page' => null]),
        ];
    }
    if (filled($filters['source_type'] ?? null)) {
        $activeChips[] = [
            'label' => 'Nguồn: '.\App\Enums\OperationsSourceType::from($filters['source_type'])->label(),
            'url' => request()->fullUrlWithQuery(['source_type' => null, 'page' => null]),
        ];
    }
    $hasEmptyGroups = collect($queueDefinitions)->contains(
        fn (array $definition, string $key): bool => (int) ($counts[$key] ?? 0) === 0 && $activeType !== $key
    );
@endphp

@section('content')
    <div x-data="adminAjaxList()" class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <nav class="inline-flex rounded-xl border border-border bg-surface-card p-1 text-xs font-bold shadow-sm" aria-label="Lọc trạng thái công việc">
                <a href="{{ route('admin.operations-work-items.index', array_merge(request()->except('page'), ['list' => 'active'])) }}"
                   @click.prevent="visitUrl($el.href)"
                   class="rounded-lg px-3.5 py-2 transition-colors {{ ! $completed ? 'bg-accent text-accent-text shadow-sm' : 'text-muted hover:text-main' }}">
                    Đang xử lý ({{ number_format($counts['total']) }})
                </a>
                <a href="{{ route('admin.operations-work-items.index', array_merge(request()->except('page'), ['list' => 'completed'])) }}"
                   @click.prevent="visitUrl($el.href)"
                   class="rounded-lg px-3.5 py-2 transition-colors {{ $completed ? 'bg-accent text-accent-text shadow-sm' : 'text-muted hover:text-main' }}">
                    Đã hoàn thành (Hàng hoàn, Duyệt &amp; Sự cố)
                </a>
            </nav>

            <button type="button"
                    @click="visitUrl(window.location.href)"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-border bg-surface-card px-3.5 py-2 text-xs font-bold text-main shadow-sm transition-colors hover:bg-surface">
                <span aria-hidden="true">↻</span>
                <span>Làm mới</span>
            </button>
        </div>

        <x-admin.search-form
            :action="route('admin.operations-work-items.index')"
            id="work-search"
            label="Tìm công việc"
            placeholder="Tìm theo mã đơn, SKU, mã vận đơn hoặc tiêu đề..."
            :value="$filters['search'] ?? ''"
            :ajax="true"
            :active-chips="$activeChips"
        >
            @if ($completed)
                <input type="hidden" name="list" value="completed">
            @endif
            @if ($activeType !== \App\Services\OperationsQueueService::TYPE_ALL)
                <input type="hidden" name="type" value="{{ $activeType }}">
            @endif
        </x-admin.search-form>

        <div id="admin-list-region"
             @click="handleRegionClick($event)"
             :class="{ 'opacity-60 pointer-events-none transition-opacity': listLoading }"
             class="space-y-4">
            @if (! $completed)
                <div x-data="{ showEmpty: false }" class="flex flex-wrap items-center gap-2 text-xs">
                    <a href="{{ route('admin.operations-work-items.index', array_merge(request()->except(['page', 'type']), ['list' => 'active'])) }}"
                       @click.prevent="visitUrl($el.href)"
                       @class([
                           'inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 font-bold transition-colors',
                           'border-accent bg-accent text-accent-text shadow-sm' => $activeType === \App\Services\OperationsQueueService::TYPE_ALL,
                           'border-border bg-surface-card text-main hover:border-accent/60' => $activeType !== \App\Services\OperationsQueueService::TYPE_ALL,
                       ])>
                        <span>Tất cả</span>
                        <span class="font-mono">({{ number_format($counts['total']) }})</span>
                    </a>
                    @foreach ($queueDefinitions as $key => $definition)
                        @php
                            $queueCount = (int) ($counts[$key] ?? 0);
                            $visibleByDefault = $queueCount > 0 || $activeType === $key;
                        @endphp
                        <a href="{{ route('admin.operations-work-items.index', array_merge(request()->except('page'), ['list' => 'active', 'type' => $key])) }}"
                           @click.prevent="visitUrl($el.href)"
                           @if (! $visibleByDefault)
                               x-show="showEmpty"
                               x-cloak
                           @endif
                           @class([
                               'inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 font-semibold transition-colors',
                               'border-accent bg-accent text-accent-text shadow-sm' => $activeType === $key,
                               'border-border bg-surface-card text-main hover:border-accent/60' => $activeType !== $key && $queueCount > 0,
                               'border-border/60 bg-surface/60 text-muted hover:text-main' => $activeType !== $key && $queueCount === 0,
                           ])>
                            <span>{{ $definition['label'] }}</span>
                            <span class="font-mono font-bold">({{ number_format($queueCount) }})</span>
                        </a>
                    @endforeach
                    @if ($hasEmptyGroups)
                        <button type="button"
                                @click="showEmpty = !showEmpty"
                                class="inline-flex items-center gap-1 rounded-xl border border-dashed border-border px-2.5 py-1.5 text-[11px] font-semibold text-muted hover:text-main transition-colors">
                            <span x-text="showEmpty ? 'Ẩn nhóm trống' : 'Hiện nhóm trống'">Hiện nhóm trống</span>
                        </button>
                    @endif
                </div>
            @else
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border bg-surface/60 px-4 py-2.5 text-xs text-muted">
                    <span>Danh sách này chỉ hiển thị các đầu việc hàng hoàn, phê duyệt và xử lý sự cố đã hoàn tất. Để tra cứu toàn bộ lịch sử đơn hàng, xem tại</span>
                    <a href="{{ route('admin.orders.index') }}" class="font-bold text-accent hover:underline whitespace-nowrap">
                        Danh sách đơn hàng →
                    </a>
                </div>
            @endif

            <div class="flex items-center justify-between px-1 text-xs text-muted">
                <span>Tổng cộng <strong class="font-bold text-main">{{ number_format($items->total()) }}</strong> đầu việc</span>
                @if ($items->hasPages())
                    <span>Trang {{ $items->currentPage() }} / {{ $items->lastPage() }}</span>
                @endif
            </div>

            @if ($items->isEmpty())
                <div class="rounded-2xl border border-border bg-surface-card p-8 text-center text-xs text-muted shadow-sm">
                    Chưa có công việc trong danh sách này.
                </div>
            @else
                <div class="overflow-hidden rounded-2xl border border-border bg-surface-card shadow-sm">
                    {{-- Compact mobile cards (< md) --}}
                    <div class="divide-y divide-border md:hidden">
                        @foreach ($items as $item)
                            @php
                                $occurredAtLocal = $item['occurred_at']->timezone($timezone);
                                $fullTimestamp = $occurredAtLocal->format('d/m/Y H:i:s');
                            @endphp
                            <article class="space-y-2 p-4 text-xs">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-bold {{ $item['type_badge_class'] }}">
                                        {{ $item['type_label'] }}
                                    </span>
                                    <span class="tabular-nums text-[11px] text-muted" title="{{ $fullTimestamp }}">
                                        {{ $occurredAtLocal->locale('vi')->diffForHumans() }}
                                    </span>
                                </div>
                                <div>
                                    <a href="{{ $item['action_url'] }}"
                                       class="inline-flex items-center gap-1 font-bold text-accent hover:underline">
                                        <span>{{ $item['action_title'] ?? $item['title'] }}</span>
                                        <span aria-hidden="true">→</span>
                                    </a>
                                    <span class="ml-1.5 font-mono text-[11px] font-semibold text-muted">{{ $item['reference'] }}</span>
                                </div>
                                <p class="line-clamp-2 text-muted">{{ $item['related_info'] ?? $item['description'] }}</p>
                            </article>
                        @endforeach
                    </div>

                    {{-- 3-column desktop table (>= md) --}}
                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full min-w-[780px] table-fixed border-collapse text-left text-xs text-muted">
                            <thead class="border-b border-border bg-surface font-bold text-main">
                                <tr>
                                    <th class="w-[34%] px-4 py-3">Việc cần làm</th>
                                    <th class="w-[48%] px-4 py-3">Thông tin liên quan</th>
                                    <th class="w-[18%] px-4 py-3 text-right">Đã chờ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($items as $item)
                                    @php
                                        $occurredAtLocal = $item['occurred_at']->timezone($timezone);
                                        $fullTimestamp = $occurredAtLocal->format('d/m/Y H:i:s');
                                    @endphp
                                    <tr class="align-top transition-colors hover:bg-surface/50">
                                        <td class="px-4 py-3.5">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-bold {{ $item['type_badge_class'] }}">
                                                    {{ $item['type_label'] }}
                                                </span>
                                            </div>
                                            <a href="{{ $item['action_url'] }}"
                                               class="mt-1.5 inline-flex items-center gap-1 font-bold text-accent hover:underline">
                                                <span>{{ $item['action_title'] ?? $item['title'] }}</span>
                                                <span aria-hidden="true">→</span>
                                            </a>
                                            <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] text-muted">
                                                <span class="font-mono font-semibold text-main">{{ $item['reference'] }}</span>
                                                @if (filled($item['reference_sub']) && $item['reference_sub'] !== $item['reference'])
                                                    <span>· {{ $item['reference_sub'] }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <p class="line-clamp-2 leading-relaxed text-main">{{ $item['related_info'] ?? $item['description'] }}</p>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums">
                                            <span class="inline-flex items-center justify-end gap-1 font-medium text-main"
                                                  title="{{ $fullTimestamp }}">
                                                <span>{{ $occurredAtLocal->locale('vi')->diffForHumans() }}</span>
                                            </span>
                                            <span class="mt-0.5 block font-mono text-[11px] text-muted" title="{{ $fullTimestamp }}">
                                                {{ $occurredAtLocal->format('d/m H:i') }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <x-ui.pagination :paginator="$items" />
                </div>
            @endif
        </div>
    </div>
@endsection
