export function variantPicker({ variants = [], initialColorId = null, initialVariantId = null } = {}) {
    const list = Array.isArray(variants) ? variants.filter(v => v && typeof v.size_value === 'string') : [];
    return {
        variants: list,
        colorId: initialColorId,
        variantId: initialVariantId,
        get available() { return list.filter(v => String(v.product_color_id) === String(this.colorId) && v.is_active === true && Number.isInteger(v.available_qty) && v.available_qty > 0); },
        get selected() { return this.available.find(v => v.id === this.variantId) ?? null; },
        get priceAvailable() { return this.selected && Number.isInteger(this.selected.effective_price); },
        changeColor(colorId) {
            this.colorId = colorId;
            if (!this.available.some(v => v.id === this.variantId)) this.variantId = null;
        },
        choose(id) { this.variantId = this.available.some(v => v.id === id) ? id : null; },
    };
}
