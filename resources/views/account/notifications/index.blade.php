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
                 async openOrder(id, initialRead, markUrl, orderUrl) {
                     if (this.markingId !== null) return;
                     if (!this.isRead(id, initialRead)) {
                         this.markingId = id;
                         this.errorMessage = '';
                         try {
                             const res = await fetch(markUrl, {
                                 method: 'POST',
                                 credentials: 'same-origin',
                                 headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrf() },
                             });
                             if (res.ok) {
                                 this.readIds = { ...this.readIds, [id]: true };
                                 this.unreadCount = Math.max(0, this.unreadCount - 1);
                                 window.dispatchEvent(new CustomEvent('cena:notifications-sync', { detail: { unreadCount: this.unreadCount, readId: id } }));
                             }
                         } catch {
                             // Proceed to order page even if background mark-read fails.
                         } finally {
                             this.markingId = null;
                         }
                     }
                     if (orderUrl) {
                         window.location.assign(orderUrl);
                     }
                 },
                 markReadKeepalive(id, initialRead, markUrl) {
                     if (this.isRead(id, initialRead)) return;
                     this.readIds = { ...this.readIds, [id]: true };
                     this.unreadCount = Math.max(0, this.unreadCount - 1);
                     window.dispatchEvent(new CustomEvent('cena:notifications-sync', { detail: { unreadCount: this.unreadCount, readId: id } }));
                     fetch(markUrl, {
                         method: 'POST',
                         keepalive: true,
                         credentials: 'same-origin',
                         headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrf() },
                     }).catch(() => {});
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
                        @php($item = \App\Http\Resources\Customer\StoreNotificationResource::present($notification))
                        @php($formattedTime = $notification->created_at?->setTimezone($tz)->format('d/m/Y H:i') ?? '')
                        <div :class="isRead({{ $notification->id }}, {{ $initiallyRead ? 'true' : 'false' }}) ? 'bg-surface-card hover:bg-surface/60' : 'bg-accent/5 hover:bg-accent/10'"
                             class="px-4 py-3.5 transition-colors sm:px-5">
                            <button type="button"
                                    @click="openOrder({{ $notification->id }}, {{ $initiallyRead ? 'true' : 'false' }}, @js(route('notifications.api.read', $notification)), @js($item['url']))"
                                    :disabled="markingId === {{ $notification->id }}"
                                    class="group flex w-full items-start gap-3 rounded-lg text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                                <span class="mt-2 h-2 w-2 shrink-0 rounded-full"
                                      :class="isRead({{ $notification->id }}, {{ $initiallyRead ? 'true' : 'false' }}) ? 'bg-transparent' : 'bg-accent'"
                                      aria-hidden="true"></span>
                                <span class="h-12 w-12 shrink-0 overflow-hidden rounded-xl border border-border bg-surface">
                                    @if ($item['thumbnail_url'] !== null)
                                        <img src="{{ $item['thumbnail_url'] }}"
                                             alt="{{ $item['item_summary'] }}"
                                             class="h-full w-full object-cover"
                                             loading="lazy"
                                             decoding="async">
                                    @else
                                        <span class="flex h-full w-full items-center justify-center text-muted" aria-hidden="true">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                                        </span>
                                    @endif
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm leading-snug"
                                          :class="isRead({{ $notification->id }}, {{ $initiallyRead ? 'true' : 'false' }}) ? 'font-medium text-muted group-hover:text-main' : 'font-bold text-main'">
                                        {{ $item['headline'] }}
                                    </span>
                                    <span x-show="!isRead({{ $notification->id }}, {{ $initiallyRead ? 'true' : 'false' }})"
                                          @if ($initiallyRead) x-cloak @endif
                                          class="sr-only">Chưa đọc</span>
                                    <span class="mt-0.5 block break-words text-xs leading-snug text-muted">{{ $item['item_summary'] }}</span>
                                    <span class="mt-1 block text-xs text-muted/80" title="{{ $formattedTime }}">{{ $formattedTime }}</span>
                                </span>
                            </button>

                            @if ($item['review_cta'] !== null)
                                <div class="mt-2.5 flex flex-wrap items-center justify-between gap-2 pl-[4.5rem]">
                                    <p class="min-w-0 text-xs leading-snug text-muted">{{ $item['review_cta']['prompt'] }}</p>
                                    <a href="{{ $item['review_cta']['url'] }}"
                                       @click="markReadKeepalive({{ $notification->id }}, {{ $initiallyRead ? 'true' : 'false' }}, @js(route('notifications.api.read', $notification)))"
                                       class="btn-secondary inline-flex shrink-0 items-center px-3 py-1.5 text-xs font-semibold">
                                        {{ $item['review_cta']['label'] }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <x-ui.pagination :paginator="$notifications" />
        </div>
    </section>
@endsection
