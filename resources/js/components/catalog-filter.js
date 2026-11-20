function readArrayParam(params, baseName) {
    const pattern = new RegExp(`^${baseName}(?:\\[\\d*\\])?$`);
    const values = [];

    for (const [key, value] of params.entries()) {
        if (pattern.test(key) && value !== '') {
            values.push(value);
        }
    }

    return values;
}

export function catalogListing(initial = {}) {
    return {
        baseUrl: initial.baseUrl || '/shoes',
        openFacet: null,
        drawerOpen: false,
        loading: false,
        errorMessage: '',
        lastFailedUrl: null,
        hasSearch: false,
        counts: {
            silhouette: 0,
            activity: 0,
            colors: 0,
            category: 0,
            sizePrice: 0,
            flags: 0,
            total: 0,
        },
        facetSearch: {
            silhouette: '',
            activity: '',
        },
        requestSeq: 0,
        abortController: null,
        debounceTimer: null,
        _popStateHandler: null,

        init() {
            this.syncCountsFromForm();
            this._popStateHandler = () => {
                const targetUrl = `${window.location.pathname}${window.location.search}`;
                this.syncFormFromUrl(targetUrl);
                this.fetchUrl(targetUrl, { push: false, scroll: false });
            };
            window.addEventListener('popstate', this._popStateHandler);
            this.$watch('drawerOpen', (open) => {
                document.documentElement.classList.toggle('overflow-hidden', open);
            });
        },

        destroy() {
            if (this._popStateHandler) {
                window.removeEventListener('popstate', this._popStateHandler);
            }
            if (this.abortController) {
                this.abortController.abort();
            }
            clearTimeout(this.debounceTimer);
            document.documentElement.classList.remove('overflow-hidden');
        },

        toggleFacet(name) {
            this.openFacet = this.openFacet === name ? null : name;
        },

        closePopovers() {
            this.openFacet = null;
        },

        openDrawer() {
            this.openFacet = null;
            this.drawerOpen = true;
            this.$nextTick(() => this.$refs.drawerClose?.focus());
        },

        closeDrawer(restoreFocus = true) {
            this.drawerOpen = false;
            if (restoreFocus) {
                this.$nextTick(() => this.$refs.drawerTrigger?.focus());
            }
        },

        syncCountsFromForm() {
            const form = this.$refs.form;
            if (!form) return;

            const data = new FormData(form);
            const search = String(data.get('search') || '').trim();
            const gender = String(data.get('gender') || '').trim();
            const category = String(data.get('category') || '').trim();
            const silhouettes = data.getAll('silhouette[]').filter(Boolean);
            const activities = data.getAll('activity[]').filter(Boolean);
            const colors = data.getAll('colors[]').filter(Boolean);
            const size = String(data.get('size') || '').trim();
            const minPrice = String(data.get('min_price') || '').trim();
            const maxPrice = String(data.get('max_price') || '').trim();
            const promotion = data.get('promotion') !== null;
            const inStock = data.get('in_stock') !== null;

            const sizePriceCount = (size !== '' ? 1 : 0) + (minPrice !== '' ? 1 : 0) + (maxPrice !== '' ? 1 : 0);
            const flagsCount = (promotion ? 1 : 0) + (inStock ? 1 : 0);

            this.hasSearch = search !== '';
            this.counts = {
                silhouette: silhouettes.length,
                activity: activities.length,
                colors: colors.length,
                category: category !== '' ? 1 : 0,
                sizePrice: sizePriceCount,
                flags: flagsCount,
                total:
                    (search !== '' ? 1 : 0) +
                    (gender !== '' ? 1 : 0) +
                    (category !== '' ? 1 : 0) +
                    silhouettes.length +
                    activities.length +
                    colors.length +
                    sizePriceCount +
                    flagsCount,
            };
        },

        syncFormFromUrl(urlStr) {
            const form = this.$refs.form;
            if (!form) return;

            const parsed = new URL(urlStr, window.location.origin);
            const params = parsed.searchParams;

            const search = params.get('search') ?? '';
            const gender = params.get('gender') ?? '';
            const category = params.get('category') ?? '';
            const silhouettes = new Set(readArrayParam(params, 'silhouette'));
            const activities = new Set(readArrayParam(params, 'activity'));
            const colors = new Set(readArrayParam(params, 'colors'));
            const size = params.get('size') ?? '';
            const minPrice = params.get('min_price') ?? '';
            const maxPrice = params.get('max_price') ?? '';
            const promotion = ['1', 'true'].includes((params.get('promotion') ?? '').toLowerCase());
            const inStock = ['1', 'true'].includes((params.get('in_stock') ?? '').toLowerCase());
            const sort = params.get('sort') || 'newest';

            for (const el of form.elements) {
                if (!el.name) continue;
                switch (el.name) {
                    case 'search':
                        el.value = search;
                        break;
                    case 'gender':
                        el.checked = el.value === gender;
                        break;
                    case 'category':
                        el.value = category;
                        break;
                    case 'silhouette[]':
                        el.checked = silhouettes.has(el.value);
                        break;
                    case 'activity[]':
                        el.checked = activities.has(el.value);
                        break;
                    case 'colors[]':
                        el.checked = colors.has(el.value);
                        break;
                    case 'size':
                        el.value = size;
                        break;
                    case 'min_price':
                        el.value = minPrice;
                        break;
                    case 'max_price':
                        el.value = maxPrice;
                        break;
                    case 'promotion':
                        el.checked = promotion;
                        break;
                    case 'in_stock':
                        el.checked = inStock;
                        break;
                    case 'sort':
                        el.value = sort;
                        break;
                }
            }

            this.syncCountsFromForm();
        },

        buildQueryUrlFromForm(resetPage = true) {
            const form = this.$refs.form;
            const target = new URL(this.baseUrl, window.location.origin);
            if (!form) {
                return `${target.pathname}${target.search}`;
            }

            const raw = new FormData(form);
            const params = new URLSearchParams();

            for (const [key, val] of raw.entries()) {
                const value = String(val).trim();
                if (value === '') continue;
                if (resetPage && key === 'page') continue;
                if (key === 'sort' && value === 'newest') continue;
                params.append(key, value);
            }

            const query = params.toString();
            return query ? `${target.pathname}?${query}` : target.pathname;
        },

        onControlAutoChange(delayMs = 180) {
            this.syncCountsFromForm();
            if (this.drawerOpen) {
                return;
            }
            this.scheduleSubmit(delayMs);
        },

        scheduleSubmit(delayMs = 180) {
            clearTimeout(this.debounceTimer);
            if (delayMs <= 0) {
                this.submitForm(false);
                return;
            }
            this.debounceTimer = setTimeout(() => {
                this.submitForm(false);
            }, delayMs);
        },

        submitForm(closeDrawerAfter = false) {
            clearTimeout(this.debounceTimer);
            this.syncCountsFromForm();
            const nextUrl = this.buildQueryUrlFromForm(true);
            this.fetchUrl(nextUrl, { push: true, scroll: false });
            if (closeDrawerAfter && this.drawerOpen) {
                this.closeDrawer(false);
            }
        },

        clearSearchAndSubmit() {
            const input = this.$refs.form?.querySelector('input[name="search"]');
            if (input) {
                input.value = '';
            }
            this.submitForm(false);
        },

        clearSizePriceAndSubmit() {
            const form = this.$refs.form;
            if (!form) return;
            for (const name of ['size', 'min_price', 'max_price']) {
                const field = form.elements.namedItem(name);
                if (field) field.value = '';
            }
            this.openFacet = null;
            this.submitForm(false);
        },

        navigateUrl(url, { push = true, scroll = false } = {}) {
            clearTimeout(this.debounceTimer);
            this.openFacet = null;
            if (this.drawerOpen) {
                this.closeDrawer(false);
            }
            const target = new URL(url, window.location.origin);
            const relativeUrl = `${target.pathname}${target.search}`;
            this.syncFormFromUrl(relativeUrl);
            this.fetchUrl(relativeUrl, { push, scroll });
        },

        handleResultsClick(event) {
            const anchor = event.target.closest('a[href]');
            if (!anchor) return;

            if (anchor.hasAttribute('data-filter-reset') || anchor.closest('nav[aria-label="Phân trang"]')) {
                event.preventDefault();
                this.navigateUrl(anchor.getAttribute('href'), {
                    push: true,
                    scroll: Boolean(anchor.closest('nav[aria-label="Phân trang"]')),
                });
            }
        },

        matchesFacetSearch(facet, label) {
            const needle = (this.facetSearch[facet] || '').trim().toLowerCase();
            if (!needle) return true;
            return String(label || '').toLowerCase().includes(needle);
        },

        retryLastRequest() {
            if (!this.lastFailedUrl) return;
            this.fetchUrl(this.lastFailedUrl, { push: true, scroll: false });
        },

        async fetchUrl(url, { push = true, scroll = false } = {}) {
            if (this.abortController) {
                this.abortController.abort();
            }

            const controller = new AbortController();
            this.abortController = controller;
            const seq = ++this.requestSeq;

            this.loading = true;
            this.errorMessage = '';
            this.lastFailedUrl = url;

            try {
                const response = await fetch(url, {
                    method: 'GET',
                    signal: controller.signal,
                    credentials: 'same-origin',
                    headers: {
                        'X-Catalog-Partial': '1',
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'text/html, application/json',
                    },
                });

                if (seq !== this.requestSeq) return;

                if (!response.ok) {
                    let message = 'Không thể tải danh sách sản phẩm theo điều kiện vừa chọn.';
                    const contentType = response.headers.get('content-type') || '';
                    if (contentType.includes('application/json')) {
                        const body = await response.json().catch(() => null);
                        if (body?.errors && typeof body.errors === 'object') {
                            const firstError = Object.values(body.errors).flat().find((msg) => typeof msg === 'string' && msg !== '');
                            if (firstError) message = firstError;
                        } else if (typeof body?.message === 'string' && body.message !== '') {
                            message = body.message;
                        }
                    } else if (response.status === 422) {
                        message = 'Giá trị bộ lọc chưa hợp lệ (ví dụ size hoặc khoảng giá). Vui lòng kiểm tra lại.';
                    }
                    this.errorMessage = message;
                    return;
                }

                const html = await response.text();
                if (seq !== this.requestSeq) return;

                const parsed = new DOMParser().parseFromString(html, 'text/html');
                const nextChips = parsed.querySelector('#catalog-chips-region');
                const nextResults = parsed.querySelector('#catalog-results-region');

                if (!nextResults) {
                    this.errorMessage = 'Không đọc được dữ liệu phản hồi từ máy chủ.';
                    return;
                }

                if (nextChips && this.$refs.chipsRegion) {
                    this.$refs.chipsRegion.innerHTML = nextChips.innerHTML;
                }
                if (this.$refs.resultsRegion) {
                    this.$refs.resultsRegion.innerHTML = nextResults.innerHTML;
                }

                if (push) {
                    const current = `${window.location.pathname}${window.location.search}`;
                    if (current !== url) {
                        window.history.pushState({ catalogUrl: url }, '', url);
                    }
                }

                if (scroll && this.$refs.root) {
                    this.$refs.root.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            } catch (error) {
                if (error?.name === 'AbortError' || seq !== this.requestSeq) {
                    return;
                }
                this.errorMessage = 'Mất kết nối hoặc máy chủ tạm thời không phản hồi. Vui lòng thử lại.';
            } finally {
                if (seq === this.requestSeq) {
                    this.loading = false;
                }
            }
        },
    };
}
