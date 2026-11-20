<div x-cloak x-show="errorMessage" role="alert" aria-live="assertive" class="flex items-center justify-between gap-3 rounded-2xl border border-error/30 bg-error-bg px-4 py-3 text-xs font-semibold text-error-text shadow-sm">
    <span x-text="errorMessage"></span>
    <button type="button" @click="errorMessage = ''" class="font-bold opacity-70 hover:opacity-100" aria-label="Đóng thông báo lỗi">×</button>
</div>
