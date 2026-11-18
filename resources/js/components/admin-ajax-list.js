export function adminAjaxList({ regionSelector = '#admin-list-region', toolbarSelector = '.bg-surface-card.mb-6' } = {}) {
    return {
        listLoading: false,
        listError: null,
        lastFailedUrl: null,
        listSeq: 0,
        listController: null,

        init() {
            this.syncSelectHighlights();
            window.addEventListener('popstate', () => {
                this.fetchList(window.location.href, { pushHistory: false, syncForm: true });
            });
        },

        getFilterForm() {
            const toolbar = document.querySelector(toolbarSelector);
            if (toolbar) {
                if (toolbar.matches('form')) {
                    return toolbar;
                }
                const nestedForm = toolbar.querySelector('form[method="GET"], form');
                if (nestedForm) {
                    return nestedForm;
                }
            }
            return document.querySelector('form[method="GET"]');
        },

        syncSelectHighlights() {
            const form = this.getFilterForm();
            if (!form) return;
            form.querySelectorAll('select[name]').forEach((el) => {
                const isActive = el.value !== '';
                el.classList.toggle('border-accent/60', isActive);
                el.classList.toggle('bg-accent/5', isActive);
                el.classList.toggle('text-accent', isActive);
                el.classList.toggle('font-bold', isActive);
                el.classList.toggle('border-border', !isActive);
                el.classList.toggle('bg-surface-card', !isActive);
                el.classList.toggle('text-main', !isActive);
                el.classList.toggle('font-semibold', !isActive);
            });
        },

        submitFilter(form) {
            if (!form) return;
            const url = new URL(form.action || window.location.pathname, window.location.origin);
            const data = new FormData(form);
            url.search = '';
            for (const [key, val] of data.entries()) {
                if (typeof val === 'string' && val.trim() !== '') {
                    url.searchParams.append(key, val.trim());
                }
            }
            this.fetchList(url.toString(), { pushHistory: true, syncForm: false });
        },

        clearField(fieldName, form) {
            if (!form) return;
            const input = form.querySelector(`[name="${fieldName}"]`);
            if (input) {
                input.value = '';
            }
            this.submitFilter(form);
        },

        resetFilters(baseUrl, form) {
            const targetForm = form || this.getFilterForm();
            if (targetForm) {
                targetForm.querySelectorAll('input[type="text"], input[type="search"], input[type="date"], input[type="number"], select').forEach((el) => {
                    if (el.tagName === 'SELECT') {
                        el.selectedIndex = 0;
                    } else {
                        el.value = '';
                    }
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                });
            }
            this.fetchList(baseUrl, { pushHistory: true, syncForm: false });
        },

        visitUrl(url) {
            this.fetchList(url, { pushHistory: true, syncForm: true });
        },

        retryLastFetch() {
            if (this.lastFailedUrl) {
                this.fetchList(this.lastFailedUrl, { pushHistory: false, syncForm: false });
            }
        },

        handleRegionClick(event) {
            const link = event.target.closest('a[href]');
            if (!link) return;
            const href = link.getAttribute('href') || '';
            if (!href.includes('page=')) return;
            event.preventDefault();
            this.fetchList(link.href, { pushHistory: true, syncForm: false });
        },

        syncFormFromUrl(urlStr) {
            const url = new URL(urlStr, window.location.origin);
            const form = this.getFilterForm();
            if (!form) return;
            form.querySelectorAll('input[name], select[name]').forEach((el) => {
                const name = el.getAttribute('name');
                if (!name) return;
                const val = url.searchParams.get(name) ?? '';
                if (el.tagName === 'SELECT' || el.type === 'text' || el.type === 'search' || el.type === 'date' || el.type === 'number') {
                    el.value = val;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        },

        async fetchList(url, { pushHistory = true, syncForm = false } = {}) {
            if (this.listController) {
                this.listController.abort();
            }
            const controller = new AbortController();
            this.listController = controller;
            const seq = ++this.listSeq;
            this.listLoading = true;
            this.listError = null;
            this.lastFailedUrl = null;

            try {
                if (pushHistory && window.location.href !== url) {
                    window.history.pushState({}, '', url);
                }
                if (syncForm) {
                    this.syncFormFromUrl(url);
                }
                this.syncSelectHighlights();

                const response = await fetch(url, {
                    method: 'GET',
                    signal: controller.signal,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'text/html',
                    },
                });
                if (seq !== this.listSeq) return;
                if (!response.ok) {
                    this.listError = 'Không thể tải dữ liệu. Vui lòng thử lại.';
                    this.lastFailedUrl = url;
                    return;
                }

                if (response.redirected && response.url && response.url !== window.location.href) {
                    window.history.replaceState({}, '', response.url);
                }

                const html = await response.text();
                if (seq !== this.listSeq) return;

                const parsed = new DOMParser().parseFromString(html, 'text/html');
                const nextRegion = parsed.querySelector(regionSelector);
                const currentRegion = document.querySelector(regionSelector);
                if (nextRegion && currentRegion) {
                    currentRegion.innerHTML = nextRegion.innerHTML;
                }

                const nextChips = parsed.querySelector('#admin-filter-chips');
                const currentChips = document.querySelector('#admin-filter-chips');
                if (nextChips && currentChips) {
                    currentChips.innerHTML = nextChips.innerHTML;
                } else if (!nextChips && currentChips) {
                    currentChips.remove();
                } else if (nextChips && !currentChips) {
                    const toolbar = document.querySelector(toolbarSelector);
                    if (toolbar) {
                        toolbar.appendChild(nextChips);
                    }
                }
            } catch (error) {
                if (error?.name !== 'AbortError') {
                    this.listError = 'Không thể kết nối máy chủ. Vui lòng thử lại.';
                    this.lastFailedUrl = url;
                }
            } finally {
                if (seq === this.listSeq) {
                    this.listLoading = false;
                }
            }
        },
    };
}
