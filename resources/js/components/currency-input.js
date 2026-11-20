/**
 * Formats a numeric string or number into Vietnamese thousand-separated display format (e.g. 1.500.000).
 */
export function formatVnd(value) {
    if (value === null || value === undefined || value === '') return '';
    const clean = String(value).replace(/[^0-9]/g, '');
    if (!clean) return '';
    return clean.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

/**
 * Parses a formatted currency string into a clean integer string.
 */
export function parseVnd(formatted) {
    if (!formatted) return '';
    return String(formatted).replace(/[^0-9]/g, '');
}

/**
 * Alpine component for currency input fields with thousand separators.
 * Keeps an unformatted integer value for the form submission.
 */
export function currencyInput({ initialValue = '', min = 0, max = 1000000000 } = {}) {
    return {
        rawValue: parseVnd(initialValue),
        displayValue: formatVnd(initialValue),
        min: min,
        max: max,

        init() {
            this.displayValue = formatVnd(this.rawValue);
        },

        onInput(e) {
            const raw = parseVnd(e.target.value);
            this.rawValue = raw;
            this.displayValue = formatVnd(raw);
        },

        setValue(val) {
            const raw = parseVnd(val);
            this.rawValue = raw;
            this.displayValue = formatVnd(raw);
        }
    };
}
