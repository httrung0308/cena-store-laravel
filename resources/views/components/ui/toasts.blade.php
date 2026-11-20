@props(['initial' => []])
<div x-data="toastsController()"
     x-init="@foreach ((array) $initial as $toast) notify(@js($toast)); @endforeach"
     @cena-toast.window="notify($event.detail)"
     class="pointer-events-none fixed left-3 right-3 top-28 z-50 flex max-w-sm flex-col gap-2 sm:left-auto sm:right-5 sm:top-14 sm:w-96">
    <template x-for="toast in items" :key="toast.id">
        <div class="pointer-events-auto rounded-2xl border p-3.5 text-xs font-semibold shadow-elevated transition-shadow duration-150"
             x-bind:class="{
                 'border-success/40 bg-surface-card text-main': toast.type === 'success',
                 'border-error/40 bg-error-bg text-error-text': toast.type === 'error',
                 'border-warning/40 bg-warning-bg text-warning-text': toast.type === 'warning',
                 'border-border bg-surface-card text-main': toast.type === 'info'
             }"
             x-on:mouseenter="pause(toast.id)"
             x-on:mouseleave="resume(toast.id)"
             role="status"
             x-bind:aria-live="toast.type === 'error' ? 'assertive' : 'polite'">
            <div class="flex items-start gap-2.5">
                <span class="mt-1 h-2 w-2 shrink-0 rounded-full"
                      x-bind:class="{
                          'bg-success': toast.type === 'success',
                          'bg-error': toast.type === 'error',
                          'bg-warning': toast.type === 'warning',
                          'bg-accent': toast.type === 'info'
                      }"
                      aria-hidden="true"></span>
                <p class="flex-1 break-words leading-relaxed" x-text="toast.message"></p>
                <button type="button"
                        class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-lg text-sm leading-none opacity-70 transition-colors hover:bg-surface hover:opacity-100"
                        x-on:click="dismiss(toast.id)"
                        aria-label="Đóng thông báo">×</button>
            </div>
            <p class="mt-1 pl-4 text-[11px] font-normal text-muted" x-show="toast.request_id" x-text="toast.request_id"></p>
        </div>
    </template>
</div>
