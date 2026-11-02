// Cart drawer state (WF-09). The server is the only source of prices, stock and quantities:
// nothing changes on screen until the server answers, and only one change is sent at a time.

const MESSAGES = {
    stale: 'Giỏ hàng đã thay đổi ở nơi khác. Đã cập nhật lại, vui lòng kiểm tra.',
    session: 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.',
    tooMany: 'Bạn thao tác quá nhanh. Vui lòng thử lại sau ít giây.',
    missing: 'Sản phẩm không còn trong giỏ. Đã tải lại giỏ hàng.',
    network: 'Không kết nối được máy chủ. Vui lòng thử lại.',
    generic: 'Có lỗi xảy ra. Vui lòng thử lại.',
    busy: 'Đang xử lý thao tác trước, vui lòng đợi.',
    chooseSize: 'Vui lòng chọn size trước khi thêm vào giỏ.',
};

function firstError(body) {
    const errors = body && typeof body.errors === 'object' && body.errors !== null ? Object.values(body.errors) : [];
    const first = errors.flat().find((value) => typeof value === 'string' && value !== '');
    return first || (body && typeof body.message === 'string' ? body.message : MESSAGES.generic);
}

export function formatVnd(amount) {
    return `${new Intl.NumberFormat('vi-VN').format(Number.isInteger(amount) ? amount : 0)} ₫`;
}

export function cartStore({ fetcher = globalThis.fetch?.bind(globalThis), csrfToken = '', urls = {}, timeout = 10000 } = {}) {
    return {
        items: [],
        count: 0,
        subtotal: 0,
        version: 0,
        notices: [],
        checkoutNote: '',
        loaded: false,
        pending: null,
        message: '',
        error: '',
        urls: { show: '/cart', add: '/cart/items', item: '/cart/items/__ID__', clear: '/cart/clear', ...urls },

        configure(newUrls = {}, token = csrfToken) {
            this.urls = { ...this.urls, ...newUrls };
            csrfToken = token;
        },

        apply(data) {
            if (!data || !Array.isArray(data.items)) return;
            this.items = data.items;
            this.count = Number.isInteger(data.count) ? data.count : 0;
            this.subtotal = Number.isInteger(data.subtotal) ? data.subtotal : 0;
            this.version = Number.isInteger(data.version) ? data.version : 0;
            this.notices = Array.isArray(data.notices) ? data.notices.filter((n) => typeof n === 'string') : [];
            this.checkoutNote = typeof data.checkout_note === 'string' ? data.checkout_note : '';
            this.loaded = true;
        },

        isBusy(key = null) {
            return this.pending !== null && (key === null || this.pending === key);
        },

        async send(method, url, body) {
            const controller = new AbortController();
            const timer = setTimeout(() => controller.abort(), timeout);
            try {
                const response = await fetcher(url, {
                    method,
                    signal: controller.signal,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: body === undefined ? undefined : JSON.stringify(body),
                });
                let payload = null;
                try { payload = await response.json(); } catch { payload = null; }
                return { status: response.status, ok: response.ok, body: payload };
            } catch {
                return { status: 0, ok: false, body: null };
            } finally {
                clearTimeout(timer);
            }
        },

        async load() {
            const result = await this.send('GET', this.urls.show);
            if (result.ok) {
                this.apply(result.body?.data);
                return true;
            }
            this.error = result.status === 0 ? MESSAGES.network : MESSAGES.generic;
            return false;
        },

        async mutate(key, method, url, body) {
            if (this.pending !== null) {
                this.error = MESSAGES.busy;
                return false;
            }
            this.pending = key;
            this.error = '';
            this.message = '';
            try {
                const result = await this.send(method, url, body);
                if (result.ok) {
                    this.apply(result.body?.data);
                    this.message = typeof result.body?.message === 'string' ? result.body.message : '';
                    return true;
                }
                if (result.status === 409 && result.body?.code === 'CART_STALE') {
                    this.apply(result.body?.data);
                    this.error = MESSAGES.stale;
                } else if (result.status === 401 || result.status === 419) {
                    this.error = MESSAGES.session;
                } else if (result.status === 429) {
                    this.error = MESSAGES.tooMany;
                } else if (result.status === 404) {
                    this.error = MESSAGES.missing;
                    await this.load();
                } else if (result.status === 0) {
                    this.error = MESSAGES.network;
                } else {
                    this.error = firstError(result.body);
                }
                return false;
            } finally {
                this.pending = null;
            }
        },

        add(variantId, quantity = 1) {
            if (!Number.isInteger(variantId) || variantId < 1) {
                this.error = MESSAGES.chooseSize;
                return Promise.resolve(false);
            }
            return this.mutate(`add-${variantId}`, 'POST', this.urls.add, { variant_id: variantId, quantity, version: this.version });
        },

        update(item, quantity) {
            if (!item || !Number.isInteger(quantity) || quantity < 1 || quantity === item.quantity) return Promise.resolve(false);
            return this.mutate(`item-${item.id}`, 'PATCH', this.urls.item.replace('__ID__', String(item.id)), { quantity, version: this.version });
        },

        remove(item) {
            if (!item) return Promise.resolve(false);
            return this.mutate(`item-${item.id}`, 'DELETE', this.urls.item.replace('__ID__', String(item.id)), { version: this.version });
        },

        clear() {
            if (this.items.length === 0) return Promise.resolve(false);
            return this.mutate('clear', 'POST', this.urls.clear, { version: this.version });
        },

        price: formatVnd,
    };
}

/**
 * Any button with data-add-to-cart and data-variant-id (product page, PR-X4) adds that variant.
 * data-quantity is optional. On success the "cart-open" window event opens the drawer; a button that also
 * carries data-buy-now="<same-origin path>" goes there instead ("Mua ngay" → checkout).
 */
export function bindAddToCartButtons(store, root = globalThis.document, target = globalThis.window) {
    root?.addEventListener('click', async (event) => {
        const button = event.target?.closest?.('[data-add-to-cart]');
        if (!button || button.disabled) return;
        event.preventDefault();
        const variantId = Number.parseInt(button.dataset.variantId ?? '', 10);
        const quantity = Number.parseInt(button.dataset.quantity ?? '1', 10);
        const buyNow = typeof button.dataset.buyNow === 'string' && button.dataset.buyNow.startsWith('/') && !button.dataset.buyNow.startsWith('//')
            ? button.dataset.buyNow
            : null;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        let added = false;
        try {
            added = await store.add(Number.isInteger(variantId) ? variantId : 0, Number.isInteger(quantity) ? quantity : 1);
        } finally {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            if (added === true && buyNow !== null) {
                target?.location?.assign(buyNow);
            } else {
                target?.dispatchEvent(new CustomEvent('cart-open'));
            }
        }
    });
}
