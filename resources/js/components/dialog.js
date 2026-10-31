const scrollLocks = new WeakMap();

function lockScroll(body) {
    if (!body || !body.style) return;
    const entry = scrollLocks.get(body);
    if (entry) { entry.count += 1; return; }
    scrollLocks.set(body, { count: 1, original: body.style.overflow });
    body.style.overflow = 'hidden';
}

function unlockScroll(body) {
    const entry = body && scrollLocks.get(body);
    if (!entry) return;
    if (--entry.count > 0) return;
    body.style.overflow = entry.original;
    scrollLocks.delete(body);
}

export function dialogController() {
    return {
        opened: false,
        busy: false,
        panel: null,
        trigger: null,
        body: null,
        doc: null,
        previousFocus: null,
        attach({ panel, trigger, document: doc, body } = {}) {
            this.panel = panel;
            this.trigger = trigger;
            this.doc = doc;
            this.body = body || doc?.body || null;
        },
        openDialog(trigger = this.trigger) {
            if (this.opened) return;
            this.previousFocus = trigger || this.doc?.activeElement || null;
            this.opened = true;
            lockScroll(this.body);
            const focusable = this._focusable();
            (focusable[0] || this.panel)?.focus?.();
        },
        closeDialog() {
            if (!this.opened || this.busy) return;
            this._cleanup();
            this.previousFocus?.focus?.();
        },
        _focusable() {
            return Array.from(this.panel?.querySelectorAll?.('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])') || []).filter(el => !el.hidden && el.tabIndex !== -1);
        },
        onKeydown(event) {
            if (!this.opened) return;
            if (event.key === 'Escape') {
                if (!this.busy) { event.preventDefault(); this.closeDialog(); }
                return;
            }
            if (event.key !== 'Tab') return;
            const elements = this._focusable();
            if (!elements.length) { event.preventDefault(); this.panel?.focus?.(); return; }
            const first = elements[0], last = elements.at(-1), active = this.doc?.activeElement;
            if (event.shiftKey && (active === first || !elements.includes(active))) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && (active === last || !elements.includes(active))) {
                event.preventDefault(); first.focus();
            }
        },
        onBackdrop(event) {
            if (event.target === event.currentTarget && !this.busy) this.closeDialog();
        },
        _cleanup() {
            if (!this.opened) return;
            this.opened = false;
            unlockScroll(this.body);
        },
        destroy() { this._cleanup(); this.panel = this.trigger = this.body = this.doc = null; },
    };
}
