export function productImages({ urlApi = globalThis.URL, initial = [] } = {}) {
    let key = 0;
    const rows = initial.map(row => ({ ...row, key: `img-${++key}`, previewUrl: null }));
    const revoke = row => { if (row?.previewUrl) { urlApi?.revokeObjectURL?.(row.previewUrl); row.previewUrl = null; } };
    return {
        images: rows,
        add() {
            const row = { key: `img-${++key}`, id: null, source_type: 'UPLOAD', file: null, external_url: null, previewUrl: null, thumbnail: this.images.length === 0 };
            this.images.push(row); return row.key;
        },
        setFile(key, file) {
            const row = this.images.find(r => r.key === key); if (!row) return;
            revoke(row); row.external_url = null; row.file = null;
            row.source_type = 'UPLOAD';
            if (file && ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                row.file = file;
                row.previewUrl = urlApi?.createObjectURL?.(file) || null;
            }
        },
        setExternal(key, url) {
            const row = this.images.find(r => r.key === key); if (!row) return;
            revoke(row); row.file = null; row.source_type = 'EXTERNAL_URL';
            row.external_url = typeof url === 'string' ? url : null;
        },
        makeThumbnail(key) { for (const row of this.images) row.thumbnail = row.key === key; },
        move(from, to) {
            if (from < 0 || to < 0 || from >= this.images.length || to >= this.images.length) return;
            const [row] = this.images.splice(from, 1); this.images.splice(to, 0, row);
        },
        remove(key) {
            const row = this.images.find(r => r.key === key); if (!row) return;
            revoke(row); this.images = this.images.filter(r => r.key !== key);
            if (row.thumbnail && this.images.length) this.makeThumbnail(this.images[0].key);
        },
        destroy() { for (const row of this.images) revoke(row); this.images = []; },
    };
}
