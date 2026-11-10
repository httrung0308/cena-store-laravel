@extends('layouts.admin', ['title' => 'Chi tiết công việc'])

@section('content')
    @php($timezone = (string) config('store.business_timezone'))
    <div class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <a class="text-sm font-semibold underline" href="{{ route('admin.operations-work-items.index', ['list' => $workItem->status === \App\Enums\OperationsWorkItemStatus::Completed ? 'completed' : 'active']) }}">← Danh sách công việc</a>
        <article class="space-y-5 rounded-xl border border-border bg-surface-card p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><p class="text-sm text-muted">{{ $workItem->source_label }} · {{ $workItem->source_reference }}</p><h1 class="mt-1 text-2xl font-bold">{{ $workItem->title }}</h1></div>
                <span class="rounded-full bg-surface px-3 py-1 text-sm text-main">{{ $workItem->status->label() }}</span>
            </div>
            <p class="whitespace-pre-line">{{ $workItem->content }}</p>
            <p class="text-sm text-muted">Phát sinh {{ $workItem->occurred_at->timezone($timezone)->format('d/m/Y H:i:s') }}</p>
        </article>

        @if ($workItem->status !== \App\Enums\OperationsWorkItemStatus::Completed)
            <form method="POST" action="{{ route('admin.operations-work-items.update', $workItem) }}" class="space-y-4 rounded-xl border border-border bg-surface-card p-6">
                @csrf @method('PATCH')
                <input type="hidden" name="version" value="{{ $workItem->version }}">
                @if ($errors->any())
                    <div role="alert" class="rounded-lg border border-error/30 bg-error-bg p-3 text-sm text-error">{{ $errors->first('work_item') ?: $errors->first('status') ?: $errors->first('version') ?: 'Vui lòng kiểm tra lại thông tin cập nhật.' }}</div>
                @endif
                <div><label for="status" class="mb-1 block text-sm font-semibold">Trạng thái</label><select id="status" name="status" required class="input w-full border-border bg-surface-card px-3 py-2 text-main">
                    @foreach ([\App\Enums\OperationsWorkItemStatus::Pending, \App\Enums\OperationsWorkItemStatus::InProgress] as $status)
                        <option value="{{ $status->value }}" @selected($workItem->status === $status)>{{ $status->label() }}</option>
                    @endforeach
                </select></div>
                <div><label for="note" class="mb-1 block text-sm font-semibold">Ghi chú kết quả xử lý</label><textarea id="note" name="note" rows="4" maxlength="2000" class="input w-full border-border bg-surface-card px-3 py-2 text-main">{{ old('note', $workItem->histories->last()?->note) }}</textarea></div>
                <button class="rounded-lg bg-accent px-5 py-2 font-semibold text-accent-text hover:bg-accent-hover">Lưu cập nhật</button>
            </form>
        @endif

        <section class="space-y-4">
            <h2 class="text-xl font-bold">Lịch sử cập nhật</h2>
            <ol class="space-y-3">
                @foreach ($workItem->histories->reverse() as $history)
                    <li class="rounded-xl border border-border bg-surface-card p-4">
                        <div class="flex flex-wrap justify-between gap-2"><strong>{{ $history->to_status->label() }}@if ($history->from_status) <span class="font-normal text-muted">· từ {{ $history->from_status->label() }}</span>@endif</strong><time class="text-sm text-muted">{{ $history->created_at->timezone($timezone)->format('d/m/Y H:i:s') }}</time></div>
                        <p class="mt-1 text-sm text-muted">{{ $history->actor_name_snapshot }} · {{ implode(', ', $history->actor_roles_snapshot) }}</p>
                        @if ($history->note)<p class="mt-3 whitespace-pre-line">{{ $history->note }}</p>@endif
                        @if ($history->outcome_code)<p class="mt-2 text-sm">Kết quả xác minh: {{ $history->outcome_code }}</p>@endif
                    </li>
                @endforeach
            </ol>
        </section>
    </div>
@endsection
