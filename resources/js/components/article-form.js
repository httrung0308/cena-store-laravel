// Admin article form: live slug preview, summary counter, cover drop zone with instant preview and size check,
// and a warning before leaving with unsaved changes. The server still validates everything on save.
export const COVER_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
export const COVER_MAX_BYTES = 5 * 1024 * 1024;

/** Mirrors Str::slug for the preview: Vietnamese letters lose their marks, đ becomes d. */
export function slugify(title) {
    return title
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[đĐ]/g, 'd')
        .toLowerCase()
        .replace(/['’]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 200)
        .replace(/-+$/, '');
}

/** Why a chosen cover would be refused, or null when it is fine. */
export function coverProblem(file) {
    if (!COVER_TYPES.includes(file.type)) return 'Ảnh bìa phải là ảnh JPEG, PNG hoặc WebP.';
    if (file.size > COVER_MAX_BYTES) return `Ảnh bìa tối đa 5 MB (ảnh này ${(file.size / 1024 / 1024).toLocaleString('vi-VN', { maximumFractionDigits: 1 })} MB). Hãy nén hoặc chọn ảnh nhỏ hơn.`;

    return null;
}

export function articleForm({ title = '', summary = '', slug = null, cover = null } = {}) {
    return {
        title,
        summary,
        fixedSlug: slug,
        coverUrl: cover,
        coverName: '',
        coverError: '',
        dragging: false,
        dirty: false,
        submitting: false,
        init() {
            this.guard = (event) => {
                if (!this.dirty || this.submitting) return;
                event.preventDefault();
                event.returnValue = '';
            };
            window.addEventListener('beforeunload', this.guard);
        },
        destroy() {
            window.removeEventListener('beforeunload', this.guard);
            if (this.coverName) URL.revokeObjectURL(this.coverUrl);
        },
        get slug() {
            return this.fixedSlug ?? (slugify(this.title) || 'bai-viet');
        },
        pickCover(file) {
            if (!file) return;
            const problem = coverProblem(file);
            if (problem) {
                this.coverError = problem;
                this.$refs.cover.value = '';
                return;
            }
            if (this.coverName) URL.revokeObjectURL(this.coverUrl);
            this.coverError = '';
            this.coverName = file.name;
            this.coverUrl = URL.createObjectURL(file);
            this.dirty = true;
        },
        dropCover(event) {
            this.dragging = false;
            const file = event.dataTransfer?.files?.[0];
            if (!file) return;
            const transfer = new DataTransfer();
            transfer.items.add(file);
            this.$refs.cover.files = transfer.files;
            this.pickCover(file);
        },
    };
}
