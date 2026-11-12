import Alpine from 'alpinejs';
import { Chart } from 'chart.js/auto';

import { addressSelector } from './components/address-selector';
import { adminStatusToggle } from './components/admin-status-toggle';
import { articleEditor } from './components/article-editor';
import { articleFeedback } from './components/article-feedback';
import { articleForm } from './components/article-form';
import { bindAddToCartButtons, cartStore } from './components/cart';
import { catalogListing } from './components/catalog-filter';
import { colorVariants } from './components/color-variants';
import { dialogController } from './components/dialog';
import { galleryController } from './components/gallery';
import { newsListing } from './components/news-listing';
import { passwordField } from './components/password-field';
import { productImages } from './components/product-images';
import { reportCharts } from './components/report-charts';
import { toastsController } from './components/toasts';
import { variantPicker } from './components/variant-picker';

// Shared Blade components use these names in x-data, e.g. x-data="variantPicker({...})".
Alpine.data('addressSelector', addressSelector);
Alpine.data('adminStatusToggle', adminStatusToggle);
Alpine.data('articleEditor', articleEditor);
Alpine.data('articleFeedback', articleFeedback);
Alpine.data('articleForm', articleForm);
Alpine.data('catalogListing', catalogListing);
Alpine.data('colorVariants', colorVariants);
Alpine.data('dialogController', dialogController);
Alpine.data('galleryController', galleryController);
Alpine.data('newsListing', newsListing);
Alpine.data('passwordField', passwordField);
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

Alpine.data('adminShell', () => ({
    ...themeMixin(),
    sidebarOpen: false,
    init() {
        this.initTheme();
        this.$watch('sidebarOpen', (open) => {
            document.documentElement.classList.toggle('overflow-hidden', open);
        });
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
window.Alpine = Alpine;
window.Chart = Chart;
Alpine.start();
