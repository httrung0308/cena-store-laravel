import Alpine from 'alpinejs';
import { Chart } from 'chart.js/auto';

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
