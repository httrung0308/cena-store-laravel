export function notificationBell({
    initialUnreadCount = 0,
    recentUrl = '',
    readAllUrl = '',
    readUrlTemplate = '',
    timezone = 'Asia/Ho_Chi_Minh',
} = {}) {
    return {
        open: false,
        loading: false,
        markingAll: false,
        openingId: null,
        error: '',
        lastFailedAction: null,
        unreadCount: Number.isInteger(initialUnreadCount) ? Math.max(0, initialUnreadCount) : 0,
        items: [],
        _syncHandler: null,

        init() {
            this._syncHandler = (event) => {
                const detail = event?.detail ?? {};
                if (typeof detail.unreadCount === 'number') {
                    this.unreadCount = Math.max(0, detail.unreadCount);
                }
                if (detail.markAllRead) {
                    const now = new Date().toISOString();
                    this.items = this.items.map((item) => (
                        item.read_at ? item : { ...item, read_at: now }
                    ));
                } else if (detail.readId) {
                    const readAt = detail.readAt || new Date().toISOString();
                    this.items = this.items.map((item) => (
                        item.id === detail.readId && !item.read_at ? { ...item, read_at: readAt } : item
                    ));
                }
            };
            window.addEventListener('cena:notifications-sync', this._syncHandler);
        },

        destroy() {
            if (this._syncHandler) {
                window.removeEventListener('cena:notifications-sync', this._syncHandler);
            }
        },

        get badgeText() {
            return this.unreadCount > 99 ? '99+' : String(this.unreadCount);
        },

        get ariaLabel() {
            return this.unreadCount > 0 ? `Thông báo, ${this.unreadCount} chưa đọc` : 'Thông báo';
        },

        toggle() {
            if (this.open) {
                this.close();
                return;
            }
            this.open = true;
            this.fetchRecent();
        },

        close() {
            this.open = false;
        },

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        },

        async fetchRecent() {
            if (!recentUrl) {
                return;
            }
            this.loading = true;
            this.error = '';
            this.lastFailedAction = null;

            try {
                const response = await fetch(recentUrl, {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                let payload = null;
                try {
                    payload = await response.json();
                } catch {
                    payload = null;
                }

                if (!response.ok) {
                    this.error = response.status === 419
                        ? 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'
                        : 'Không tải được thông báo.';
                    this.lastFailedAction = 'fetchRecent';
                    return;
                }

                const data = payload?.data ?? {};
                if (typeof data.unread_count === 'number') {
                    this.unreadCount = Math.max(0, data.unread_count);
                }
                this.items = Array.isArray(data.notifications) ? data.notifications : [];
            } catch {
                this.error = 'Không kết nối được máy chủ.';
                this.lastFailedAction = 'fetchRecent';
            } finally {
                this.loading = false;
            }
        },

        async markAllRead() {
            if (this.markingAll || this.unreadCount === 0 || !readAllUrl) {
                return;
            }
            this.markingAll = true;
            this.error = '';
            this.lastFailedAction = null;

            try {
                const response = await fetch(readAllUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                });

                if (!response.ok) {
                    this.error = response.status === 419
                        ? 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'
                        : 'Không thể đánh dấu đã đọc.';
                    this.lastFailedAction = 'markAllRead';
                    return;
                }

                const now = new Date().toISOString();
                this.unreadCount = 0;
                this.items = this.items.map((item) => (
                    item.read_at ? item : { ...item, read_at: now }
                ));
                window.dispatchEvent(new CustomEvent('cena:notifications-sync', {
                    detail: { unreadCount: 0, markAllRead: true },
                }));
            } catch {
                this.error = 'Không kết nối được máy chủ.';
                this.lastFailedAction = 'markAllRead';
            } finally {
                this.markingAll = false;
            }
        },

        retry() {
            if (this.lastFailedAction === 'markAllRead') {
                return this.markAllRead();
            }
            return this.fetchRecent();
        },

        async openItem(item) {
            if (!item || this.openingId !== null) {
                return;
            }
            if (!item.read_at && readUrlTemplate) {
                this.openingId = item.id;
                const url = readUrlTemplate.replace('__ID__', String(item.id));
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': this.csrfToken(),
                        },
                    });
                    if (response.ok) {
                        const readAt = new Date().toISOString();
                        item.read_at = readAt;
                        this.unreadCount = Math.max(0, this.unreadCount - 1);
                        window.dispatchEvent(new CustomEvent('cena:notifications-sync', {
                            detail: { unreadCount: this.unreadCount, readId: item.id, readAt },
                        }));
                    }
                } catch {
                    // Proceed to order page even if background mark-read fails.
                } finally {
                    this.openingId = null;
                }
            }
            if (item.url) {
                window.location.assign(item.url);
            }
        },

        formatTime(isoString) {
            if (!isoString) {
                return '';
            }
            try {
                const date = new Date(isoString);
                if (Number.isNaN(date.getTime())) {
                    return '';
                }
                return new Intl.DateTimeFormat('vi-VN', {
                    timeZone: timezone,
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                }).format(date);
            } catch {
                return '';
            }
        },
    };
}
