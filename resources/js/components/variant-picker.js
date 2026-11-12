export function variantPicker({ variants = [], colors = [], initialColorId = null, initialVariantId = null, maxPerVariant = 10 } = {}) {
    const list = Array.isArray(variants) ? variants.filter(v => v && typeof v.size_value === 'string') : [];
    const colorList = Array.isArray(colors) ? colors : [];
    const cap = Number.isInteger(maxPerVariant) && maxPerVariant > 0 ? maxPerVariant : 10;

    return {
        variants: list,
        colors: colorList,
        maxPerVariant: cap,
        colorId: initialColorId,
        variantId: initialVariantId,
        quantity: 1,
        attemptedSubmitWithoutSize: false,

        get currentColor() {
            return this.colors.find(c => String(c.id) === String(this.colorId)) ?? null;
        },
        get available() {
            return list.filter(v => String(v.product_color_id) === String(this.colorId) && v.is_active === true && Number.isInteger(v.available_qty) && v.available_qty > 0);
        },
        get selected() {
            return this.available.find(v => v.id === this.variantId) ?? null;
        },
        get priceAvailable() {
            return Boolean(this.selected && Number.isInteger(this.selected.effective_price));
        },
        get maxQty() {
            if (!this.selected) return 1;
            const limit = Number.isInteger(this.selected.max_cart_qty) ? this.selected.max_cart_qty : this.maxPerVariant;
            return Math.max(1, Math.min(this.selected.available_qty, limit));
        },
        get sizeMatrix() {
            const seen = new Set();
            const sizes = [];
            for (const v of list) {
                if (v.is_active === true && !seen.has(v.size_value)) {
                    seen.add(v.size_value);
                    sizes.push(v.size_value);
                }
            }

            const inColor = list.filter(v => String(v.product_color_id) === String(this.colorId) && v.is_active === true);
            return sizes.map(sizeValue => {
                const match = inColor.find(v => v.size_value === sizeValue) ?? null;
                if (!match) {
                    return { size_value: sizeValue, id: `unavailable-${sizeValue}`, status: 'unavailable', available_qty: 0 };
                }
                const hasStock = Number.isInteger(match.available_qty) && match.available_qty > 0;
                return {
                    size_value: sizeValue,
                    id: match.id,
                    status: hasStock ? 'available' : 'out_of_stock',
                    available_qty: hasStock ? match.available_qty : 0,
                };
            });
        },
        get colorSummary() {
            const inStock = this.available.filter(v => Number.isInteger(v.effective_price));
            if (inStock.length === 0) {
                return { hasStock: false, varies: false, minPriceLabel: null, maxPriceLabel: null, singleBasePriceLabel: null, singleDiscountPercent: 0 };
            }
            let minItem = inStock[0];
            let maxItem = inStock[0];
            for (const item of inStock) {
                if (item.effective_price < minItem.effective_price) minItem = item;
                if (item.effective_price > maxItem.effective_price) maxItem = item;
            }
            const varies = minItem.effective_price !== maxItem.effective_price;
            return {
                hasStock: true,
                varies,
                minPriceLabel: minItem.price_label ?? null,
                maxPriceLabel: maxItem.price_label ?? null,
                singleBasePriceLabel: !varies ? (minItem.base_price_label ?? null) : null,
                singleDiscountPercent: !varies ? (minItem.discount_percent ?? 0) : 0,
            };
        },
        changeColor(colorId) {
            this.colorId = colorId;
            if (!this.available.some(v => v.id === this.variantId)) {
                this.variantId = null;
            }
            this.quantity = 1;
            this.attemptedSubmitWithoutSize = false;

            const chosen = this.currentColor;
            if (chosen?.style_color_code && typeof globalThis.window !== 'undefined' && typeof globalThis.history?.replaceState === 'function') {
                try {
                    const url = new URL(globalThis.window.location.href);
                    url.searchParams.set('style_color', chosen.style_color_code);
                    globalThis.history.replaceState(globalThis.history.state, '', `${url.pathname}${url.search}${url.hash}`);
                } catch {}
            }
        },
        choose(id) {
            this.variantId = this.available.some(v => v.id === id) ? id : null;
            this.attemptedSubmitWithoutSize = false;
            this.setQuantity(this.quantity);
        },
        setQuantity(value) {
            const parsed = Number.parseInt(String(value), 10);
            const bounded = Number.isInteger(parsed) ? parsed : 1;
            this.quantity = Math.max(1, Math.min(this.maxQty, bounded));
        },
        stepQuantity(delta) {
            this.setQuantity(this.quantity + delta);
        },
    };
}
