import { slugify } from './live-slug';

const ALLOWED_IMAGE_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp']);
const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

export function normalizeVi(str) {
    return String(str ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .toLowerCase()
        .trim();
}

export function formatVnd(value) {
    const digits = String(value ?? '').replace(/\D+/g, '');
    if (!digits) return '';
    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

export function parseDigits(value) {
    const digits = String(value ?? '').replace(/\D+/g, '');
    return digits === '' ? '' : String(parseInt(digits, 10));
}

export function productFormWorkspace(config = {}) {
    let uidCounter = 0;
    const nextUid = (prefix = 'u') => `${prefix}-${++uidCounter}`;

    const initialProduct = config.product || {};
    const isNew = Boolean(config.isNew);
    const isDuplicate = Boolean(config.isDuplicate);
    const categories = Array.isArray(config.categories) ? config.categories : [];
    const classifications = Array.isArray(config.classifications) ? config.classifications : [];
    const initialSelectedClassifications = Array.isArray(config.selectedClassificationIds)
        ? config.selectedClassificationIds.map((id) => Number(id))
        : [];

    const mapImages = (rawImages = []) =>
        (Array.isArray(rawImages) ? rawImages : []).map((img, idx) => ({
            uid: nextUid('img'),
            id: img.id ?? null,
            file: null,
            previewUrl: null,
            existingUrl: img.existingUrl ?? null,
            external_url: img.external_url ?? '',
            clone_source_path: img.clone_source_path ?? '',
            alt_text: img.alt_text ?? '',
            thumbnail: Boolean(img.thumbnail ?? img.is_thumbnail ?? idx === 0),
            showAltEditor: false,
            broken: false,
        }));

    const mapVariants = (rawVariants = []) =>
        (Array.isArray(rawVariants) ? rawVariants : []).map((v) => ({
            uid: nextUid('var'),
            id: v.id ?? null,
            size_value: String(v.size_value ?? ''),
            sku: v.sku ?? '',
            skuManuallySet: Boolean(v.id || v.sku),
            price_override: v.price_override !== null && v.price_override !== undefined && v.price_override !== ''
                ? String(v.price_override)
                : '',
            price_override_display: formatVnd(v.price_override ?? ''),
            is_active: v.is_active === undefined ? true : Boolean(Number(v.is_active)),
            documented: Boolean(v.documented),
        }));

    const mapColors = (rawColors = []) => {
        const list = (Array.isArray(rawColors) ? rawColors : []).map((c) => ({
            uid: nextUid('col'),
            id: c.id ?? null,
            color_name: c.color_name ?? c.name ?? '',
            style_color_code: c.style_color_code ?? '',
            color_key: c.color_key ?? 'black',
            primary_hex: c.primary_hex ?? c.hex ?? '#000000',
            is_active: c.is_active === undefined ? true : Boolean(Number(c.is_active)),
            variants: mapVariants(c.variants),
            images: mapImages(c.images),
            rangeStart: '',
            rangeEnd: '',
            rangeStep: '0.5',
            rangePreview: [],
            rangePreviewError: '',
            skuPrefix: '',
            showSkuColumn: false,
            showUrlAdder: false,
            newExternalUrl: '',
            dragOver: false,
        }));

        return list;
    };

    const initialCategoryId = initialProduct.category_id ? Number(initialProduct.category_id) : '';
    const initialCategory = categories.find((c) => Number(c.id) === Number(initialCategoryId));

    return {
        action: config.action || '',
        method: (config.method || 'POST').toUpperCase(),
        indexUrl: config.indexUrl || '/admin/products',
        csrfToken: config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '',
        isNew,
        isDuplicate,

        // Core fields
        name: initialProduct.name ?? '',
        slug: initialProduct.slug ?? '',
        brand_line: initialProduct.brand_line ?? '',
        description: initialProduct.description ?? '',
        category_id: initialCategoryId,
        base_price: parseDigits(initialProduct.base_price ?? ''),
        base_price_display: formatVnd(initialProduct.base_price ?? ''),
        promotion_price: parseDigits(initialProduct.promotion_price ?? ''),
        promotion_price_display: formatVnd(initialProduct.promotion_price ?? ''),
        warranty_months: initialProduct.warranty_months ?? 6,
        exchange_days: initialProduct.exchange_days ?? 7,
        is_active: initialProduct.is_active === undefined ? (isDuplicate ? false : true) : Boolean(Number(initialProduct.is_active)),
        is_featured: Boolean(Number(initialProduct.is_featured ?? 0)),

        // Slug controls (hidden by default)
        manuallyModifiedSlug: !isNew || isDuplicate,
        isSlugLocked: !isNew && !isDuplicate,
        showSlugEditor: false,

        // Category searchable combobox
        categories,
        categoryOpen: false,
        categoryQuery: '',
        categoryHighlight: 0,

        // Classifications searchable multi-select
        classifications,
        classificationQuery: '',
        selectedClassificationIds: initialSelectedClassifications,

        // Colors workspace
        colors: mapColors(config.colors),
        activeColorIndex: 0,

        // Submission & validation state
        submitting: false,
        errors: {},
        networkNotice: null,

        init() {
            if (this.colors.length === 0) {
                this.addColor();
            }
            if (initialCategory) {
                this.categoryQuery = '';
            }
            window.addEventListener('beforeunload', () => this.cleanupPreviews());
        },

        cleanupPreviews() {
            for (const color of this.colors) {
                for (const img of color.images) {
                    if (img.previewUrl) URL.revokeObjectURL(img.previewUrl);
                }
            }
        },

        // --- Slug ---
        onNameInput() {
            if (this.isNew && !this.isDuplicate && !this.manuallyModifiedSlug) {
                this.slug = slugify(this.name);
            }
        },
        onSlugInput() {
            this.manuallyModifiedSlug = true;
        },
        unlockSlug() {
            this.isSlugLocked = false;
            this.showSlugEditor = true;
            this.$nextTick(() => this.$refs.slugInput?.focus());
        },
        regenerateSlug() {
            this.manuallyModifiedSlug = false;
            this.slug = slugify(this.name);
        },

        // --- Prices ---
        onBasePriceInput(val) {
            this.base_price = parseDigits(val);
            this.base_price_display = formatVnd(this.base_price);
        },
        onPromoPriceInput(val) {
            this.promotion_price = parseDigits(val);
            this.promotion_price_display = formatVnd(this.promotion_price);
        },
        onVariantPriceInput(variant, val) {
            variant.price_override = parseDigits(val);
            variant.price_override_display = formatVnd(variant.price_override);
        },
        get discountPercent() {
            const base = Number(this.base_price || 0);
            const promo = Number(this.promotion_price || 0);
            if (!base || !promo || promo >= base) return null;
            return Math.round(((base - promo) / base) * 100);
        },

        // --- Category Combobox ---
        get selectedCategoryLabel() {
            const found = this.categories.find((c) => Number(c.id) === Number(this.category_id));
            return found ? found.label : '';
        },
        get filteredCategories() {
            const q = normalizeVi(this.categoryQuery);
            if (!q) return this.categories;
            return this.categories.filter((c) => normalizeVi(c.label).includes(q));
        },
        selectCategory(cat) {
            this.category_id = cat.id;
            this.categoryOpen = false;
            this.categoryQuery = '';
            this.clearError('category_id');
        },
        clearCategory() {
            this.category_id = '';
            this.categoryQuery = '';
            this.categoryOpen = true;
        },

        // --- Classifications Multi-Select ---
        get selectedClassificationObjects() {
            const set = new Set(this.selectedClassificationIds.map(Number));
            return this.classifications.filter((c) => set.has(Number(c.id)));
        },
        get filteredClassifications() {
            const q = normalizeVi(this.classificationQuery);
            const list = q
                ? this.classifications.filter((c) => normalizeVi(c.name).includes(q) || normalizeVi(c.group_label).includes(q))
                : this.classifications;

            const groups = [];
            const byGroup = new Map();
            for (const item of list) {
                if (!byGroup.has(item.group_label)) {
                    const g = { label: item.group_label, items: [] };
                    byGroup.set(item.group_label, g);
                    groups.push(g);
                }
                byGroup.get(item.group_label).items.push(item);
            }
            return groups;
        },
        isClassificationSelected(id) {
            return this.selectedClassificationIds.includes(Number(id));
        },
        toggleClassification(id) {
            const num = Number(id);
            if (this.isClassificationSelected(num)) {
                this.selectedClassificationIds = this.selectedClassificationIds.filter((x) => x !== num);
            } else {
                this.selectedClassificationIds = [...this.selectedClassificationIds, num];
            }
            this.clearError('classification_ids');
        },
        removeClassification(id) {
            const num = Number(id);
            this.selectedClassificationIds = this.selectedClassificationIds.filter((x) => x !== num);
        },

        // --- Colors & Tabs ---
        get activeColor() {
            return this.colors[this.activeColorIndex] || this.colors[0] || null;
        },
        addColor() {
            this.colors.push({
                uid: nextUid('col'),
                id: null,
                color_name: '',
                style_color_code: '',
                color_key: 'black',
                primary_hex: '#000000',
                is_active: true,
                variants: [],
                images: [],
                rangeStart: '39',
                rangeEnd: '42',
                rangeStep: '0.5',
                rangePreview: [],
                rangePreviewError: '',
                skuPrefix: '',
                showSkuColumn: false,
                showUrlAdder: false,
                newExternalUrl: '',
                dragOver: false,
            });
            this.activeColorIndex = this.colors.length - 1;
        },
        removeColor(index) {
            if (this.colors.length <= 1) return;
            const target = this.colors[index];
            const hasDocumented = target.variants.some((v) => v.documented);
            if (hasDocumented) return;
            for (const img of target.images) {
                if (img.previewUrl) URL.revokeObjectURL(img.previewUrl);
            }
            this.colors.splice(index, 1);
            if (this.activeColorIndex >= this.colors.length) {
                this.activeColorIndex = Math.max(0, this.colors.length - 1);
            }
        },
        colorHasError(ci) {
            const prefix = `colors.${ci}.`;
            return Object.keys(this.errors).some((k) => k === `colors.${ci}` || k.startsWith(prefix));
        },

        // --- Variants & Size Range Preview ---
        addVariant(color, sizeValue = '') {
            color.variants.push({
                uid: nextUid('var'),
                id: null,
                size_value: sizeValue,
                sku: '',
                skuManuallySet: false,
                price_override: '',
                price_override_display: '',
                is_active: true,
                documented: false,
            });
        },
        removeVariant(color, variantUid) {
            const target = color.variants.find((v) => v.uid === variantUid);
            if (target?.documented) return;
            color.variants = color.variants.filter((v) => v.uid !== variantUid);
        },
        previewSizeRange(color) {
            color.rangePreviewError = '';
            const start = Number(color.rangeStart);
            const end = Number(color.rangeEnd);
            const step = Number(color.rangeStep) === 1 ? 1 : 0.5;
            if (!Number.isFinite(start) || !Number.isFinite(end) || start <= 0 || end < start) {
                color.rangePreview = [];
                color.rangePreviewError = 'Nhập khoảng size hợp lệ (Từ size ≤ Đến size).';
                return;
            }
            const existing = new Set(color.variants.map((v) => String(v.size_value).trim()));
            const generated = [];
            for (let s = start; s <= end + 1e-9 && generated.length < 100; s += step) {
                const normalized = Number.isInteger(Math.round(s * 2) / 2)
                    ? String(Math.round(s))
                    : String(Math.round(s * 2) / 2);
                generated.push({
                    size: normalized,
                    exists: existing.has(normalized),
                });
            }
            if (generated.length >= 100 && end - start > 50) {
                color.rangePreviewError = 'Tối đa 100 size cho mỗi phối màu.';
            }
            color.rangePreview = generated;
        },
        applySizeRange(color) {
            if (color.rangePreview.length === 0) {
                this.previewSizeRange(color);
            }
            const toAdd = color.rangePreview.filter((item) => !item.exists);
            for (const item of toAdd) {
                this.addVariant(color, item.size);
            }
            color.rangePreview = [];
        },

        // --- Images per Color ---
        defaultAltFor(color) {
            const pName = (this.name || '').trim() || 'Sản phẩm Cena';
            const cName = (color?.color_name || '').trim();
            return cName ? `${pName} - ${cName}` : pName;
        },
        handleFileDrop(color, event) {
            color.dragOver = false;
            if (event.dataTransfer?.files?.length) {
                this.addFilesToColor(color, event.dataTransfer.files);
            }
        },
        addFilesToColor(color, fileList) {
            const files = Array.from(fileList || []);
            if (!files.length) return;
            const hasThumb = color.images.some((i) => i.thumbnail);
            let addedThumb = hasThumb;
            for (const file of files) {
                if (!ALLOWED_IMAGE_TYPES.has(file.type)) {
                    this.notifyToast('warning', `Bỏ qua "${file.name}": chỉ hỗ trợ JPEG, PNG hoặc WebP.`);
                    continue;
                }
                if (file.size > MAX_IMAGE_BYTES) {
                    this.notifyToast('warning', `Bỏ qua "${file.name}": vượt quá dung lượng tối đa 5MB.`);
                    continue;
                }
                const previewUrl = URL.createObjectURL(file);
                color.images.push({
                    uid: nextUid('img'),
                    id: null,
                    file,
                    previewUrl,
                    existingUrl: null,
                    external_url: '',
                    clone_source_path: '',
                    alt_text: '',
                    thumbnail: !addedThumb,
                    showAltEditor: false,
                    broken: false,
                });
                addedThumb = true;
            }
        },
        addExternalUrlToColor(color) {
            const url = (color.newExternalUrl || '').trim();
            if (!url) return;
            if (!url.startsWith('https://')) {
                this.notifyToast('warning', 'URL ảnh phải bắt đầu bằng https://');
                return;
            }
            const hasThumb = color.images.some((i) => i.thumbnail);
            color.images.push({
                uid: nextUid('img'),
                id: null,
                file: null,
                previewUrl: null,
                existingUrl: null,
                external_url: url,
                clone_source_path: '',
                alt_text: '',
                thumbnail: !hasThumb,
                showAltEditor: false,
                broken: false,
            });
            color.newExternalUrl = '';
            color.showUrlAdder = false;
        },
        setThumbnail(color, imgUid) {
            for (const img of color.images) {
                img.thumbnail = img.uid === imgUid;
            }
        },
        moveImage(color, index, delta) {
            const next = index + delta;
            if (next < 0 || next >= color.images.length) return;
            const [moved] = color.images.splice(index, 1);
            color.images.splice(next, 0, moved);
        },
        removeImage(color, imgUid) {
            const target = color.images.find((i) => i.uid === imgUid);
            if (target?.previewUrl) URL.revokeObjectURL(target.previewUrl);
            const wasThumb = target?.thumbnail;
            color.images = color.images.filter((i) => i.uid !== imgUid);
            if (wasThumb && color.images.length > 0) {
                color.images[0].thumbnail = true;
            }
        },

        // --- Error Helpers ---
        getError(key) {
            const val = this.errors[key];
            if (!val) return null;
            return Array.isArray(val) ? val[0] : String(val);
        },
        clearError(key) {
            if (this.errors[key]) {
                const copy = { ...this.errors };
                delete copy[key];
                this.errors = copy;
            }
        },
        revealErrors(errorMap) {
            const keys = Object.keys(errorMap || {});
            if (!keys.length) return;

            if (keys.includes('slug')) {
                this.showSlugEditor = true;
                this.isSlugLocked = false;
            }

            let firstColorErr = null;
            for (const key of keys) {
                const match = key.match(/^colors\.(\d+)(?:\.(.*))?$/);
                if (match) {
                    const ci = Number(match[1]);
                    if (firstColorErr === null && this.colors[ci]) firstColorErr = ci;
                    const sub = match[2] || '';
                    if (sub.includes('.sku') && this.colors[ci]) {
                        this.colors[ci].showSkuColumn = true;
                    }
                    const imgMatch = sub.match(/^images\.(\d+)\.alt_text$/);
                    if (imgMatch && this.colors[ci]?.images?.[Number(imgMatch[1])]) {
                        this.colors[ci].images[Number(imgMatch[1])].showAltEditor = true;
                    }
                }
            }
            if (firstColorErr !== null) {
                this.activeColorIndex = firstColorErr;
            }

            this.$nextTick(() => {
                const el = this.$el.querySelector('[data-has-error="true"], .border-error');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (typeof el.focus === 'function') el.focus({ preventScroll: true });
                }
            });
        },

        // --- Totals & Publish Readiness ---
        get totalVariantsCount() {
            return this.colors.reduce((sum, c) => sum + c.variants.length, 0);
        },
        get colorsMissingImages() {
            return this.colors.filter((c) => c.images.length === 0).length;
        },

        // --- AJAX Submit ---
        buildFormData() {
            const fd = new FormData();
            if (this.method === 'PUT' || this.method === 'PATCH') {
                fd.append('_method', this.method);
            }
            fd.append('name', this.name);
            fd.append('slug', this.slug);
            fd.append('brand_line', this.brand_line || '');
            fd.append('category_id', this.category_id ? String(this.category_id) : '');
            fd.append('base_price', this.base_price || '');
            if (this.promotion_price !== '') {
                fd.append('promotion_price', this.promotion_price);
            }
            fd.append('warranty_months', String(this.warranty_months ?? 6));
            fd.append('exchange_days', String(this.exchange_days ?? 7));
            fd.append('description', this.description || '');
            fd.append('is_active', this.is_active ? '1' : '0');
            fd.append('is_featured', this.is_featured ? '1' : '0');

            for (const id of this.selectedClassificationIds) {
                fd.append('classification_ids[]', String(id));
            }

            this.colors.forEach((color, ci) => {
                if (color.id) fd.append(`colors[${ci}][id]`, String(color.id));
                fd.append(`colors[${ci}][color_name]`, color.color_name);
                fd.append(`colors[${ci}][style_color_code]`, color.style_color_code);
                fd.append(`colors[${ci}][color_key]`, color.color_key || 'black');
                fd.append(`colors[${ci}][primary_hex]`, color.primary_hex || '#000000');
                fd.append(`colors[${ci}][is_active]`, color.is_active ? '1' : '0');

                color.variants.forEach((v, vi) => {
                    if (v.id) fd.append(`colors[${ci}][variants][${vi}][id]`, String(v.id));
                    fd.append(`colors[${ci}][variants][${vi}][size_value]`, v.size_value);
                    if (v.sku) fd.append(`colors[${ci}][variants][${vi}][sku]`, v.sku);
                    if (v.price_override !== '') {
                        fd.append(`colors[${ci}][variants][${vi}][price_override]`, v.price_override);
                    }
                    fd.append(`colors[${ci}][variants][${vi}][is_active]`, v.is_active ? '1' : '0');
                });

                color.images.forEach((img, ii) => {
                    if (img.id) fd.append(`colors[${ci}][images][${ii}][id]`, String(img.id));
                    if (img.file) fd.append(`colors[${ci}][images][${ii}][file]`, img.file);
                    if (img.external_url) fd.append(`colors[${ci}][images][${ii}][external_url]`, img.external_url);
                    if (img.clone_source_path) fd.append(`colors[${ci}][images][${ii}][clone_source_path]`, img.clone_source_path);
                    fd.append(`colors[${ci}][images][${ii}][alt_text]`, img.alt_text || '');
                    fd.append(`colors[${ci}][images][${ii}][is_thumbnail]`, img.thumbnail ? '1' : '0');
                });
            });

            return fd;
        },
        notifyToast(type, message) {
            window.dispatchEvent(new CustomEvent('cena-toast', { detail: { type, message } }));
        },
        async submitForm() {
            if (this.submitting) return;
            this.submitting = true;
            this.errors = {};
            this.networkNotice = null;

            try {
                const response = await fetch(this.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    credentials: 'same-origin',
                    body: this.buildFormData(),
                });

                if (response.status === 422) {
                    const payload = await response.json().catch(() => ({}));
                    this.errors = payload.errors || {};
                    this.revealErrors(this.errors);
                    this.notifyToast('error', 'Chưa lưu: vui lòng kiểm tra lại các trường báo đỏ.');
                    return;
                }

                if (response.status === 401 || response.status === 419) {
                    this.networkNotice = {
                        type: 'session',
                        message: 'Phiên đăng nhập đã hết hạn hoặc CSRF hết hiệu lực. Toàn bộ dữ liệu và ảnh bạn đã chọn vẫn còn nguyên — hãy mở tab mới đăng nhập lại rồi nhấn Lưu.',
                    };
                    this.notifyToast('error', 'Phiên làm việc hết hạn. Dữ liệu vẫn được giữ trên trang.');
                    return;
                }

                if (!response.ok) {
                    this.networkNotice = {
                        type: 'uncertain',
                        message: `Máy chủ phản hồi lỗi (${response.status}) sau khi gửi yêu cầu. Để tránh tạo trùng, không tự động gửi lại — vui lòng kiểm tra Danh sách sản phẩm trước khi thao tác tiếp.`,
                    };
                    this.notifyToast('warning', 'Không rõ yêu cầu đã được lưu hay chưa. Vui lòng kiểm tra danh sách.');
                    return;
                }

                const data = await response.json().catch(() => ({}));
                this.notifyToast('success', data.message || 'Đã lưu sản phẩm.');
                if (data.redirect) {
                    window.location.assign(data.redirect);
                }
            } catch {
                this.networkNotice = {
                    type: 'uncertain',
                    message: 'Mất kết nối mạng sau khi gửi yêu cầu. Dữ liệu có thể đã được máy chủ tiếp nhận hoặc chưa — không tự động gửi lại để tránh tạo trùng. Hãy kiểm tra Danh sách sản phẩm trước khi thử lại.',
                };
                this.notifyToast('warning', 'Mất kết nối sau khi gửi. Vui lòng kiểm tra danh sách sản phẩm.');
            } finally {
                this.submitting = false;
            }
        },
    };
}
