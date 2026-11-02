import Alpine from 'alpinejs';
import { Chart } from 'chart.js/auto';

import { addressSelector } from './components/address-selector';
import { bindAddToCartButtons, cartStore } from './components/cart';
import { colorVariants } from './components/color-variants';
import { dialogController } from './components/dialog';
import { galleryController } from './components/gallery';
import { passwordField } from './components/password-field';
import { productImages } from './components/product-images';
import { toastsController } from './components/toasts';
import { variantPicker } from './components/variant-picker';

// Shared Blade components use these names in x-data, e.g. x-data="variantPicker({...})".
Alpine.data('addressSelector', addressSelector);
Alpine.data('colorVariants', colorVariants);
Alpine.data('dialogController', dialogController);
Alpine.data('galleryController', galleryController);
Alpine.data('passwordField', passwordField);
Alpine.data('productImages', productImages);
Alpine.data('toastsController', toastsController);
Alpine.data('variantPicker', variantPicker);

// Server cart shared by the navbar badge, the drawer and any [data-add-to-cart] button.
Alpine.store('cart', cartStore({ csrfToken: document.querySelector('meta[name="csrf-token"]')?.content ?? '' }));
bindAddToCartButtons(Alpine.store('cart'));

Alpine.data('storefrontShell', () => ({
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
        this.$nextTick(() => document.querySelector(`#${panel === 'menu' ? 'mobile-menu' : `${panel}-panel`} button`)?.focus());
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
