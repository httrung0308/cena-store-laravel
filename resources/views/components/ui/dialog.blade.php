@props(['id' => 'cena-dialog', 'title' => 'Thông báo'])
<div x-data="dialogController()" x-init="attach({panel: $refs.panel, trigger: $refs.trigger, body: document.body, document})" class="inline-block">
    <button x-ref="trigger" type="button" x-on:click="openDialog($el)" aria-haspopup="dialog" x-bind:aria-expanded="opened.toString()" aria-controls="{{ $id }}" {{ $triggerAttributes ?? '' }}>{{ $trigger ?? 'Mở hộp thoại' }}</button>
    <div x-cloak x-show="opened" x-on:keydown="onKeydown($event)" x-on:click.self="onBackdrop($event)" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" role="presentation">
        <section x-ref="panel" id="{{ $id }}" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" tabindex="-1" class="max-h-[90vh] w-full max-w-lg overflow-y-auto bg-white p-6 shadow-lg">
            <div class="flex items-start justify-between gap-4"><h2 id="{{ $id }}-title" class="text-lg font-semibold">{{ $title }}</h2><button type="button" x-on:click="closeDialog()" aria-label="Đóng hộp thoại">×</button></div>
            <div class="mt-4">{{ $slot }}</div>
        </section>
    </div>
</div>
