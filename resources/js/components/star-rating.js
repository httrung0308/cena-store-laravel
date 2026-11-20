const RATINGS = ['0.5', '1.0', '1.5', '2.0', '2.5', '3.0', '3.5', '4.0', '4.5', '5.0'];

function normalizeRating(raw, fallback = '5.0') {
    const parsed = Number.parseFloat(String(raw ?? ''));
    if (!Number.isFinite(parsed)) {
        return fallback;
    }
    const clamped = Math.max(0.5, Math.min(5.0, Math.round(parsed * 2) / 2));
    const formatted = clamped.toFixed(1);
    return RATINGS.includes(formatted) ? formatted : fallback;
}

export function starRating({ initial = '5.0' } = {}) {
    return {
        value: normalizeRating(initial, '5.0'),
        hoverValue: null,

        get displayValue() {
            return this.hoverValue ?? this.value;
        },

        get numericValue() {
            return Number.parseFloat(this.displayValue);
        },

        get labelText() {
            const formatted = this.displayValue.replace('.', ',').replace(/,0$/, '');
            return `${formatted} / 5 sao`;
        },

        get ariaValueNow() {
            return Number.parseFloat(this.value);
        },

        starFill(starIndex) {
            const current = this.numericValue;
            if (current >= starIndex) {
                return 'full';
            }
            if (current >= starIndex - 0.5) {
                return 'half';
            }
            return 'empty';
        },

        valueFromPointer(event, starIndex) {
            const target = event.currentTarget;
            if (!target || typeof target.getBoundingClientRect !== 'function') {
                return normalizeRating(starIndex);
            }
            const rect = target.getBoundingClientRect();
            const clientX = event.touches?.[0]?.clientX ?? event.changedTouches?.[0]?.clientX ?? event.clientX;
            if (!Number.isFinite(clientX) || rect.width <= 0) {
                return normalizeRating(starIndex);
            }
            const isLeftHalf = (clientX - rect.left) < (rect.width / 2);
            return normalizeRating(isLeftHalf ? starIndex - 0.5 : starIndex);
        },

        onHover(event, starIndex) {
            this.hoverValue = this.valueFromPointer(event, starIndex);
        },

        onLeave() {
            this.hoverValue = null;
        },

        onSelect(event, starIndex) {
            this.value = this.valueFromPointer(event, starIndex);
            this.hoverValue = null;
        },

        onTouch(event, starIndex) {
            this.value = this.valueFromPointer(event, starIndex);
            this.hoverValue = null;
        },

        onKeydown(event) {
            const current = Number.parseFloat(this.value);
            let next = null;

            switch (event.key) {
                case 'ArrowLeft':
                case 'ArrowDown':
                    next = Math.max(0.5, current - 0.5);
                    break;
                case 'ArrowRight':
                case 'ArrowUp':
                    next = Math.min(5.0, current + 0.5);
                    break;
                case 'Home':
                    next = 0.5;
                    break;
                case 'End':
                    next = 5.0;
                    break;
                default:
                    return;
            }

            event.preventDefault();
            this.value = normalizeRating(next);
            this.hoverValue = null;
        },
    };
}
