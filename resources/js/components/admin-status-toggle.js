export function adminStatusToggle({ regionSelector = '#admin-list-region' } = {}) {
    return {
        pendingKey: null,
        statusMessage: '',
        errorMessage: '',
        refreshSeq: 0,
        refreshController: null,

        isBusy(key = null) {
            return this.pendingKey !== null && (key === null || this.pendingKey === key);
        },

        async submitToggle(event, key = 'action') {
            const form = event?.target?.closest?.('form');
            if (!form) return;

            if (this.pendingKey !== null) {
                return;
            }

            this.pendingKey = key;
            this.errorMessage = '';
            this.statusMessage = '';

            const button = form.querySelector('button[type="submit"], button:not([type])');
            if (button) {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
            }

            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: new FormData(form),
                });

                let body = null;
                try {
                    body = await response.json();
                } catch {
                    body = null;
                }

                if (!response.ok) {
                    const errors = body?.errors && typeof body.errors === 'object' ? Object.values(body.errors).flat() : [];
                    const firstError = errors.find((msg) => typeof msg === 'string' && msg !== '');
                    if (response.status === 419) {
                        this.errorMessage = 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.';
                    } else if (response.status === 429) {
                        this.errorMessage = 'Bạn thao tác quá nhanh. Vui lòng đợi ít giây rồi thử lại.';
                    } else if (response.status === 403) {
                        this.errorMessage = 'Bạn không có quyền thực hiện thao tác này.';
                    } else {
                        this.errorMessage = firstError || body?.message || 'Không thể cập nhật trạng thái. Vui lòng thử lại.';
                    }
                    return;
                }

                if (typeof body?.message === 'string' && body.message !== '') {
                    this.statusMessage = body.message;
                    window.dispatchEvent(new CustomEvent('cena-toast', {
                        detail: { message: body.message, type: 'success' },
                    }));
                }

                await this.refreshRegion();
            } catch {
                this.errorMessage = 'Không kết nối được máy chủ. Trạng thái chưa thay đổi, vui lòng thử lại.';
            } finally {
                if (button) {
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                }
                this.pendingKey = null;
            }
        },

        async refreshRegion() {
            if (this.refreshController) {
                this.refreshController.abort();
            }
            const controller = new AbortController();
            this.refreshController = controller;
            const seq = ++this.refreshSeq;

            try {
                const response = await fetch(window.location.href, {
                    method: 'GET',
                    signal: controller.signal,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'text/html',
                    },
                });
                if (!response.ok || seq !== this.refreshSeq) return;

                if (response.redirected && response.url && response.url !== window.location.href) {
                    window.history.replaceState({}, '', response.url);
                }

                const html = await response.text();
                if (seq !== this.refreshSeq) return;

                const parsed = new DOMParser().parseFromString(html, 'text/html');
                const nextRegion = parsed.querySelector(regionSelector);
                const currentRegion = this.$refs.region || document.querySelector(regionSelector);
                if (nextRegion && currentRegion) {
                    currentRegion.innerHTML = nextRegion.innerHTML;
                }
            } catch (error) {
                if (error?.name === 'AbortError') return;
                // Keep optimistic/confirmed fallback message; user can refresh if needed
            }
        },
    };
}
