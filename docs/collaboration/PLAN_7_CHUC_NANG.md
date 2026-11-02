# Kế hoạch làm 7 chức năng tối thiểu — chia việc Claude / Codex

Repo: https://github.com/httrung0308/cena-store-laravel · Ngày lập: 02/11/2026
Phạm vi: **chỉ 7 chức năng** leader giao. Không làm OTP, coupon, checkout, thanh toán… trong đợt này.

| # | Chức năng | Ai làm |
|---|---|---|
| 1 | Dữ liệu tỉnh/phường (nguồn diachiviet.com, CC BY 4.0) | **Claude** |
| 2 | Đăng ký, đăng nhập, đăng xuất | **Claude** |
| 3 | Sổ địa chỉ (thêm, sửa, xóa, chọn mặc định) | **Claude** |
| 4 | Quản lý sản phẩm (danh mục, màu, size, ảnh) | **Codex** |
| 5 | Phiếu nhập kho | **Codex** |
| 6 | Trang danh sách và chi tiết sản phẩm | **Codex** |
| 7 | Giỏ hàng | **Claude** |

Cách chia: **Claude làm nhánh "khách hàng"** (tài khoản → địa chỉ → giỏ), **Codex làm nhánh "hàng hóa"** (sản phẩm → kho → trang bán). Hai nhánh gần như không đụng file của nhau; chỉ gặp nhau ở chức năng 7 (giỏ cần trang sản phẩm của Codex).

---

## 1. Thứ tự và phụ thuộc

```
PR-0  Claude: nền chung (routes, layout, middleware, Alpine)      ← làm đầu tiên, cả hai chờ PR này
        │
        ├── Claude: PR-C1 Đăng nhập ─► PR-C2 Tỉnh/phường ─► PR-C3 Sổ địa chỉ ─┐
        │                                                                      ├─► PR-C4 Giỏ hàng
        └── Codex:  PR-X1 Danh mục ─► PR-X2 Sản phẩm ─► PR-X3 Nhập kho ─► PR-X4 Trang SP ─┘
```

| PR | Bên | Nội dung | Phụ thuộc | Ước lượng |
|---|---|---|---|---|
| PR-0 | Claude | Nền chung (mục 3) | `main` hiện tại | 0,5 ngày |
| PR-C1 | Claude | Đăng ký / đăng nhập / đăng xuất | PR-0 | 1 ngày |
| PR-C2 | Claude | Dataset tỉnh/phường + validator + seeder | PR-0 | 1 ngày |
| PR-C3 | Claude | Sổ địa chỉ | C1, C2 | 0,5–1 ngày |
| PR-C4 | Claude | Giỏ hàng (khách + user, gộp giỏ khi đăng nhập) | C1, **X4** | 1 ngày |
| PR-X1 | Codex | Admin: danh mục 2 cấp + nhà cung cấp tối giản | PR-0 | 0,5–1 ngày |
| PR-X2 | Codex | Admin: sản phẩm + phối màu + size + ảnh | X1 | 1,5 ngày |
| PR-X3 | Codex | Admin: phiếu nhập kho (nháp → ghi sổ → hủy nháp) | X2 | 1 ngày |
| PR-X4 | Codex | Trang `/shoes` (lọc, sắp xếp, phân trang) + trang chi tiết | X2 (X3 để có tồn kho) | 1 ngày |

Trong lúc chờ X4, Claude viết sẵn backend giỏ hàng (CartService + API + test) ở nhánh C4; chỉ gắn nút "Thêm vào giỏ" sau khi X4 đã vào `main`.

---

## 2. Quy trình chung cho MỌI PR (cả hai bên)

1. `git fetch origin` → tạo nhánh mới **từ `origin/main` mới nhất**, đặt tên `feat/<ma-pr>-<ten-ngan>` (vd. `feat/c1-auth`, `feat/x2-products`).
2. **Làm trong worktree riêng** của mình (`git worktree add ...`). Không làm trong thư mục của bên kia, không `reset/stash/checkout` nhánh của bên kia.
3. Chỉ sửa file thuộc quyền của mình (mục 4). Cần sửa file bên kia → nhắn qua người dùng, không tự sửa.
4. Có test PHPUnit cho: trường hợp đúng, trường hợp sai, kiểm quyền (user A không đụng được dữ liệu user B; khách không vào được trang admin). Chạy test hẹp, rồi `vendor/bin/pint --dirty --format agent`.
5. Tự mở trình duyệt kiểm giao diện (desktop + điện thoại).
6. Ghi một mục mới **ở cuối** `docs/ai-progress.md` (chỉ thêm, không sửa mục cũ).
7. Trước khi merge: `git merge origin/main` vào nhánh mình (nếu xung đột ở `ai-progress.md` thì giữ cả hai mục), push, mở PR vào `main`.
8. **Chờ CI xanh** (`gh pr checks <số> --watch`), rồi `gh pr merge <số> --squash --admin --delete-branch`. CI đỏ thì sửa, không merge.
9. Sau khi merge, nhắn người dùng: "PR-xx đã vào main". Bên kia `git fetch` và merge `origin/main` vào nhánh đang làm.

Quy định giữ nguyên từ AGENTS.md: không thêm thư viện, không sửa lockfile, **không tạo migration mới** (schema 34 bảng đã đủ cho 7 chức năng; nếu thật sự thiếu cột thì dừng lại hỏi), test chỉ chạy trên MySQL test `cena_store_test`, **không chạy 2 bộ test cùng lúc trên cùng schema test**, không đổi giao diện gốc của Cena.

**Tránh đụng schema test:** khi cả hai bên cùng chạy test, mỗi bên phải dùng **schema test riêng**. Claude dùng `cena_store_test`; Codex tạo schema `cena_store_test_codex` + user riêng theo README và trỏ `.env.testing` của worktree Codex vào đó. Nếu chưa làm được thì báo người dùng để chạy test luân phiên.

---

## 3. PR-0 — Nền chung (Claude làm trước, ~0,5 ngày) — ĐÃ CÓ TRÊN MAIN

Tên chốt sau PR-0 (dùng đúng các tên này):
- Route khách: `shoes.index` (`GET /shoes`, tham số `gender` = `men|women|kids|unisex`) — Codex thay trang "Sắp ra mắt" ở PR-X4; chi tiết đặt tên `shoes.show`.
- Route admin (trong `routes/admin.php`, đã tự có prefix `/admin`, tên `admin.`, middleware `auth` + `role:admin,staff`): `admin.dashboard` có sẵn; menu trái tự hiện khi có `admin.categories.index`, `admin.suppliers.index`, `admin.products.index`, `admin.stock-imports.index`. Thao tác chỉ admin: thêm `->middleware('role:admin')`.
- Layout: `@extends('layouts.store')` cho trang khách, `@extends('layouts.admin')` cho trang quản trị; đặt `@section('title')` và `@section('content')`; thêm JS riêng của trang bằng `@push('scripts')`.
- Alpine đã đăng ký: `addressSelector`, `colorVariants`, `dialogController`, `galleryController`, `passwordField`, `productImages`, `toastsController`, `variantPicker`.


Mục đích: tạo sẵn các file mà cả hai bên đều cần, để sau đó không ai phải sửa chung một file.

- `routes/web.php`: chỉ giữ trang chủ và nạp 4 file route riêng:
  - `routes/storefront.php` (Codex) — `/shoes`, `/shoes/{slug}`
  - `routes/admin.php` (Codex) — prefix `/admin`, middleware `auth` + `role:admin,staff`
  - `routes/account.php` (Claude) — đăng nhập/đăng ký/đăng xuất, `/account/addresses`, API phường
  - `routes/cart.php` (Claude) — giỏ hàng
- Middleware `EnsureUserHasRole` (alias `role`), kiểm user còn active và có vai trò hiện hành.
- Layout:
  - `layouts/store.blade.php`: navbar + footer gốc Cena, cho các trang khách.
  - `layouts/admin.blade.php`: khung trang quản trị tối giản (menu trái: Danh mục, Nhà cung cấp, Sản phẩm, Nhập kho).
- `resources/js/app.js`: đăng ký **một lần** tất cả module Alpine đã có (dialog, toasts, password-field, address-selector, color-variants, product-images, gallery, variant-picker). Sau PR-0 không ai sửa `app.js` nữa; cần module mới thì tạo file riêng và nhắn người dùng.
- Navbar: các mục Men/Women/Kids/Unisex trỏ về `/shoes?gender=men|women|kids|unisex` (trang `/shoes` do Codex làm ở PR-X4; trước khi X4 merge, route tạm trả về trang "đang cập nhật").
- Tài khoản admin/staff/customer cho máy dev: dùng `DemoUsersSeeder` có sẵn (bật `STORE_DEMO_USERS=true` + `STORE_DEMO_PASSWORD` riêng trong `.env`, rồi `php artisan db:seed --class=CoreFoundationSeeder`).
- Test có vai trò: `User::factory()->administrator()|staff()|customer()->create()` rồi `$this->actingAs($user)` — Codex viết test admin được **trước khi** có trang đăng nhập.
- Đưa file kế hoạch này vào `docs/collaboration/PLAN_7_CHUC_NANG.md`.

---

## 4. Phân quyền file — ai được sửa file nào

| Khu vực | Claude | Codex |
|---|---|---|
| `routes/web.php`, `resources/js/app.js`, `bootstrap/app.php` | ✅ chỉ trong PR-0 | ❌ |
| `routes/account.php`, `routes/cart.php` | ✅ | ❌ |
| `routes/admin.php`, `routes/storefront.php` | ❌ | ✅ |
| `app/Http/Controllers/Auth/*`, `Account/*`, `Cart/*` | ✅ | ❌ |
| `app/Http/Controllers/Admin/*`, `Storefront/*` | ❌ | ✅ |
| `app/Services/Auth*`, `Address*`, `AdministrativeUnit*`, `Cart*` | ✅ | ❌ |
| `app/Services/ProductManagement*`, `StockImport*`, `CatalogQuery*`, `Media*` | ❌ | ✅ |
| `app/Services/InventoryService.php` (đã có) | chỉ đọc | chỉ **gọi**, không sửa |
| `app/Http/Requests/Auth|Account|Cart/*` | ✅ | ❌ |
| `app/Http/Requests/Admin|Storefront/*` | ❌ | ✅ |
| `resources/views/auth/*`, `account/*`, `cart/*`, `components/auth|account|cart/*` | ✅ | ❌ |
| `resources/views/admin/*`, `catalog/*`, `components/admin|catalog/*` | ❌ | ✅ |
| `resources/views/components/storefront/navigation.blade.php` (icon tài khoản, giỏ) | ✅ | ❌ |
| `layouts/store.blade.php`, `layouts/admin.blade.php` | ✅ tạo ở PR-0 | ❌ (cần sửa thì nhắn) |
| `database/data/*`, seeder địa giới | ✅ | ❌ |
| Seeder sản phẩm/nhập kho demo | ❌ | ✅ |
| `config/store.php` | chỉ **thêm** key mới | chỉ **thêm** key mới |
| `docs/ai-progress.md` | chỉ thêm mục cuối | chỉ thêm mục cuối |
| Models, enums, migrations, Money/Cost/Clock, Resources | ❌ không sửa (cần thì hỏi) | ❌ không sửa (cần thì hỏi) |

---

## 5. Chi tiết từng PR

### Claude

**PR-C1 — Đăng ký, đăng nhập, đăng xuất**
- Trang `/register`, `/login` dùng form EXT-013/014 + nền video; `POST /logout`.
- Đăng ký: chỉ nhận họ tên, email, SĐT, mật khẩu; server tự gán vai trò `customer`; tạo giỏ trống.
- Đăng nhập: chặn tài khoản bị khóa/xóa; giới hạn 5 lần sai / phút; tạo session mới (chống chiếm phiên); admin/staff vào `/admin`, khách quay lại trang trước.
- Navbar: icon tài khoản hiện "Đăng nhập" hoặc tên + "Đăng xuất".
- Test: đăng ký không tự nâng quyền admin, email trùng, mật khẩu sai, tài khoản khóa, khách vào `/admin` bị chặn.

**PR-C2 — Dữ liệu tỉnh/phường**
- Tải `tinh.json`, `xa.json`, `giay-phep.txt` từ diachiviet.com, lưu mã băm file gốc.
- Chuyển sang `database/data/administrative_units.json` + `manifest.json` đúng hợp đồng EXT-001 (mã chuỗi, `PROVINCE`/`WARD`, parent, `dataset_version`, nguồn + giấy phép + ghi công).
- Validator + `AdministrativeUnitSeeder` (chạy 2 lần không nhân bản); gắn mã TP.HCM cho phí ship.
- API nội bộ `GET /api/provinces`, `GET /api/provinces/{code}/wards` cho ô chọn địa chỉ.
- Test: phường đúng tỉnh, mã giữ số 0 đầu, seed lại không trùng, checksum sai thì dừng.

**PR-C3 — Sổ địa chỉ**
- `/account/addresses`: danh sách, thêm, sửa, xóa mềm, đặt mặc định (luôn chỉ 1 mặc định).
- Ô chọn tỉnh → phường (EXT-019); lưu kèm tên/mã snapshot.
- Chỉ thấy và sửa địa chỉ của mình (người khác → 404).

**PR-C4 — Giỏ hàng**
- `CartService`: giỏ server cho cả khách (cookie token) và user; thêm / đổi số lượng (1–10) / xóa; chặn vượt tồn kho còn bán (`stock − reserved`) và sản phẩm ngừng bán; `version` tăng mỗi lần sửa.
- Gộp giỏ khách vào giỏ tài khoản khi đăng nhập/đăng ký.
- API JSON + drawer giỏ Alpine theo giao diện gốc (EXT-032); số món trên icon giỏ.
- Nối nút "Thêm vào giỏ" trên trang chi tiết của Codex (chỉ thêm `data-*`/sự kiện, không đổi bố cục trang của Codex).
- **Không giữ hàng** khi thêm giỏ (giữ hàng chỉ xảy ra lúc checkout — ngoài phạm vi đợt này).

### Codex

**PR-X1 — Danh mục và nhà cung cấp (admin)**
- `/admin/categories`: danh sách, tìm, thêm/sửa, ẩn (tối đa 2 cấp, slug duy nhất, nhóm giới tính).
- `/admin/suppliers`: danh sách, thêm/sửa, ngừng hợp tác (cần cho phiếu nhập).
- Dùng component EXT-021/022. Không xóa cứng bản ghi đã dùng.

**PR-X2 — Sản phẩm, phối màu, size, ảnh (admin)**
- `ProductManagementService`: tạo/sửa sản phẩm kèm nhiều phối màu, mỗi màu nhiều size (dùng `ShoeSizeSequence` sinh dải size), ảnh upload vào storage public (kiểm MIME thật JPEG/PNG/WebP) hoặc URL ngoài.
- Form **không** có tồn kho, giá vốn (chỉ InventoryService được ghi các cột đó).
- Ẩn/ngừng bán thay vì xóa cứng khi đã phát sinh chứng từ.
- Dùng component EXT-023/024/026.

**PR-X3 — Phiếu nhập kho (admin)**
- `StockImportService`: tạo phiếu nháp (chọn NCC, thêm dòng SKU/số lượng/giá nhập, server tự tính tổng) → **Ghi sổ**: khóa phiếu, gọi `InventoryService::receive()` cho từng dòng trong cùng 1 transaction → phiếu thành POSTED, không sửa được.
- Ghi sổ lần 2 không cộng tồn lần 2; hủy chỉ áp dụng cho phiếu nháp.
- Seeder demo: tạo vài phiếu nhập POSTED **qua service** (không gán tồn kho trực tiếp).

**PR-X4 — Trang danh sách và chi tiết sản phẩm**
- `/shoes`: lọc giới tính/danh mục/màu/size/khoảng giá, sắp xếp (mới nhất, giá tăng/giảm, tên), phân trang giữ bộ lọc; dùng EXT-027/028/029 + giao diện Cena.
- `/shoes/{slug}`: gallery theo màu, chọn size; size hết hàng (`stock − reserved = 0`) bị mờ; giá hiệu lực = giá riêng của size → giá khuyến mãi → giá gốc.
- Để sẵn nút "Thêm vào giỏ" với `data-variant-id` (Claude nối logic ở PR-C4).
- Navbar Men/Women/Kids/Unisex đã được Claude trỏ sẵn về `/shoes?gender=men|women|kids|unisex` ở PR-0; Codex chỉ cần route `/shoes` đọc đúng tham số `gender` này.

---

## 6. Hoàn thành khi nào

- 8 PR (PR-0, C1–C4, X1–X4) đều đã merge vào `main` với CI xanh.
- Kịch bản kiểm tra cuối (Claude chạy sau khi C4 merge): admin tạo sản phẩm → nhập kho → khách thấy sản phẩm trên `/shoes`, chọn size → thêm giỏ khi chưa đăng nhập → đăng ký → giỏ được gộp → thêm địa chỉ có tỉnh/phường.
- `docs/STATUS.md` cập nhật lại tiến độ.

---

## 7. Prompt dán cho Codex

> Copy nguyên khối dưới đây vào Codex, **sau khi PR-0 của Claude đã merge vào main**.

```
Bạn là Codex, làm phần "hàng hóa" của đợt 7 chức năng cho repo httrung0308/cena-store-laravel.
Đọc trước: AGENTS.md, CONTRIBUTING.md, docs/collaboration/PLAN_7_CHUC_NANG.md (kế hoạch chung, đã có trên main),
docs/specification.md phần P03.02, P03.03, P04.01, P05.01, WF-05, WF-06, WF-07, và docs/NGHIEP_VU_ERD_CHUC_NANG.md.

Việc của bạn, làm TUẦN TỰ, mỗi việc 1 PR riêng vào main:
  PR-X1 Danh mục 2 cấp + nhà cung cấp (admin)
  PR-X2 Sản phẩm + phối màu + size + ảnh (admin)
  PR-X3 Phiếu nhập kho: nháp -> ghi sổ (gọi InventoryService) -> hủy nháp
  PR-X4 Trang /shoes (lọc, sắp xếp, phân trang) + trang chi tiết /shoes/{slug}
Chi tiết từng PR xem mục 5 của PLAN_7_CHUC_NANG.md.

Bắt buộc:
- Claude đang làm song song phần tài khoản/địa chỉ/giỏ hàng. CHỈ sửa file thuộc cột "Codex" ở mục 4 của plan.
  Không sửa routes/web.php, resources/js/app.js, bootstrap/app.php, layouts, navigation, models, enums, migrations,
  InventoryService, Money/Cost/Clock, Resources. Cần đổi các file đó thì DỪNG và ghi rõ yêu cầu cho người dùng.
- Mỗi PR: tạo nhánh feat/xN-... từ origin/main mới nhất, làm trong git worktree riêng, không đụng thư mục/nhánh của Claude.
- Test MySQL thật. Dùng schema test riêng cena_store_test_codex (không dùng chung cena_store_test với Claude).
  Nếu chưa tạo được schema riêng thì báo người dùng để chạy test luân phiên, không chạy song song trên cùng schema.
- Không thêm dependency, không sửa lockfile, không tạo migration mới. Giữ giao diện Cena gốc, dùng lại component EXT có sẵn.
- Test cho trường hợp đúng, sai và kiểm quyền (khách/customer không vào được /admin; staff/admin theo plan).
- Trước khi merge: git merge origin/main vào nhánh, chạy lại test liên quan + pint --dirty, push, mở PR vào main,
  chờ CI xanh bằng `gh pr checks <số> --watch`, rồi `gh pr merge <số> --squash --admin --delete-branch`.
  CI đỏ thì sửa, không merge. Mỗi lần chỉ merge 1 PR.
- Ghi một mục mới ở CUỐI docs/ai-progress.md cho mỗi PR (chỉ thêm, không sửa mục cũ).
- Sau mỗi PR đã merge, báo: "PR-Xn đã vào main: <link PR>, CI <link run>, các file đã sửa".
- PR-X4: để nút "Thêm vào giỏ" có data-variant-id cho size đang chọn; KHÔNG tự viết logic giỏ hàng (Claude làm ở PR-C4).
```
