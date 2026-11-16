@props([
    'action',
    'value',
    'toggleKey',
    'label' => 'Thứ tự hiển thị',
])

@php
    $initial = (string) (int) $value;
@endphp

<form
    method="POST"
    action="{{ $action }}"
    x-data="{
        initial: @js($initial),
        current: @js($initial),
        stepping: false,
        get numeric() {
            const n = Number.parseInt(String(this.current).trim(), 10);
            return Number.isNaN(n) ? 0 : n;
        },
        get dirty() { return !this.stepping && String(this.current).trim() !== this.initial && String(this.current).trim() !== ''; },
        reset() { this.current = this.initial; },
        step(delta) {
            if (this.isBusy()) return;
            const next = Math.max(0, Math.min(65535, this.numeric + delta));
            if (String(next) === this.initial) return;
            this.stepping = true;
            this.current = String(next);
            this.$nextTick(() => this.$refs.form.requestSubmit());
        },
    }"
    x-ref="form"
    @submit.prevent="submitToggle($event, @js($toggleKey))"
    class="relative inline-flex items-center"
>
    @csrf
    @method('PATCH')
    <div class="inline-flex h-6 items-center rounded-md border border-border bg-surface focus-within:border-accent focus-within:ring-2 focus-within:ring-accent/30 transition-colors">
        <button
            type="button"
            @click="step(-1)"
            :disabled="isBusy() || numeric <= 0"
            title="Giảm 1"
            aria-label="Giảm {{ $label }} đi 1"
            class="inline-flex h-full w-5 items-center justify-center rounded-l-md text-muted hover:bg-border/40 hover:text-main transition-colors disabled:opacity-35 disabled:cursor-not-allowed cursor-pointer select-none"
        >
            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
            </svg>
        </button>
        <input
            type="number"
            name="order"
            min="0"
            max="65535"
            step="1"
            required
            x-model="current"
            @input="stepping = false"
            @keydown.escape="reset()"
            aria-label="{{ $label }}"
            title="Bấm -/+ để đổi nhanh 1 đơn vị hoặc nhập số rồi nhấn Enter"
            class="h-full w-9 border-x border-border/70 bg-transparent px-0.5 text-center font-mono text-[11px] font-bold text-main focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
        >
        <button
            type="button"
            @click="step(1)"
            :disabled="isBusy() || numeric >= 65535"
            title="Tăng 1"
            aria-label="Tăng {{ $label }} thêm 1"
            class="inline-flex h-full w-5 items-center justify-center rounded-r-md text-muted hover:bg-border/40 hover:text-main transition-colors disabled:opacity-35 disabled:cursor-not-allowed cursor-pointer select-none"
        >
            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
        </button>
    </div>
    <button
        type="submit"
        x-show="dirty"
        x-cloak
        :disabled="isBusy()"
        title="Lưu thứ tự"
        aria-label="Lưu {{ $label }}"
        class="absolute left-full ml-1 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-accent text-accent-text shadow-sm hover:bg-accent-hover transition-colors disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
    >
        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
        </svg>
    </button>
</form>
