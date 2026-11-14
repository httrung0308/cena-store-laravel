function normalizeSearchText(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .toLowerCase()
        .trim();
}

export function adminProductFilters({
    baseUrl,
    initialFilters = {},
    options = {},
    regionSelector = '#admin-list-region',
} = {}) {
    const genderMap = new Map((options.genders ?? []).map((item) => [String(item.value), item.label]));
    const silhouetteMap = new Map((options.silhouettes ?? []).map((item) => [Number(item.id), item.label]));
    const activityMap = new Map((options.activities ?? []).map((item) => [Number(item.id), item.label]));

    return {
        baseUrl,
        regionSelector,
        search: initialFilters.search ?? '',
        appliedSearch: initialFilters.search ?? '',
        genders: (initialFilters.genders ?? []).map(String),
        silhouettes: (initialFilters.silhouettes ?? []).map(Number),
        activities: (initialFilters.activities ?? []).map(Number),
        status: initialFilters.status ?? '',
        stock: initialFilters.stock ?? '',
        featured: initialFilters.featured ?? '',
        openDropdown: null,
        dropdownSearch: {
            silhouettes: '',
            activities: '',
        },
        mobileFiltersOpen: Boolean(
            (initialFilters.genders ?? []).length > 0 ||
            (initialFilters.silhouettes ?? []).length > 0 ||
            (initialFilters.activities ?? []).length > 0 ||
            initialFilters.status ||
            initialFilters.stock ||
            initialFilters.featured
        ),
        filterLoading: false,
        filterSeq: 0,
        filterController: null,

        init() {
            this._onPopState = () => {
                this.syncFromUrl();
                this.fetchRegion(window.location.href, { pushState: false });
            };
            window.addEventListener('popstate', this._onPopState);
        },

        destroy() {
            if (this._onPopState) {
                window.removeEventListener('popstate', this._onPopState);
            }
        },

        activeAdvancedCount() {
            return (
                this.genders.length +
                this.silhouettes.length +
                this.activities.length +
                (this.status ? 1 : 0) +
                (this.stock ? 1 : 0) +
                (this.featured ? 1 : 0)
            );
        },

        hasAnyFilter() {
            return this.appliedSearch.trim() !== '' || this.activeAdvancedCount() > 0;
        },

        activeChips() {
            const chips = [];
            const q = this.appliedSearch.trim();
            if (q !== '') {
                chips.push({
                    key: `search:${q}`,
                    type: 'search',
                    groupLabel: 'Từ khóa',
                    label: `"${q}"`,
                });
            }
            this.genders.forEach((val) => {
                chips.push({
                    key: `gender:${val}`,
                    type: 'genders',
                    value: val,
                    groupLabel: 'Đối tượng',
                    label: genderMap.get(String(val)) ?? val,
                });
            });
            this.silhouettes.forEach((id) => {
                chips.push({
                    key: `silhouette:${id}`,
                    type: 'silhouettes',
                    value: id,
                    groupLabel: 'Kiểu dáng',
                    label: silhouetteMap.get(Number(id)) ?? `#${id}`,
                });
            });
            this.activities.forEach((id) => {
                chips.push({
                    key: `activity:${id}`,
                    type: 'activities',
                    value: id,
                    groupLabel: 'Mục đích',
                    label: activityMap.get(Number(id)) ?? `#${id}`,
                });
            });
            if (this.status) {
                chips.push({
                    key: `status:${this.status}`,
                    type: 'status',
                    groupLabel: 'Trạng thái',
                    label: this.status === 'active' ? 'Đang bán' : 'Ngừng bán',
                });
            }
            if (this.stock) {
                chips.push({
                    key: `stock:${this.stock}`,
                    type: 'stock',
                    groupLabel: 'Tồn kho',
                    label: this.stock === 'in_stock' ? 'Còn hàng' : 'Hết hàng',
                });
            }
            if (this.featured) {
                chips.push({
                    key: `featured:${this.featured}`,
                    type: 'featured',
                    groupLabel: 'Nổi bật',
                    label: this.featured === 'featured' ? 'Nổi bật' : 'Không nổi bật',
                });
            }
            return chips;
        },

        removeChip(chip) {
            if (!chip) return;
            if (chip.type === 'search') {
                this.search = '';
                this.appliedSearch = '';
                this.applyFilters();
                return;
            }
            if (chip.type === 'genders' || chip.type === 'silhouettes' || chip.type === 'activities') {
                this.toggleSelection(chip.type, chip.value);
                return;
            }
            if (chip.type === 'status' || chip.type === 'stock' || chip.type === 'featured') {
                this[chip.type] = '';
                this.applyFilters();
            }
        },

        toggleDropdown(name) {
            this.openDropdown = this.openDropdown === name ? null : name;
            if (this.openDropdown && this.dropdownSearch[name] !== undefined) {
                this.$nextTick(() => {
                    this.$refs[`${name}Search`]?.focus?.();
                });
            }
        },

        closeDropdown() {
            this.openDropdown = null;
        },

        matchesDropdownSearch(name, label) {
            const q = normalizeSearchText(this.dropdownSearch[name] ?? '');
            if (!q) return true;
            return normalizeSearchText(label).includes(q);
        },

        isSelected(key, value) {
            const target = key === 'genders' ? String(value) : Number(value);
            return this[key].includes(target);
        },

        toggleSelection(key, value) {
            const target = key === 'genders' ? String(value) : Number(value);
            if (this[key].includes(target)) {
                this[key] = this[key].filter((item) => item !== target);
            } else {
                this[key] = [...this[key], target];
            }
            this.applyFilters();
        },

        clearGroup(key) {
            this[key] = [];
            this.applyFilters();
        },

        buildUrl() {
            const params = new URLSearchParams();
            const trimmedSearch = this.search.trim();
            if (trimmedSearch !== '') {
                params.set('search', trimmedSearch);
            }
            this.genders.forEach((val) => params.append('genders[]', val));
            this.silhouettes.forEach((id) => params.append('silhouettes[]', String(id)));
            this.activities.forEach((id) => params.append('activities[]', String(id)));
            if (this.status) params.set('status', this.status);
            if (this.stock) params.set('stock', this.stock);
            if (this.featured) params.set('featured', this.featured);

            const qs = params.toString();
            return qs ? `${this.baseUrl}?${qs}` : this.baseUrl;
        },

        applyFilters() {
            this.appliedSearch = this.search.trim();
            const nextUrl = this.buildUrl();
            this.fetchRegion(nextUrl, { pushState: true });
        },

        clearAllFilters() {
            this.search = '';
            this.appliedSearch = '';
            this.genders = [];
            this.silhouettes = [];
            this.activities = [];
            this.status = '';
            this.stock = '';
            this.featured = '';
            this.dropdownSearch = { silhouettes: '', activities: '' };
            this.openDropdown = null;
            this.applyFilters();
        },

        syncFromUrl() {
            const params = new URLSearchParams(window.location.search);
            this.search = params.get('search') ?? '';
            this.appliedSearch = this.search.trim();
            this.genders = params.getAll('genders[]').filter(Boolean);
            this.silhouettes = params.getAll('silhouettes[]').map(Number).filter(Boolean);
            this.activities = params.getAll('activities[]').map(Number).filter(Boolean);
            this.status = params.get('status') ?? '';
            this.stock = params.get('stock') ?? '';
            this.featured = params.get('featured') ?? '';
        },

        handleRegionClick(event) {
            const clearBtn = event.target.closest('[data-clear-filters]');
            if (clearBtn) {
                event.preventDefault();
                this.clearAllFilters();
                return;
            }

            const pageLink = event.target.closest('nav a[href], .pagination a[href]');
            if (pageLink && pageLink.getAttribute('href')) {
                event.preventDefault();
                this.fetchRegion(pageLink.href, { pushState: true });
            }
        },

        async fetchRegion(url, { pushState = false } = {}) {
            if (this.filterController) {
                this.filterController.abort();
            }
            const controller = new AbortController();
            this.filterController = controller;
            const seq = ++this.filterSeq;
            this.filterLoading = true;

            if (pushState && window.location.href !== url) {
                window.history.pushState({}, '', url);
            }

            try {
                const response = await fetch(url, {
                    method: 'GET',
                    signal: controller.signal,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'text/html',
                    },
                });
                if (!response.ok || seq !== this.filterSeq) return;

                if (response.redirected && response.url && response.url !== window.location.href) {
                    window.history.replaceState({}, '', response.url);
                }

                const html = await response.text();
                if (seq !== this.filterSeq) return;

                const parsed = new DOMParser().parseFromString(html, 'text/html');
                const nextRegion = parsed.querySelector(this.regionSelector);
                const currentRegion = this.$refs.region || document.querySelector(this.regionSelector);
                if (nextRegion && currentRegion) {
                    currentRegion.innerHTML = nextRegion.innerHTML;
                }
            } catch (error) {
                if (error?.name === 'AbortError') return;
                window.location.assign(url);
            } finally {
                if (seq === this.filterSeq) {
                    this.filterLoading = false;
                }
            }
        },
    };
}
