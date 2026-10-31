# EXT-001 — Nghiên cứu nguồn dữ liệu địa giới hai cấp, hợp đồng snapshot v1 (P01.04 / PREP)

- **Upstream / base:** `httrung0308/cena-store-laravel:main`; **BASE_SHA thực tế** `d2771892dddc6aa20f8fce29dc11df59b3f566a7` (đọc lại GitHub ngày 31/10/2026).
- **Ngày nghiên cứu:** 2026-10-31, giờ Việt Nam. **Trạng thái:** **RESEARCH ONLY / BLOCKED DATASET** — chưa xác minh đủ nguồn và chưa được owner duyệt; **không tạo JSON/manifest giả, không import, không seed**.
- **Phạm vi EXT-001:** `docs/collaboration/data-source-proposal.md`, và **chỉ khi đủ bằng chứng** mới thêm `database/data/proposals/administrative_units.json` + `database/data/proposals/manifest.json`. Hai file sau **chưa tồn tại trong đầu ra này**. Không triển khai validator EXT-002 hay chỉnh runtime/DB.
- **Hợp đồng dữ liệu/manifest v1:** áp dụng phần **§2–§3 (wire format)** của tài liệu tích hợp đính kèm “TASK EXT-002 — VALIDATOR DATASET ĐỊA GIỚI, HỢP ĐỒNG V1 ĐÃ CHỐT” do owner cung cấp. Các phần API/implementation/test của EXT-002 **không thuộc** EXT-001. Trên `main` tại SHA trên chưa có `docs/collaboration/administrative-data-contract.md`; có Draft PR core P01.04 chưa merge, không xem đó là code/contract đã nhập main.

## 1. Đối chiếu hợp đồng và boundary

Đã đọc `README.md`, `AGENTS.md`, `CONTRIBUTING.md`, `docs/specification.md` (DB-05, WF-04, WF-24, P01.04), `docs/architecture/decisions.md`, `docs/ai-progress.md`, `docs/database/schema.md`, `app/Models/AdministrativeUnit.php` và `app/Enums/AdministrativeUnitLevel.php` trên `main`. `.ai/rules/index.md` không có tại ref này.

Bảng `administrative_units` có `code VARCHAR(20)`, `name VARCHAR(150)`, `level VARCHAR(20)`, `parent_id` FK tự tham chiếu, `dataset_version VARCHAR(50)`, `valid_from DATE`, `valid_to DATE NULL`, `is_active` và timestamps; `UNIQUE(dataset_version, code)`. `level` chỉ là `PROVINCE`/`WARD` theo DB CHECK và enum đã có; province không parent, ward phải có parent. `parent_code` là **khóa ngoại logic của file trao đổi**, importer tương lai do owner resolve sang `parent_id`; **không thêm cột database**.

WF-04 yêu cầu địa chỉ hai cấp tỉnh → xã/phường/đặc khu, giữ mã chuỗi và snapshot đơn/địa chỉ cũ. WF-24/P01.04 là phần owner: foundation seeding, data production, gắn shipping/config sau khi dữ liệu được duyệt. V1 là **một snapshot hiện hành**, **một `dataset_version`**, mỗi hàng là đơn vị có thể chọn tại ngày `effective_as_of`; không trộn huyện, bản ghi ngừng hiệu lực hoặc nhiều version. Không tự gán một ngày hiệu lực từ mốc văn bản nếu chưa có quyết định của owner.

## 2. Nguồn pháp lý và nguồn dữ liệu ứng viên

| Tài liệu/nguồn | Mốc, phạm vi, mức tin cậy | Cách dùng / hạn chế |
|---|---|---|
| [QĐ 19/2025/QĐ-TTg, Chính phủ](https://vanban.chinhphu.vn/?docid=214409&pageid=27160); [thông tin danh mục 34/3.321](https://baochinhphu.vn/bang-danh-muc-va-ma-so-cua-34-tinh-thanh-moi-3321-don-vi-hanh-chinh-cap-xa-moi-102250704153652947.htm) | Ban hành 30/06/2025, **hiệu lực 01/07/2025**; Phụ lục I/II quy định mã tỉnh 2 chữ số và cấp xã 5 chữ số; **34 tỉnh/thành, 3.321 đơn vị cấp xã tại mốc này** | **Nguồn pháp lý gốc** để đối chiếu code/name/parent. Không coi phụ lục 2025 là danh mục cập nhật đầy đủ năm 2026. |
| [NQ 30/2026/QH16 — Đồng Nai](https://vanban.chinhphu.vn/?classid=1&docid=218009&pageid=27160) | Hiệu lực **30/04/2026** | Đối chiếu tên/loại hình thành phố Đồng Nai và các văn bản cấp xã liên quan. |
| [NQ 36/2026/QH16 — Quảng Ninh](https://vanban.chinhphu.vn/?classid=1&docid=219325&orggroupid=1&pageid=27160) | Hiệu lực **01/09/2026** | Đối chiếu loại hình thành phố Quảng Ninh. |
| [NQ 39/2026/QH16 — Bắc Ninh](https://vanban.chinhphu.vn/?docid=219328&pageid=27160) | Hiệu lực **20/09/2026** | Đối chiếu loại hình thành phố Bắc Ninh. |
| [Địa Chỉ Việt — dữ liệu mở](https://diachiviet.com/du-lieu-mo/) | **Ứng viên bên thứ ba**, trang ghi cập nhật 24/10/2026, file tạo 29/10/2026, theo văn bản tới 20/09/2026, công bố **34/3.321**; **CC BY 4.0** cho phần tổng hợp | [tinh.json](https://diachiviet.com/wp-content/uploads/dvc/open-data/tinh.json), [xa.json](https://diachiviet.com/wp-content/uploads/dvc/open-data/xa.json), [giấy phép/nguồn](https://diachiviet.com/wp-content/uploads/dvc/open-data/giay-phep.txt). File có thể tái tạo/thay đổi theo thời gian, phải lưu bytes và hash khi tải. Chính website ghi **không phải bản chính thức**, có thể có sai sót. Khi dùng phải attribution đúng license. |
| [Open Admin Data — Vietnam](https://github.com/open-admin-data/vietnam-administrative-divisions) | Ứng viên dữ liệu máy đọc được, [LICENSE CC BY 4.0](https://github.com/open-admin-data/vietnam-administrative-divisions/blob/main/LICENSE); repo có mã kiểu string và parent | Không coi nhánh `main` hoặc tập tỉnh đơn lẻ là snapshot đầy đủ. Đã thấy `data/all-province.json` có 34 hàng; `data/all-ward.json` **chưa lấy/đối chiếu trọn bytes**. Phải pin commit SHA và rà cập nhật năm 2026 trước khi dùng. |
| [Workira — nguồn và phương pháp](https://tracuudonvihanhchinh.workira.vn/nguon-du-lieu) | Nguồn đối chiếu độc lập có công bố phương pháp/danh mục | Không thay thế văn bản gốc; cần tự kiểm giấy phép tái phân phối nếu trích dữ liệu của họ. |

**Giấy phép/pháp lý:** văn bản hành chính nhà nước có chế độ quyền tác giả khác với bản biên soạn bên thứ ba; không suy từ việc văn bản pháp luật công khai rằng mọi bộ JSON của bên thứ ba đều tự do sử dụng. Cần lưu license URL, attribution, phạm vi thay đổi và ghi nhận ngày lấy dữ liệu. Ngày hiệu lực của từng nghị quyết pháp lý **không mặc nhiên** là `valid_from` của từng record trong V1.

**Tồn đọng quan trọng:** chưa tải, lưu bytes gốc và đối chiếu độc lập trọn bộ mã/tên/parent của **3.321** xã/phường/đặc khu với phụ lục và mọi văn bản có hiệu lực tới snapshot do owner duyệt. Chưa chứng minh được có/không có sửa đổi mới hơn mốc nhà cung cấp công bố. **Không phát hành danh mục/manifest chưa qua audit.**

## 3. Định dạng chính xác của `administrative_units.json` — wire contract v1 đã chốt

Root là **JSON array không rỗng**, mọi phần tử là JSON object có **đúng 8 field** sau, không thừa/thiếu field (kể cả field có giá trị `null`):

| Field | Kiểu/chính sách bắt buộc |
|---|---|
| `code` | **String ASCII**, không tự padding/trim/cast. `PROVINCE` khớp toàn chuỗi `\A[0-9]{2}\z`, `WARD` khớp toàn chuỗi `\A[0-9]{5}\z` (regex PHP); giữ mọi số 0 đầu. |
| `name` | **String UTF-8 hợp lệ**, 1..150 ký tự Unicode, không toàn whitespace, không whitespace đầu/cuối; **không trim/normalize/sửa tên**. Lấy đúng tên đơn vị theo tài liệu pháp lý đã audit. |
| `level` | Chính xác `PROVINCE` hoặc `WARD` (xã/phường/đặc khu cùng là WARD), không `DISTRICT`. |
| `parent_code` | Field **luôn có**: province `null`; ward là **string** tỉnh có đúng 2 chữ số, tham chiếu một `PROVINCE` thuộc **cùng snapshot/version**, bất kể thứ tự hàng. |
| `dataset_version` | String 1..50 ký tự, khớp toàn chuỗi `\A[A-Za-z0-9][A-Za-z0-9._-]{0,49}\z`, **bằng `manifest.dataset_version` ở mọi hàng**. Không buộc prefix/ngày. |
| `valid_from` | Ngày thật `YYYY-MM-DD` (năm 1000..9999), **bằng chính xác `manifest.effective_as_of` ở mọi hàng**. Đây là **ngày hiệu lực toàn snapshot**, không phải lịch sử riêng từng xã. |
| `valid_to` | **`null` bắt buộc** trong snapshot hiện hành v1 (không trộn bản ghi hết hiệu lực). |
| `is_active` | **Boolean `true` bắt buộc**, không chấp nhận `1`, chuỗi `"true"`, `false` hoặc `null`. |

Khóa duy nhất **`(dataset_version, code)`**; so mã dưới dạng string nguyên bản (không làm mất số 0 đầu do numeric key), phân loại level và parent đầy đủ. Không ràng buộc thứ tự phần tử/key; khi xuất có thể sắp để dễ review nhưng **hợp lệ không phụ thuộc sort**. Không có `id`, `parent_id`, huyện hay địa chỉ lịch sử trong file v1.

## 4. Định dạng chính xác của `manifest.json` — wire contract v1 đã chốt

Root là **JSON object** có đúng **15 field bắt buộc** dưới đây, và **duy nhất `source_sha256` là field tùy chọn**. Từ chối field lạ. Không có giá trị mặc định cho ngày/version/mã HCM của dataset thật.

| Field | Ràng buộc |
|---|---|
| `schema_version` | **Integer `1`** (không chuỗi `"1"`, boolean hoặc float). |
| `dataset_version` | Cùng regex và 1..50 ký tự như records; **một version trên toàn snapshot**. |
| `hcm_province_code` | **String 2 chữ số ASCII**, trỏ một `PROVINCE` cùng version; với dataset thật owner/EXT-001 phải **đối chiếu nguồn pháp lý xác nhận đúng TP. Hồ Chí Minh**, không suy từ dữ liệu test hoặc hardcode config. |
| `effective_as_of` | Ngày thật `YYYY-MM-DD`, năm 1000..9999, ngày hiệu lực snapshot **do owner duyệt**. |
| `retrieved_at` | Ngày thật `YYYY-MM-DD`, năm 1000..9999; ngày tải **theo giờ Việt Nam**, **không trước `effective_as_of`**. |
| `source_urls` | **JSON array không rỗng** chứa URL HTTPS hợp lệ, không `username/password`. |
| `legal_basis_urls` | **JSON array không rỗng** chứa URL HTTPS hợp lệ, không `username/password`. |
| `license` | String UTF-8 không rỗng/sau kiểm whitespace; kiểm nội dung giấy phép nguồn riêng. |
| `license_url` | URL HTTPS hợp lệ, không userinfo. |
| `attribution` | String UTF-8 không rỗng/sau kiểm whitespace, ghi nguồn/biên soạn/chuyển đổi. |
| `province_count` | Integer ≥ 1, bằng số hàng `PROVINCE` thực tế. |
| `ward_count` | Integer ≥ 1, bằng số hàng `WARD` thực tế. |
| `record_count` | Integer ≥ 2, bằng tổng hai count và số hàng thật. |
| `file` | **Chính xác** string `administrative_units.json`; metadata **không** phải lệnh đọc một path tùy ý. |
| `sha256` | **64 ký tự hex thường** `[0-9a-f]{64}`, SHA256 của **bytes nguyên vẹn** `administrative_units.json` đã tạo (không hash manifest). |
| `source_sha256` *(tùy chọn)* | **JSON object không rỗng**, key là nhãn nguồn string không rỗng; value 64 hex thường; ghi hash **bytes gốc từng nguồn**, không coi là đã xác minh pháp lý. Không chấp nhận JSON array. |

**Quy trình kiểm:** (1) chọn nguồn + ngày snapshot và ghi quyết định duyệt; (2) tải/lưu bytes nguồn; (3) đối chiếu từng mã, tên, level, parent với văn bản có hiệu lực; (4) chuẩn hóa v1 **không tự sửa dữ liệu sai**; (5) ghi JSON UTF-8 rồi **hash chính bytes vừa ghi**; (6) ghi manifest ngày tải thực theo VN và source hashes; (7) checksum so lại từ file cùng bytes. Tái xuất/đổi newline/BOM/whitespace phải tính hash mới. Verify checksum **không đồng nghĩa** nội dung đúng hoặc có quyền tái phân phối. Không hardcode số 34/3321/3355 vào hợp đồng validator: đây chỉ là **mốc đối chiếu của nguồn 2025**, không phải invariant của mọi version.

## 5. Quy trình tái tạo ứng viên (chỉ sau khi owner xác minh schema nguồn và chốt snapshot)

Các lệnh dưới đây là **hướng dẫn tái tạo**, **NOT RUN trên dữ liệu nguồn đầy đủ** trong phiên này. Chỉ ghi ra **`database/data/proposals/`**, không gọi seeder, DB, HTTP runtime hay validator EXT-002. Nguồn có thể đổi schema/bytes theo thời gian: nếu có bất thường, **dừng** và audit, không đoán field hoặc sửa tên để vượt kiểm.

Lưu nguồn gốc **ngoài repository** và chụp checksum bytes ngay khi tải:

```bash
mkdir -p /tmp/cena-ext001 database/data/proposals
curl --fail --location --show-error --silent \
  'https://diachiviet.com/wp-content/uploads/dvc/open-data/tinh.json' \
  -o /tmp/cena-ext001/tinh.json
curl --fail --location --show-error --silent \
  'https://diachiviet.com/wp-content/uploads/dvc/open-data/xa.json' \
  -o /tmp/cena-ext001/xa.json
curl --fail --location --show-error --silent \
  'https://diachiviet.com/wp-content/uploads/dvc/open-data/giay-phep.txt' \
  -o /tmp/cena-ext001/giay-phep.txt
sha256sum /tmp/cena-ext001/tinh.json /tmp/cena-ext001/xa.json /tmp/cena-ext001/giay-phep.txt
```

Trước khi chạy bước chuyển đổi, **người có quyền review** phải đặt ba biến môi trường bằng **giá trị thật đã duyệt** (không có fallback, không tự lấy ngày hiện tại cho hiệu lực): `CENA_DATASET_VERSION`, `CENA_EFFECTIVE_AS_OF`, `CENA_HCM_PROVINCE_CODE`. Bắt buộc xác minh format `{"cot": [...], "dong": [...]}` và tên cột `ma_tinh`, `ma_xa`, `ten_day_du`, `so_don_vi_cap_xa` từ **bản nguồn đã tải**; nếu tên cột khác thì **không chạy**. `retrieved_at` chỉ được đặt bằng ngày tải thực tại giờ Việt Nam; nếu tái tạo ngày khác phải tải lại nguồn, không tái dùng ngày tải hoặc checksum cũ.

```bash
python3 - <<'PY'
import hashlib
import json
import os
import re
from collections import Counter
from datetime import date, datetime
from pathlib import Path
from zoneinfo import ZoneInfo

raw = Path('/tmp/cena-ext001')
out = Path('database/data/proposals')

def required_env(key):
    value = os.environ.get(key)
    if value is None or value == '':
        raise ValueError(f'Missing owner-approved setting: {key}')
    return value

def iso_day(value):
    if not re.fullmatch(r'[0-9]{4}-[0-9]{2}-[0-9]{2}', value):
        raise ValueError('Invalid YYYY-MM-DD')
    result = date.fromisoformat(value)
    if result.year < 1000:
        raise ValueError('Year below 1000')
    return result

version = required_env('CENA_DATASET_VERSION')
assert re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9._-]{0,49}', version)
effective = iso_day(required_env('CENA_EFFECTIVE_AS_OF'))
hcm_code = required_env('CENA_HCM_PROVINCE_CODE')
assert re.fullmatch(r'[0-9]{2}', hcm_code)
retrieved = datetime.now(ZoneInfo('Asia/Ho_Chi_Minh')).date()
assert retrieved >= effective, 'Source retrieved before approved effective date'

filenames = ('tinh.json', 'xa.json')
source_urls = [
    'https://diachiviet.com/wp-content/uploads/dvc/open-data/tinh.json',
    'https://diachiviet.com/wp-content/uploads/dvc/open-data/xa.json',
]
source_bytes = {name: (raw / name).read_bytes() for name in filenames}
sources = [json.loads(source_bytes[name].decode('utf-8-sig')) for name in filenames]
assert all(isinstance(s, dict) and isinstance(s.get('cot'), list)
           and isinstance(s.get('dong'), list) and s['dong'] for s in sources)
# Field names and source-snapshot date must be audited against the downloaded files.
assert {'ma_tinh', 'ten_day_du', 'so_don_vi_cap_xa'} <= set(sources[0]['cot'])
assert {'ma_xa', 'ma_tinh', 'ten_day_du'} <= set(sources[1]['cot'])

records = []
for level, entries, digits in (
    ('PROVINCE', sources[0]['dong'], 2),
    ('WARD', sources[1]['dong'], 5),
):
    for item in entries:
        assert isinstance(item, dict)
        code = item.get('ma_tinh' if level == 'PROVINCE' else 'ma_xa')
        name = item.get('ten_day_du')
        parent = None if level == 'PROVINCE' else item.get('ma_tinh')
        assert isinstance(code, str) and re.fullmatch(r'[0-9]{%d}' % digits, code)
        assert isinstance(name, str) and 1 <= len(name) <= 150
        assert name.strip() and not name[0].isspace() and not name[-1].isspace()
        assert parent is None or (isinstance(parent, str)
                                  and re.fullmatch(r'[0-9]{2}', parent))
        records.append(dict(code=code, name=name, level=level, parent_code=parent,
                            dataset_version=version, valid_from=effective.isoformat(),
                            valid_to=None, is_active=True))

keys = ['code:' + r['code'] for r in records]  # Never coerce codes to integers.
assert len(keys) == len(set(keys)), 'Duplicate codes'
provinces = {r['code'] for r in records if r['level'] == 'PROVINCE'}
assert hcm_code in provinces, 'HCM code is not in this snapshot'
assert all(r['parent_code'] in provinces for r in records if r['level'] == 'WARD')
assert all(r['parent_code'] is None for r in records if r['level'] == 'PROVINCE')
ward_counts = Counter(r['parent_code'] for r in records if r['level'] == 'WARD')
assert all(ward_counts[p['ma_tinh']] == p['so_don_vi_cap_xa']
           for p in sources[0]['dong']), 'Published province/ward counts disagree'
# Review every name/code/parent and the actual identity of the HCM province.
# The checks above are only structural: they DO NOT establish legal correctness.
records.sort(key=lambda r: (0 if r['level'] == 'PROVINCE' else 1, r['code']))
data_bytes = (json.dumps(records, ensure_ascii=False, indent=2) + '\n').encode('utf-8')
manifest = {
    'schema_version': 1,
    'dataset_version': version,
    'hcm_province_code': hcm_code,
    'effective_as_of': effective.isoformat(),
    'retrieved_at': retrieved.isoformat(),
    'source_urls': source_urls,
    'legal_basis_urls': [
        'https://vanban.chinhphu.vn/?docid=214409&pageid=27160',
        'https://vanban.chinhphu.vn/?classid=1&docid=218009&pageid=27160',
        'https://vanban.chinhphu.vn/?classid=1&docid=219325&orggroupid=1&pageid=27160',
        'https://vanban.chinhphu.vn/?docid=219328&pageid=27160',
    ],
    'license': 'CC BY 4.0',
    'license_url': 'https://creativecommons.org/licenses/by/4.0/',
    'attribution': ('Nguồn: Địa Chỉ Việt (diachiviet.com), dữ liệu mở đơn vị '
                    'hành chính, CC BY 4.0; được chuyển đổi định dạng cho Cena Store.'),
    'province_count': len(sources[0]['dong']),
    'ward_count': len(sources[1]['dong']),
    'record_count': len(records),
    'file': 'administrative_units.json',
    'sha256': hashlib.sha256(data_bytes).hexdigest(),
    'source_sha256': {name: hashlib.sha256(source_bytes[name]).hexdigest()
                      for name in filenames},
}
assert manifest['record_count'] == manifest['province_count'] + manifest['ward_count']
# Only execute after legal audit and explicit approval of source, effective date,
# HCM code, licensing, and name/parent correspondence: this is a PROPOSAL, not production.
out.mkdir(parents=True, exist_ok=True)
data_path = out / 'administrative_units.json'
data_path.write_bytes(data_bytes)
(out / 'manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2)
                                   + '\n', encoding='utf-8')
assert hashlib.sha256(data_path.read_bytes()).hexdigest() == manifest['sha256']
print('CANDIDATE ONLY (NOT APPROVED):', len(records), manifest['sha256'])
PY
sha256sum database/data/proposals/administrative_units.json
python3 -m json.tool database/data/proposals/manifest.json >/dev/null
```

**Fail-closed / hạn chế của script:** Đây là ví dụ chuyển đổi cho **một phiên bản schema ứng viên**; không tự chứng minh provider field `ten_day_du`, `so_don_vi_cap_xa` hay giá trị là đầy đủ/chính xác. Không dùng `retrieved_at` tính lúc tái chạy để mô tả một bản tải cũ. Các kiểm này không thay kết quả chạy validator EXT-002, kiểm pháp lý, kiểm checksum độc lập hay owner approval. Nếu kiểm metadata/schema/số lượng nguồn không đạt, **không commit** bản ghi đã xuất. Không tự chỉnh tên bằng `.strip()`, tự thêm số 0, suy parent bằng tên, tự gán version/ngày/hcm code, hoặc bỏ ward thiếu nguồn.

## 6. Kiểm định và điều kiện nghiệm thu trước khi đưa dữ liệu proposals vào PR

1. Tải **bytes nguyên bản** theo URL, ghi `retrieved_at` đúng giờ VN và `source_sha256`. Giữ bản raw ngoài repo và ghim immutable snapshot nếu nhà cung cấp hỗ trợ; lần tải sau có checksum khác phải đánh giá lại.
2. Kiểm `PROVINCE`/`WARD` duy nhất, code đúng width/string/leading zero, duplicate `(version, code)`, parent đúng một province cùng version; không parent thiếu, cross-version, huyện hay bản inactive. Name chuẩn UTF-8, không whitespace dư, tối đa 150 ký tự.
3. Kiểm toàn bộ 15 metadata bắt buộc, không key lạ, optional `source_sha256` đúng map; count thực bằng manifest; date thật, `retrieved_at >= effective_as_of`, `valid_from` mọi hàng bằng `effective_as_of`, `valid_to=null`, `is_active=true`; `hcm_province_code` trỏ đúng TP.HCM có bằng chứng.
4. Đối chiếu **từng dòng** code/name/parent/cấp hành chính với phụ lục nhà nước và các thay đổi sau 01/07/2025 đến ngày owner chọn. Con số 34/3.321 chỉ là baseline lịch sử cần đối chiếu, **không thay bằng invariant cứng**. Kiểm quyền tái phân phối/attribution nguồn biên soạn.
5. Kiểm `sha256sum database/data/proposals/administrative_units.json` bằng chính `manifest.sha256`; đổi bytes dù chỉ newline/BOM cũng phải đổi checksum. **Checksum đúng chỉ xác nhận bytes**, không xác thực nguồn/nội dung pháp lý.
6. Chỉ khi đầy đủ bằng chứng và owner chấp thuận mới commit **cả hai** JSON proposals trong một cập nhật EXT-001. Import production, FoundationSeeder/DB, hồi quy địa chỉ và đánh dấu P01.04 hoàn tất **ngoài phạm vi**.

## 7. Kết quả thực tế / handoff

- **PASS:** đọc lại `main` và chốt BASE_SHA; đọc yêu cầu DB-05/WF-04/WF-24/P01.04, enum; đối chiếu metadata nguồn pháp lý, nguồn ứng viên và giấy phép đã công bố; đối chiếu schema v1 đính kèm (không lập trình validator).
- **PASS:** tài liệu đã mô tả đủ 8 field records, 15 field manifest + 1 optional, checksum bytes, name/UTF-8, version, dates, URL/attribution và HCM parent; chứa lệnh/sơ đồ tái tạo có chặn điều kiện.
- **NOT RUN:** tải bản raw đầy đủ, kiểm checksum nguồn, chạy chuyển đổi trên **bản nguồn đã audit**, đối chiếu đủ 3.321 ward và mọi thay đổi pháp lý năm 2026, checksum dataset hoàn chỉnh. **Không có dataset/manifest thật để chứng minh pass**.
- **NOT APPLICABLE:** Artisan, MySQL8.4/test DB, PHPUnit, Pint, `npm run build`, browser/keyboard test — thay đổi tài liệu nghiên cứu thuần, không sửa code/runtime/UI.
- **PENDING:** owner chọn/duyệt `effective_as_of` và `dataset_version` thật, bản raw nguồn, `hcm_province_code`, license/attribution, review toàn bộ bất thường. Không tự dùng mốc 20/09/2026 hoặc 31/10/2026 làm mặc định dữ liệu thật.

**Kết luận:** EXT-001 mới ở mốc **nghiên cứu và chuẩn hóa hợp đồng v1**; không tạo `database/data/proposals/administrative_units.json` hay `manifest.json` vì **chưa xác minh đủ dữ liệu**, không dùng dữ liệu giả, không triển khai EXT-002, không nâng trạng thái P01.04.
