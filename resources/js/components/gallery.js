export function galleryController({ albums = {}, initialColor = null } = {}) {
    const safe = key => Array.isArray(albums[key]) ? albums[key].filter(x => x && (typeof x.id === 'string' || typeof x.id === 'number') && typeof x.url === 'string') : [];
    return {
        color: initialColor,
        images: safe(initialColor),
        selectedId: safe(initialColor)[0]?.id ?? null,
        get selected() { return this.images.find(i => i.id === this.selectedId) ?? null; },
        choose(id) { if (this.images.some(i => i.id === id)) this.selectedId = id; },
        changeColor(code) {
            if (code === this.color) return;
            this.color = code; this.images = safe(code); this.selectedId = this.images[0]?.id ?? null;
        },
        step(direction) {
            if (!this.images.length) return;
            const i = this.images.findIndex(x => x.id === this.selectedId);
            const next = (i + direction + this.images.length) % this.images.length;
            this.selectedId = this.images[next].id;
        },
        onKeydown(event) {
            if (event.key === 'ArrowRight') { event.preventDefault(); this.step(1); }
            if (event.key === 'ArrowLeft') { event.preventDefault(); this.step(-1); }
        },
    };
}
