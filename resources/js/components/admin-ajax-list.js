export function adminAjaxList({ regionSelector = '#admin-list-region', toolbarSelector = '.bg-surface-card.mb-6' } = {}) {
    return {
        listLoading: false,
        listSeq: 0,
        listController: null,

        init() {
            window.addEventListener('popstate', () => {
                this.fetchList(window.location.href, { pushHistory: false, syncForm: true });
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
            if (form) {
                form.querySelectorAll('input[type="text"], input[type="search"], select').forEach((el) => {
                    if (el.tagName === 'SELECT') {
                        el.selectedIndex = 0;
                    } else {
                        el.value = '';
                    }
                });
            }
            this.fetchList(baseUrl, { pushHistory: true, syncForm: false });
        },

        visitUrl(url) {
            this.fetchList(url, { pushHistory: true, syncForm: true });
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
            const form = document.querySelector('form[method="GET"]');
            if (!form) return;
            form.querySelectorAll('input[name], select[name]').forEach((el) => {
                const name = el.getAttribute('name');
                if (!name) return;
                const val = url.searchParams.get(name) ?? '';
                if (el.tagName === 'SELECT' || el.type === 'text' || el.type === 'search') {
                    el.value = val;
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

            try {
                if (pushHistory && window.location.href !== url) {
                    window.history.pushState({}, '', url);
                }
                if (syncForm) {
                    this.syncFormFromUrl(url);
                }

                const response = await fetch(url, {
                    method: 'GET',
                    signal: controller.signal,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'text/html',
                    },
                });
                if (!response.ok || seq !== this.listSeq) return;

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
                    window.location.href = url;
                }
            } finally {
                if (seq === this.listSeq) {
                    this.listLoading = false;
                }
            }
        },
    };
}
