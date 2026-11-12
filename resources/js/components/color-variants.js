export function colorVariants({ colors = [] } = {}) {
    let identity = 0;
    const makeId = () => `local-${++identity}`;

    const suggestSku = (color, sizeValue) => {
        if (!sizeValue) return '';
        const slugInput = document.querySelector('input[name="slug"]');
        const slug = slugInput?.value || 'PROD';
        const cleanSlug = slug.replace(/[^a-zA-Z0-9]/g, '').toUpperCase().slice(0, 8) || 'PROD';
        const colorCode = (color.style_color_code || 'DEF').replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
        const cleanSize = String(sizeValue).replace('.', '_');
        return `${cleanSlug}-${colorCode}-${cleanSize}`;
    };

    const rows = colors.map(color => ({
        ...color,
        uid: makeId(),
        removed: false,
        variants: (Array.isArray(color.variants) ? color.variants : []).map(v => ({
            ...v,
            uid: makeId(),
            size_value: String(v.size_value),
            sku: v.sku || suggestSku(color, v.size_value),
            removed: false,
            skuManuallySet: Boolean(v.sku && v.sku !== suggestSku(color, v.size_value)),
        })),
    }));

    return {
        colors: rows,
        searchQuery: '',

        addColor(input = {}) {
            const color = {
                uid: makeId(),
                id: null,
                name: '',
                style_color_code: '',
                hex: '#000000',
                color_key: 'black',
                is_active: true,
                ...input,
                removed: false,
                variants: [],
            };
            this.colors.push(color);
            return color.uid;
        },

        addVariant(colorUid, input = {}) {
            const color = this.colors.find(c => c.uid === colorUid && !c.removed);
            if (!color || typeof input?.size_value !== 'string') return null;

            const suggested = suggestSku(color, input.size_value);
            const variant = {
                uid: makeId(),
                id: null,
                size_value: input.size_value,
                sku: input.sku || suggested,
                price_override: input.price_override ?? null,
                is_active: input.is_active ?? true,
                ...input,
                removed: false,
                skuManuallySet: Boolean(input.sku && input.sku !== suggested),
            };
            color.variants.push(variant);
            return variant.uid;
        },

        onSizeChange(colorUid, variantUid) {
            const color = this.colors.find(c => c.uid === colorUid);
            const variant = color?.variants.find(v => v.uid === variantUid);
            if (!variant || variant.skuManuallySet) return;
            variant.sku = suggestSku(color, variant.size_value);
        },

        onSkuChange(variant) {
            variant.skuManuallySet = true;
        },

        isDuplicateSku(sku, currentVariantUid) {
            if (!sku) return false;
            const norm = String(sku).trim().toUpperCase();
            let count = 0;
            for (const color of this.colors) {
                if (color.removed) continue;
                for (const v of color.variants) {
                    if (v.removed) continue;
                    if (String(v.sku || '').trim().toUpperCase() === norm) {
                        count++;
                        if (count > 1) return true;
                    }
                }
            }
            return false;
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
            const [item] = this.colors.splice(from, 1);
            this.colors.splice(to, 0, item);
        },

        moveVariant(colorUid, from, to) {
            const list = this.colors.find(c => c.uid === colorUid)?.variants;
            if (!list || from < 0 || to < 0 || from >= list.length || to >= list.length) return;
            const [item] = list.splice(from, 1);
            list.splice(to, 0, item);
        },

        addSequence(colorUid, sizes) {
            if (!Array.isArray(sizes)) return;
            for (const size of sizes) {
                if (typeof size === 'string') this.addVariant(colorUid, { size_value: size });
            }
        },
    };
}
