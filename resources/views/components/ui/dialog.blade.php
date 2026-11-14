@props([
    'id' => 'cena-dialog',
    'title' => 'Thông báo',
    'triggerClass' => '',
    'panelClass' => 'max-w-lg',
])
<div x-data="dialogController()" x-init="attach({panel: $refs.panel, trigger: $refs.trigger, body: document.body, document, scheduleFocus: callback => $nextTick(callback)})" class="inline-block">
    <button x-ref="trigger" type="button" x-on:click="openDialog($el)" aria-haspopup="dialog" x-bind:aria-expanded="opened.toString()" aria-controls="{{ $id }}" {{ $triggerAttributes ?? '' }} @if($triggerClass) class="{{ $triggerClass }}" @endif>{{ $trigger ?? 'Mở hộp thoại' }}</button>
    <div x-cloak x-show="opened" x-on:keydown="onKeydown($event)" x-on:click.self="onBackdrop($event)" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-[1px]" role="presentation">
        <section x-ref="panel" id="{{ $id }}" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" tabindex="-1" class="max-h-[90vh] w-full {{ $panelClass }} overflow-y-auto rounded-2xl border border-border bg-surface-card p-6 text-main shadow-elevated">
            <div class="flex items-start justify-between gap-4 border-b border-border/60 pb-3">
                <h2 id="{{ $id }}-title" class="text-base font-bold text-main">{{ $title }}</h2>
                <button type="button" x-on:click="closeDialog()" aria-label="Đóng hộp thoại" class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-base text-muted hover:bg-surface hover:text-main transition-colors">×</button>
            </div>
            <div class="mt-4">{{ $slot }}</div>
        </section>
    </div>
</div>
