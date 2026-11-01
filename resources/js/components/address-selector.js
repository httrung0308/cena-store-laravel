export function addressSelector({ provinces = [], initialProvinceCode = null, initialWardCode = null, getWards = null } = {}) {
    let sequence = 0, aborter = null;
    const provincesSafe = Array.isArray(provinces) ? provinces.filter(p => p && typeof p.code === 'string' && typeof p.name === 'string' && typeof p.dataset_version === 'string') : [];
    return {
        provinces: provincesSafe,
        provinceCode: initialProvinceCode,
        wardCode: initialWardCode,
        wards: [],
        loading: false,
        error: null,
        get available() { return typeof getWards === 'function'; },
        async changeProvince(code) {
            sequence += 1;
            aborter?.abort();
            this.wardCode = null;
            this.wards = [];
            this.error = null;
            const current = sequence;
            this.provinceCode = typeof code === 'string' ? code : null;
            const province = provincesSafe.find(p => p.code === this.provinceCode);
            this.loading = false;
            if (!province || !this.available) return;
            aborter = new AbortController();
            const controller = aborter;
            this.loading = true;
            try {
                const wards = await getWards(this.provinceCode, { signal: controller.signal });
                if (current !== sequence || controller.signal.aborted) return;
                if (!Array.isArray(wards)) throw new TypeError('Invalid ward list');
                this.wards = wards.filter(w => w && typeof w.code === 'string' && typeof w.name === 'string' && w.parent_code === province.code && w.dataset_version === province.dataset_version);
            } catch (error) {
                if (current !== sequence || controller.signal.aborted) return;
                this.error = 'Không thể tải xã/phường. Vui lòng thử lại.';
            } finally {
                if (current === sequence) this.loading = false;
            }
        },
        selectWard(code) { this.wardCode = this.wards.some(w => w.code === code) ? code : null; },
        async retry() { return this.changeProvince(this.provinceCode); },
        async initialize() {
            const initialWard = this.wardCode;
            await this.changeProvince(this.provinceCode);
            if (this.wards.some(w => w.code === initialWard)) this.wardCode = initialWard;
        },
        destroy() { sequence++; aborter?.abort(); this.loading = false; },
    };
}
