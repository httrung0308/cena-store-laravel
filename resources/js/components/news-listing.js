export function newsListing({ baseUrl = '/news', initialSearch = '' } = {}) {
    return {
        baseUrl,
        search: initialSearch,
        loading: false,
        errorMessage: '',
        lastFailedUrl: null,
        requestSeq: 0,
        abortController: null,
        debounceTimer: null,
        _popStateHandler: null,

        init() {
            this._popStateHandler = () => {
                const targetUrl = `${window.location.pathname}${window.location.search}`;
                const parsed = new URL(targetUrl, window.location.origin);
                this.search = parsed.searchParams.get('search') ?? '';
                this.fetchUrl(targetUrl, { push: false, scroll: false });
            };
            window.addEventListener('popstate', this._popStateHandler);
        },

        destroy() {
            if (this._popStateHandler) {
                window.removeEventListener('popstate', this._popStateHandler);
            }
            if (this.abortController) {
                this.abortController.abort();
            }
            clearTimeout(this.debounceTimer);
        },

        buildUrl(resetPage = true) {
            const target = new URL(this.baseUrl, window.location.origin);
            const params = new URLSearchParams(resetPage ? '' : window.location.search);
            const q = String(this.search || '').trim();
            if (q !== '') {
                params.set('search', q);
            } else {
                params.delete('search');
            }
            if (resetPage) {
                params.delete('page');
            }
            const qs = params.toString();
            return qs ? `${target.pathname}?${qs}` : target.pathname;
        },

        onSearchInput() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.submitSearch();
            }, 250);
        },

        submitSearch() {
            clearTimeout(this.debounceTimer);
            const nextUrl = this.buildUrl(true);
            this.fetchUrl(nextUrl, { push: true, scroll: false });
        },

        clearSearch() {
            this.search = '';
            this.submitSearch();
            this.$nextTick(() => this.$refs.searchInput?.focus());
        },

        handleResultsClick(event) {
            const resetBtn = event.target.closest('[data-news-reset]');
            if (resetBtn) {
                event.preventDefault();
                this.clearSearch();
                return;
            }
            const anchor = event.target.closest('nav[aria-label="Phân trang"] a[href]');
            if (!anchor) return;
            event.preventDefault();
            const target = new URL(anchor.getAttribute('href'), window.location.origin);
            const relativeUrl = `${target.pathname}${target.search}`;
            this.search = target.searchParams.get('search') ?? '';
            this.fetchUrl(relativeUrl, { push: true, scroll: true });
        },

        retryLastRequest() {
            if (!this.lastFailedUrl) return;
            this.fetchUrl(this.lastFailedUrl, { push: false, scroll: false });
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
                        'X-News-Partial': '1',
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'text/html',
                    },
                });

                if (seq !== this.requestSeq) return;

                if (!response.ok) {
                    this.errorMessage = response.status === 422
                        ? 'Từ khóa tìm kiếm chưa hợp lệ (tối đa 150 ký tự).'
                        : 'Không thể tải danh sách bài viết. Vui lòng thử lại.';
                    return;
                }

                const html = await response.text();
                if (seq !== this.requestSeq) return;

                const parsed = new DOMParser().parseFromString(html, 'text/html');
                const nextRegion = parsed.querySelector('#news-results-region');
                if (!nextRegion || !this.$refs.resultsRegion) {
                    this.errorMessage = 'Không đọc được dữ liệu phản hồi từ máy chủ.';
                    return;
                }

                this.$refs.resultsRegion.innerHTML = nextRegion.innerHTML;

                if (push) {
                    const current = `${window.location.pathname}${window.location.search}`;
                    if (current !== url) {
                        window.history.pushState({ newsUrl: url }, '', url);
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
