@extends('layouts.store')

@section('title', 'Thông báo')

@php($tz = (string) config('store.business_timezone'))
@php($initialUnread = $unreadCount ?? $notifications->getCollection()->whereNull('read_at')->count())

@section('content')
    <section class="py-6 sm:py-10"
             x-data="{
                 unreadCount: {{ (int) $initialUnread }},
                 allRead: false,
                 readIds: {},
                 markingAll: false,
                 markingId: null,
                 errorMessage: '',
                 init() {
                     this._sync = (e) => {
                         const d = e?.detail ?? {};
                         if (typeof d.unreadCount === 'number') this.unreadCount = Math.max(0, d.unreadCount);
                         if (d.markAllRead) this.allRead = true;
                         else if (d.readId) this.readIds = { ...this.readIds, [d.readId]: true };
                     };
                     window.addEventListener('cena:notifications-sync', this._sync);
                 },
                 destroy() {
                     if (this._sync) window.removeEventListener('cena:notifications-sync', this._sync);
                 },
                 isRead(id, initialRead) {
                     return Boolean(initialRead || this.allRead || this.readIds[id]);
                 },
                 csrf() {
                     return document.querySelector('meta[name=&quot;csrf-token&quot;]')?.content ?? '';
                 },
                 async markAll() {
                     if (this.markingAll || this.unreadCount === 0) return;
                     this.markingAll = true;
                     this.errorMessage = '';
                     try {
                         const res = await fetch(@js(route('notifications.api.read-all')), {
                             method: 'POST',
                             credentials: 'same-origin',
                             headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrf() },
                         });
                         if (!res.ok) {
                             this.errorMessage = res.status === 419 ? 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.' : 'Không thể đánh dấu tất cả đã đọc.';
                             return;
                         }
                         this.allRead = true;
                         this.unreadCount = 0;
                         window.dispatchEvent(new CustomEvent('cena:notifications-sync', { detail: { unreadCount: 0, markAllRead: true } }));
                     } catch {
                         this.errorMessage = 'Không kết nối được máy chủ.';
                     } finally {
                         this.markingAll = false;
                     }
                 },
                 async markOne(id, url) {
                     if (this.markingId !== null) return;
                     this.markingId = id;
                     this.errorMessage = '';
                     try {
                         const res = await fetch(url, {
                             method: 'POST',
                             credentials: 'same-origin',
                             headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrf() },
                         });
                         if (!res.ok) {
                             this.errorMessage = res.status === 419 ? 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.' : 'Không thể đánh dấu thông báo đã đọc.';
                             return;
                         }
                         if (!this.readIds[id]) {
                             this.readIds = { ...this.readIds, [id]: true };
                             this.unreadCount = Math.max(0, this.unreadCount - 1);
                         }
                         window.dispatchEvent(new CustomEvent('cena:notifications-sync', { detail: { unreadCount: this.unreadCount, readId: id } }));
                     } catch {
                         this.errorMessage = 'Không kết nối được máy chủ.';
                     } finally {
                         this.markingId = null;
                     }
                 }
             }">
        <div class="mx-auto flex max-w-4xl flex-col gap-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="space-y-2">
                    <a href="{{ route('account.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted transition-colors hover:text-accent">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                        <span>Tài khoản của tôi</span>
                    </a>
                    <h1 class="text-2xl font-extrabold tracking-tight text-main sm:text-3xl">Thông báo</h1>
                </div>

                @if ($notifications->isNotEmpty())
                    <button type="button"
                            @click="markAll()"
                            :disabled="markingAll || unreadCount === 0"
                            class="btn-secondary px-3.5 py-2 text-xs font-semibold disabled:cursor-not-allowed disabled:opacity-45">
                        <span x-text="markingAll ? 'Đang xử lý...' : 'Đọc tất cả'">Đọc tất cả</span>
                    </button>
                @endif
            </div>

            <p x-cloak x-show="errorMessage !== ''" x-text="errorMessage" role="alert"
               class="rounded-xl border border-error/30 bg-error-bg px-4 py-2.5 text-xs font-medium text-error-text"></p>

            @if ($notifications->isEmpty())
                <div class="rounded-2xl border-2 border-dashed border-border bg-surface/40 p-10 text-center">
                    <p class="text-sm text-muted">Bạn chưa có thông báo nào.</p>
                </div>
            @else
                <div class="rounded-2xl border border-border bg-surface-card divide-y divide-border overflow-hidden">
                    @foreach ($notifications as $notification)
                        @php($initiallyRead = $notification->read_at !== null)
                        @php($orderCode = $notification->order?->order_code)
                        <article :class="isRead({{ $notification->id }}, {{ $initiallyRead ? 'true' : 'false' }}) ? 'bg-surface-card' : 'bg-accent/5'"
                                 class="flex flex-col gap-3 px-4 py-3.5 transition-colors sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span x-show="!isRead({{ $notification->id }}, {{ $initiallyRead ? 'true' : 'false' }})"
                                          @class(['inline-flex items-center gap-1.5 rounded-full bg-accent-sub px-2 py-0.5 text-[11px] font-semibold text-accent-sub-text', 'hidden' => $initiallyRead])
                                          @if ($initiallyRead) x-cloak @endif>
                                        <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                        Chưa đọc
                                    </span>
                                    <span x-show="isRead({{ $notification->id }}, {{ $initiallyRead ? 'true' : 'false' }})"
                                          @class(['inline-flex items-center rounded-full bg-surface px-2 py-0.5 text-[11px] font-medium text-muted', 'hidden' => ! $initiallyRead])
                                          @if (! $initiallyRead) x-cloak @endif>
                                        Đã đọc
                                    </span>
                                    <span class="text-xs text-muted">{{ $notification->created_at?->setTimezone($tz)->format('d/m/Y H:i') }}</span>
                                </div>
                                <p class="min-w-0 break-words text-sm leading-snug"
                                   :class="isRead({{ $notification->id }}, {{ $initiallyRead ? 'true' : 'false' }}) ? 'font-normal text-muted' : 'font-semibold text-main'">
                                    Đơn <span class="font-mono break-all">{{ $orderCode }}</span> &middot; {{ $notification->order_status->label() }}
                                </p>
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center gap-2 sm:gap-3">
                                @if ($orderCode !== null)
                                    <a href="{{ route('orders.show', $orderCode) }}"
                                       class="btn-secondary px-3 py-1.5 text-xs font-semibold">Xem đơn</a>
                                @endif
                                <button type="button"
                                        x-show="!isRead({{ $notification->id }}, {{ $initiallyRead ? 'true' : 'false' }})"
                                        @if ($initiallyRead) x-cloak @endif
                                        :disabled="markingId === {{ $notification->id }}"
                                        @click="markOne({{ $notification->id }}, @js(route('notifications.api.read', $notification)))"
                                        class="px-2 py-1.5 text-xs font-semibold text-accent transition-colors hover:text-accent-hover disabled:opacity-50">
                                    <span x-text="markingId === {{ $notification->id }} ? 'Đang lưu...' : 'Đánh dấu đã đọc'">Đánh dấu đã đọc</span>
                                </button>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            <x-ui.pagination :paginator="$notifications" />
        </div>
    </section>
@endsection
