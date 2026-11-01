@props(['initial' => []])
<div x-data="toastsController()" x-init="@foreach ((array) $initial as $toast) notify(@js($toast)); @endforeach" class="pointer-events-none fixed right-3 top-3 z-50 flex max-w-sm flex-col gap-2">
    <template x-for="toast in items" :key="toast.id">
        <div class="pointer-events-auto border bg-white p-4 text-sm text-black shadow-lg" x-bind:class="{ 'border-red-500': toast.type === 'error', 'border-zinc-400': toast.type !== 'error' }" x-on:mouseenter="pause(toast.id)" x-on:mouseleave="resume(toast.id)" role="status" x-bind:aria-live="toast.type === 'error' ? 'assertive' : 'polite'">
            <div class="flex items-start gap-3"><p class="flex-1 break-words" x-text="toast.message"></p><button type="button" class="underline" x-on:click="dismiss(toast.id)" aria-label="Đóng thông báo">×</button></div>
            <p class="mt-1 text-xs text-zinc-500" x-show="toast.request_id" x-text="toast.request_id"></p>
        </div>
    </template>
</div>
