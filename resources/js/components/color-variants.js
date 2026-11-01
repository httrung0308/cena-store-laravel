export function colorVariants({ colors = [] } = {}) {
    let identity = 0;
    const makeId = () => `local-${++identity}`;
    const rows = colors.map(color => ({
        ...color, uid: makeId(), removed: false,
        variants: (Array.isArray(color.variants) ? color.variants : []).map(v => ({ ...v, uid: makeId(), size_value: String(v.size_value), removed: false })),
    }));
    return {
        colors: rows,
        addColor(input = {}) {
            const color = { uid: makeId(), id: null, name: '', style_color_code: '', hex: '', ...input, removed: false, variants: [] };
            this.colors.push(color);
            return color.uid;
        },
        addVariant(colorUid, input) {
            const color = this.colors.find(c => c.uid === colorUid && !c.removed);
            if (!color || typeof input?.size_value !== 'string') return null;
            const variant = { uid: makeId(), id: null, size_value: input.size_value, price_override: null, ...input, removed: false };
            color.variants.push(variant);
            return variant.uid;
        },
        removeVariant(colorUid, uid) {
            const color = this.colors.find(c => c.uid === colorUid);
            const item = color?.variants.find(v => v.uid === uid);
            if (!item) return;
            if (item.id !== null && item.id !== undefined) item.removed = true;
            else color.variants = color.variants.filter(v => v.uid !== uid);
        },
        removeColor(uid) {
            const item = this.colors.find(c => c.uid === uid);
            if (!item) return;
            if (item.id !== null && item.id !== undefined) item.removed = true;
            else this.colors = this.colors.filter(c => c.uid !== uid);
        },
        moveColor(from, to) {
            if (from < 0 || to < 0 || from >= this.colors.length || to >= this.colors.length) return;
            const [item] = this.colors.splice(from, 1); this.colors.splice(to, 0, item);
        },
        moveVariant(colorUid, from, to) {
            const list = this.colors.find(c => c.uid === colorUid)?.variants;
            if (!list || from < 0 || to < 0 || from >= list.length || to >= list.length) return;
            const [item] = list.splice(from, 1); list.splice(to, 0, item);
        },
        addSequence(colorUid, sizes) {
            if (!Array.isArray(sizes)) return;
            for (const size of sizes) if (typeof size === 'string') this.addVariant(colorUid, { size_value: size });
        },
    };
}
