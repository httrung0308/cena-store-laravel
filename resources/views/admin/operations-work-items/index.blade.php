@extends('layouts.admin', ['title' => 'Công việc cần xử lý'])

@section('content')
    @php($timezone = (string) config('store.business_timezone'))
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm text-muted">Vận hành cửa hàng</p>
                <h1 class="text-2xl font-bold">{{ $completed ? 'Công việc đã hoàn thành' : 'Công việc cần xử lý' }}</h1>
            </div>
            <nav class="flex gap-2 text-sm" aria-label="Lọc trạng thái công việc">
                <a class="rounded-lg px-3 py-2 {{ ! $completed ? 'bg-accent text-accent-text' : 'bg-surface-card text-main' }}" href="{{ route('admin.operations-work-items.index', array_merge(request()->except('page'), ['list' => 'active'])) }}">Đang xử lý</a>
                <a class="rounded-lg px-3 py-2 {{ $completed ? 'bg-accent text-accent-text' : 'bg-surface-card text-main' }}" href="{{ route('admin.operations-work-items.index', array_merge(request()->except('page'), ['list' => 'completed'])) }}">Đã hoàn thành</a>
            </nav>
        </div>

        <form method="GET" class="grid gap-3 rounded-xl border border-border bg-surface-card p-4 md:grid-cols-[1fr_15rem_auto]">
            @if ($completed)<input type="hidden" name="list" value="completed">@endif
            <label class="sr-only" for="work-search">Tìm công việc</label>
            <input id="work-search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" placeholder="Tìm nội dung hoặc mã đối tượng" class="input rounded-lg border-border bg-surface-card px-3 py-2 text-main">
            <label class="sr-only" for="source-type">Loại đối tượng</label>
            <select id="source-type" name="source_type" class="input rounded-lg border-border bg-surface-card px-3 py-2 text-main">
                <option value="">Tất cả loại đối tượng</option>
                @foreach (\App\Enums\OperationsSourceType::cases() as $sourceType)
                    <option value="{{ $sourceType->value }}" @selected(($filters['source_type'] ?? '') === $sourceType->value)>{{ $sourceType->label() }}</option>
                @endforeach
            </select>
            <button class="rounded-lg bg-accent px-5 py-2 font-semibold text-accent-text hover:bg-accent-hover">Tìm kiếm</button>
        </form>

        @if ($items->isEmpty())
            <div class="rounded-xl border border-border bg-surface-card p-8 text-center text-muted">Chưa có công việc trong danh sách này.</div>
        @else
            <div class="overflow-x-auto rounded-xl border border-border bg-surface-card">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-border bg-surface text-main"><tr><th class="px-4 py-3">Công việc</th><th class="px-4 py-3">Đối tượng liên quan</th><th class="px-4 py-3">Phát sinh</th><th class="px-4 py-3">Trạng thái</th><th class="px-4 py-3"><span class="sr-only">Chi tiết</span></th></tr></thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($items as $item)
                            <tr>
                                <td class="max-w-xl px-4 py-4"><a class="font-semibold text-main underline-offset-2 hover:underline" href="{{ route('admin.operations-work-items.show', $item) }}">{{ $item->title }}</a><p class="mt-1 line-clamp-2 text-muted">{{ $item->content }}</p></td>
                                <td class="px-4 py-4"><span class="block">{{ $item->source_label }}</span><span class="text-muted">{{ $item->source_reference }}</span></td>
                                <td class="whitespace-nowrap px-4 py-4">{{ $item->occurred_at->timezone($timezone)->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-4">{{ $item->status->label() }}</td>
                                <td class="px-4 py-4"><a class="font-semibold underline" href="{{ route('admin.operations-work-items.show', $item) }}">Xem</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$items" />
        @endif
    </div>
@endsection
