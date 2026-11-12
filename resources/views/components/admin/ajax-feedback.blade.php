<div class="space-y-2" aria-live="polite">
    <div x-cloak x-show="statusMessage" role="status" class="flex items-center justify-between gap-3 rounded-2xl border border-success/30 bg-success-bg px-4 py-3 text-xs font-semibold text-success-text shadow-sm">
        <span x-text="statusMessage"></span>
        <button type="button" @click="statusMessage = ''" class="font-bold opacity-70 hover:opacity-100" aria-label="Đóng thông báo">×</button>
    </div>
    <div x-cloak x-show="errorMessage" role="alert" class="flex items-center justify-between gap-3 rounded-2xl border border-error/30 bg-error-bg px-4 py-3 text-xs font-semibold text-error-text shadow-sm">
        <span x-text="errorMessage"></span>
        <button type="button" @click="errorMessage = ''" class="font-bold opacity-70 hover:opacity-100" aria-label="Đóng thông báo lỗi">×</button>
    </div>
</div>
