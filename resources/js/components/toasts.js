const types = new Set(['success', 'error', 'warning', 'info']);
export function toastsController({ schedule = setTimeout, cancel = clearTimeout, now = Date.now, timeout = 5000 } = {}) {
    let nextId = 0;
    const timers = new Map();
    function safe(item) {
        if (!item || typeof item.message !== 'string' || !item.message.trim()) return null;
        return { id: ++nextId, message: item.message, type: types.has(item.type) ? item.type : 'info', request_id: typeof item.request_id === 'string' ? item.request_id : null };
    }
    return {
        items: [],
        notify(item) {
            const toast = safe(item);
            if (!toast) return null;
            this.items.push(toast);
            if (timeout > 0) this._schedule(toast.id, timeout);
            return toast.id;
        },
        _schedule(id, delay) {
            const record = { remaining: delay, started: now(), token: null };
            record.token = schedule(() => this.dismiss(id), delay);
            timers.set(id, record);
        },
        pause(id) {
            const timer = timers.get(id);
            if (!timer || timer.token === null) return;
            cancel(timer.token);
            timer.remaining = Math.max(0, timer.remaining - (now() - timer.started));
            timer.token = null;
        },
        resume(id) {
            const timer = timers.get(id);
            if (!timer || timer.token !== null) return;
            timer.started = now();
            timer.token = schedule(() => this.dismiss(id), timer.remaining);
        },
        dismiss(id) {
            const timer = timers.get(id);
            if (timer?.token !== null && timer?.token !== undefined) cancel(timer.token);
            timers.delete(id);
            this.items = this.items.filter(t => t.id !== id);
        },
        destroy() {
            for (const id of [...timers.keys()]) this.dismiss(id);
            this.items = [];
        },
    };
}
