import Alpine from 'alpinejs';
import { Chart } from 'chart.js/auto';

import { addressSelector } from './components/address-selector';
import { adminAjaxList } from './components/admin-ajax-list';
import { adminProductFilters } from './components/admin-product-filters';
import { adminStatusToggle } from './components/admin-status-toggle';
import { articleEditor } from './components/article-editor';
import { articleFeedback } from './components/article-feedback';
import { articleForm } from './components/article-form';
import { bindAddToCartButtons, cartStore } from './components/cart';
import { catalogListing } from './components/catalog-filter';
import { colorVariants } from './components/color-variants';
import { currencyInput } from './components/currency-input';
import { dialogController } from './components/dialog';
import { galleryController } from './components/gallery';
import { liveSlug } from './components/live-slug';
import { newsListing } from './components/news-listing';
import { passwordField } from './components/password-field';
import { productFormWorkspace } from './components/product-form-workspace';
import { productImages } from './components/product-images';
import { reportCharts } from './components/report-charts';
import { toastsController } from './components/toasts';
import { variantPicker } from './components/variant-picker';

// Shared Blade components use these names in x-data, e.g. x-data="variantPicker({...})".
Alpine.data('addressSelector', addressSelector);
Alpine.data('adminAjaxList', adminAjaxList);
Alpine.data('adminProductFilters', adminProductFilters);
Alpine.data('adminStatusToggle', adminStatusToggle);
Alpine.data('articleEditor', articleEditor);
Alpine.data('articleFeedback', articleFeedback);
Alpine.data('articleForm', articleForm);
Alpine.data('catalogListing', catalogListing);
Alpine.data('colorVariants', colorVariants);
Alpine.data('currencyInput', currencyInput);
Alpine.data('dialogController', dialogController);
Alpine.data('galleryController', galleryController);
Alpine.data('liveSlug', liveSlug);
Alpine.data('newsListing', newsListing);
Alpine.data('passwordField', passwordField);
Alpine.data('productFormWorkspace', productFormWorkspace);
Alpine.data('productImages', productImages);
Alpine.data('reportCharts', reportCharts);
Alpine.data('toastsController', toastsController);
Alpine.data('variantPicker', variantPicker);

// Server cart shared by the navbar badge, the drawer and any [data-add-to-cart] button.
Alpine.store('cart', cartStore({ csrfToken: document.querySelector('meta[name="csrf-token"]')?.content ?? '' }));
bindAddToCartButtons(Alpine.store('cart'));

function themeMixin() {
    return {
        theme: 'light',
        initTheme() {
            try {
                this.theme = localStorage.getItem('cena-theme') || 'light';
            } catch (e) {
                this.theme = 'light';
            }
            this.applyTheme(this.theme);
        },
        setTheme(t) {
            this.theme = t;
            try {
                localStorage.setItem('cena-theme', t);
            } catch (e) {}
            this.applyTheme(t);
        },
        applyTheme(t) {
            try {
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        },
    };
}

const ADMIN_SIDEBAR_GROUPS_STORAGE_KEY = 'cena-admin-sidebar-groups';

Alpine.data('adminShell', (config = {}) => ({
    ...themeMixin(),
    sidebarOpen: false,
    groupKeys: Array.isArray(config.groupKeys) ? config.groupKeys : [],
    activeGroups: Array.isArray(config.activeGroups) ? config.activeGroups : [],
    openGroups: {},
    init() {
        this.initTheme();
        this.initSidebarGroups();
        this.$watch('sidebarOpen', (open) => {
            document.documentElement.classList.toggle('overflow-hidden', open);
        });
    },
    initSidebarGroups() {
        const domGroups = [...document.querySelectorAll('#admin-sidebar [data-sidebar-group]')];
        const discoveredKeys = domGroups
            .map((el) => el.getAttribute('data-sidebar-group'))
            .filter(Boolean);
        const discoveredActiveKeys = domGroups
            .filter((el) => el.getAttribute('data-group-active') === 'true' || el.querySelector('[aria-current="page"]'))
            .map((el) => el.getAttribute('data-sidebar-group'))
            .filter(Boolean);

        const allKeys = [...new Set([...this.groupKeys, ...discoveredKeys])];
        const activeKeys = new Set([...this.activeGroups, ...discoveredActiveKeys]);

        this.groupKeys = allKeys;
        this.activeGroups = [...activeKeys];

        const nextState = {};
        allKeys.forEach((key) => {
            nextState[key] = true;
        });

        try {
            const raw = localStorage.getItem(ADMIN_SIDEBAR_GROUPS_STORAGE_KEY);
            if (raw) {
                const parsed = JSON.parse(raw);
                if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                    Object.entries(parsed).forEach(([key, value]) => {
                        if (typeof value === 'boolean') {
                            nextState[key] = value;
                        }
                    });
                }
            }
        } catch (e) {}

        activeKeys.forEach((key) => {
            nextState[key] = true;
        });

        this.openGroups = nextState;
        this.persistSidebarGroups();
    },
    isGroupOpen(groupKey) {
        if (Object.prototype.hasOwnProperty.call(this.openGroups, groupKey)) {
            return Boolean(this.openGroups[groupKey]);
        }

        return true;
    },
    toggleGroup(groupKey) {
        this.openGroups = {
            ...this.openGroups,
            [groupKey]: !this.isGroupOpen(groupKey),
        };
        this.persistSidebarGroups();
    },
    persistSidebarGroups() {
        try {
            localStorage.setItem(ADMIN_SIDEBAR_GROUPS_STORAGE_KEY, JSON.stringify(this.openGroups));
        } catch (e) {}
    },
    closeSidebar() {
        this.sidebarOpen = false;
    },
}));

Alpine.data('storefrontShell', () => ({
    ...themeMixin(),
    init() {
        this.initTheme();
    },
    panel: null,
    noticeTitle: '',
    hoverImage: null,
    pointerX: 0,
    pointerY: 0,
    returnFocus: null,
    openPanel(panel) {
        if (!this.panel) this.returnFocus = document.activeElement;
        this.hoverImage = null;
        this.panel = panel;
        this.$nextTick(() => {
            const root = document.querySelector(`#${panel === 'menu' ? 'mobile-menu' : `${panel}-panel`}`);
            (root?.querySelector('[data-autofocus]') ?? root?.querySelector('button'))?.focus();
        });
    },
    openNotice(title) {
        this.noticeTitle = title;
        this.openPanel('notice');
    },
    closePanel() {
        this.panel = null;
        this.$nextTick(() => this.returnFocus?.focus({ preventScroll: true }));
    },
    trapFocus(event) {
        const controls = [...event.currentTarget.querySelectorAll('a[href], button:not([disabled]), input, select, textarea, [tabindex="0"]')];
        const first = controls[0];
        const last = controls.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    },
}));
// Auto scroll to first form error on load
document.addEventListener('DOMContentLoaded', () => {
    const firstError = document.querySelector('[aria-invalid="true"], .border-error, [role="alert"]');
    if (firstError) {
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (typeof firstError.focus === 'function') {
            try { firstError.focus(); } catch (e) {}
        }
    }
});

// Submit guard against accidental double submits
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.hasAttribute('data-no-guard')) return;

    const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
    // Allow slight delay so form data is captured
    setTimeout(() => {
        submitButtons.forEach(btn => {
            if (btn instanceof HTMLButtonElement) {
                btn.disabled = true;
                btn.classList.add('opacity-75', 'cursor-not-allowed');
            }
        });
    }, 50);
});

window.Alpine = Alpine;
window.Chart = Chart;
Alpine.start();
