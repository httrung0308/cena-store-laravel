const scrollLocks = new WeakMap();
// body -> { token, controller } of the dialog that owns focus. Alpine calls methods through a fresh proxy each
// time, so ownership is checked by a per-dialog token instead of comparing `this`.
const focusOwners = new WeakMap();
const owns = (dialog) => !dialog.body || focusOwners.get(dialog.body)?.token === dialog.token;

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
        previousOwner: null,
        scheduleFocus: null,
        focusEpoch: 0,
        destroyed: false,
        token: null,
        attach({ panel, trigger, document: doc, body, scheduleFocus } = {}) {
            this.panel = panel;
            this.trigger = trigger;
            this.doc = doc;
            this.body = body || doc?.body || null;
            this.scheduleFocus = scheduleFocus || null;
        },
        openDialog(trigger = this.trigger) {
            if (this.opened || this.destroyed) return;
            this.previousFocus = trigger || this.doc?.activeElement || null;
            this.opened = true;
            this.token ??= Symbol('dialog');
            this.previousOwner = this.body ? focusOwners.get(this.body) || null : null;
            if (this.body) focusOwners.set(this.body, { token: this.token, controller: this });
            lockScroll(this.body);
            const epoch = ++this.focusEpoch;
            const focusAfterShow = () => {
                if (!this.opened || this.destroyed || this.focusEpoch !== epoch) return;
                if (!owns(this)) return;
                const focusable = this._focusable();
                (focusable[0] || this.panel)?.focus?.();
            };
            if (this.scheduleFocus) this.scheduleFocus(focusAfterShow);
            else focusAfterShow();
        },
        closeDialog() {
            if (!this.opened || this.busy) return;
            const ownsFocus = owns(this);
            this._cleanup();
            if (ownsFocus) this.previousFocus?.focus?.();
        },
        _focusable() {
            return Array.from(this.panel?.querySelectorAll?.('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])') || []).filter(el => !el.hidden && el.tabIndex !== -1);
        },
        onKeydown(event) {
            if (!this.opened || !owns(this)) return;
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
            ++this.focusEpoch;
            if (this.body && owns(this)) {
                if (this.previousOwner?.controller.opened) focusOwners.set(this.body, this.previousOwner);
                else focusOwners.delete(this.body);
            }
            this.previousOwner = null;
            unlockScroll(this.body);
        },
        destroy() {
            this.destroyed = true;
            this._cleanup();
            this.panel = this.trigger = this.body = this.doc = this.scheduleFocus = null;
        },
    };
}
