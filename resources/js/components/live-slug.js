/**
 * Helper to slugify a string according to Vietnamese rules and Cena Store URL conventions.
 */
export function slugify(text) {
    if (!text || typeof text !== 'string') return '';

    let str = text.trim().toLowerCase();

    // Vietnamese diacritics removal
    str = str.replace(/[àáạảãâầấậẩẫăằắặẳẵ]/g, 'a')
        .replace(/[èéẹẻẽêềếệểễ]/g, 'e')
        .replace(/[ìíịỉĩ]/g, 'i')
        .replace(/[òóọỏõôồốộổỗơờớợởỡ]/g, 'o')
        .replace(/[ùúụủũưừứựửữ]/g, 'u')
        .replace(/[ỳýỵỷỹ]/g, 'y')
        .replace(/đ/g, 'd');

    // Remove special characters, keep alphanumeric and hyphens
    str = str.replace(/[^a-z0-9\s-]/g, '');

    // Replace whitespace and underscores with a single hyphen
    str = str.replace(/[\s_]+/g, '-');

    // Remove multiple hyphens
    str = str.replace(/-+/g, '-');

    // Remove leading and trailing hyphens
    return str.replace(/^-+|-+$/g, '');
}

/**
 * Alpine component for live slug generation with edit-lock.
 * @param {Object} options
 * @param {boolean} options.isNew - Whether this is a new record creation form.
 * @param {string} options.initialName - Initial name value.
 * @param {string} options.initialSlug - Initial slug value.
 */
export function liveSlug({ isNew = true, initialName = '', initialSlug = '' } = {}) {
    return {
        name: initialName,
        slug: initialSlug,
        isNew: isNew,
        manuallyModified: false,
        isLocked: !isNew,

        init() {
            if (this.isNew && this.slug && this.slug !== slugify(this.name)) {
                this.manuallyModified = true;
            }
        },

        onNameInput(e) {
            this.name = e.target.value;
            if (this.isNew && !this.manuallyModified) {
                this.slug = slugify(this.name);
            }
        },

        onSlugInput(e) {
            this.slug = e.target.value;
            this.manuallyModified = true;
        },

        unlockSlug() {
            this.isLocked = false;
            this.$nextTick(() => {
                const slugInput = this.$refs.slugInput;
                if (slugInput) {
                    slugInput.focus();
                    slugInput.select();
                }
            });
        },

        regenerateSlug() {
            this.slug = slugify(this.name);
            this.manuallyModified = false;
        }
    };
}
