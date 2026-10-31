# Tổng hợp tiến độ — 31/10/2026

Trang này tóm tắt tình trạng project để phân chia công việc. Chi tiết từng task, lệnh test và bằng chứng nằm trong [ai-progress.md](ai-progress.md); kế hoạch gốc là [specification.md](specification.md) (12 phase, 51 task, làm tuần tự).

## Tóm tắt

- **Hoàn thành 6/51 task** (P00.01 → P01.03) — đã có trên `main`.
- **Đang làm P01.04 và P01.05** — code nằm trong các PR Draft bên dưới, chưa merge.
- Người dùng hiện chỉ thấy **trang chủ** (giao diện Cena gốc). Chưa có đăng nhập, danh mục, giỏ hàng, đặt hàng, thanh toán, quản trị.
- **Điểm nghẽn:** EXT-001 (dataset tỉnh/phường + manifest) và EXT-002 (validator) chưa có → P01.04 chưa đóng → chuỗi P02 trở đi chưa mở.
- Test trên nhánh mới nhất: **819 tests / 7543 assertions PASS** (MySQL 8.4 thật, thứ tự ngẫu nhiên).

## Pull request đang mở

| PR | Nội dung | Đích | CI | Trạng thái |
|---|---|---|---|---|
| [#1](https://github.com/httrung0308/cena-store-laravel/pull/1) | P01.04 core: role, demo user opt-in, palette, cấu hình ship | `main` | PASS | Draft |
| [#2](https://github.com/httrung0308/cena-store-laravel/pull/2) | P01.05: SalesLedgerService, OutboxService, ProcessOutbox | PR #1 | PASS | Draft |
| [#4](https://github.com/httrung0308/cena-store-laravel/pull/4) | P01.05: InventoryService (giữ/xuất/nhập/cách ly, WAC, chống oversell) | PR #2 | PASS | Draft |
| [#3](https://github.com/httrung0308/cena-store-laravel/pull/3) | Component UI chuẩn bị trước EXT-007..048 (bên cộng tác) | `main` | PASS | Draft |

PR #1 → #2 → #4 xếp chồng nên phải review/merge đúng thứ tự đó. PR #3 độc lập.

## Tiến độ theo kế hoạch

| Phase | Task | Trạng thái |
|---|---|---|
| P00 — Đặc tả, môi trường | P00.01–P00.03 | ✅ Hoàn thành |
| P01 — Schema, model, tiền, dữ liệu nền | P01.01 schema 41 bảng, P01.02 model/Resource, P01.03 Money/Clock/lỗi | ✅ Hoàn thành |
| | P01.04 seed + dataset địa chỉ | 🟡 Core xong (PR #1); thiếu dataset/validator/importer EXT-001/002 |
| | P01.05 ledger, outbox, kho | 🟡 Code + test xong (PR #2, #4); chờ đóng P01.04 |
| P02 — Đăng nhập, quyền, OTP, địa chỉ | 5 task | ⬜ Chưa làm backend; form UI có ở PR #3 |
| P03 — Catalog quản trị, màu/size/ảnh | 4 task | ⬜ Chưa làm backend; form/table UI có ở PR #3 |
| P04 — Kho, phiếu nhập, giá vốn | 3 task | ⬜ Chưa làm (primitive kho đã có từ P01.05) |
| P05 — Storefront, giỏ AJAX, merge giỏ | 4 task | ⬜ Chưa làm backend; card/filter/gallery/cart line ở PR #3 |
| P06 — Giá, coupon, checkout | 4 task | ⬜ Chưa làm |
| P07 — COD, VietQR, VNPay, hoàn tiền | 5 task | ⬜ Chưa làm |
| P08 — Đơn, giao hàng, trả hàng, PDF | 4 task | ⬜ Chưa làm; UI danh sách/chi tiết/timeline đơn ở PR #3 |
| P09 — Đánh giá, hậu mãi từng đôi | 6 task | ⬜ Chưa làm; UI chi tiết RMA ở PR #3 |
| P10 — Báo cáo, outbox vận hành, demo seed | 3 task | ⬜ Chưa làm |
| P11 — Nghiệm thu, bảo mật, deploy, báo cáo | 5 task | ⬜ Chưa làm; có hướng dẫn cài cloud + kịch bản demo (EXT-047/048) |

## Gói việc bên cộng tác (EXT)

- **Đã có code trong PR #3:** EXT-003..032, 038, 039, 040, 043, 047, 048 (EXT-001 mới là tài liệu đề xuất nguồn dữ liệu). Phần lớn ở mức PREP_UNVERIFIED: CI PASS nhưng chưa kiểm render trên trình duyệt; chưa gắn vào route/trang nào.
- **Chờ hợp đồng backend (10 gói):** EXT-033/034 (P05.02), 035 (P06.01/02), 036/037 (P03.02), 041 (P08.04), 042 (P09.02), 044 (P04.01/02), 045 (P10.02), 046 (P10.01).
- **Chưa có, đang chặn tiến độ:** EXT-001 dataset + manifest, EXT-002 validator.
- Cần chốt khi review PR #3: tên khóa JSON của snapshot địa chỉ đơn hàng (EXT-039 so với factory hiện tại), và chế độ readonly của EXT-007 đang dùng `disabled` nên giá trị không được submit.

## Đề xuất phân chia (leader quyết định)

Kế hoạch là một chuỗi phụ thuộc tuần tự (P01.04 → P01.05 → P02.01 → … → P11.05). Trong lúc chờ task trước, các thành viên viết trước service + test ở nhánh riêng (PREP), chỉ gắn route khi task trước đã merge.

1. **Ngay bây giờ:** hoàn thành EXT-001/002; owner review và merge PR #1 → #2 → #4, rồi PR #3 sau khi kiểm render; đóng P01.04 và P01.05.
2. **Sau P01**, theo bảng phân công ở mục 15 của kế hoạch:
   - Nguyễn Quang Đức — P02 (đăng ký/đăng nhập, RBAC, OTP, hồ sơ, sổ địa chỉ).
   - Nguyễn Hoàng Thành — P03 (danh mục, NCC, sản phẩm, catalog) và P04 (phiếu nhập, thẻ kho).
   - Từ Hào Văn — P05–P08 (giỏ, giá/coupon, checkout, thanh toán, đơn, giao hàng).
   - Huỳnh Tiến Trung — UI chung P03.01, P09, P10, P11, tích hợp và merge.

Mỗi PR một task, CI PASS, owner review rồi mới merge. Mỗi người dùng MySQL test riêng; đọc [AGENTS.md](../AGENTS.md), [CONTRIBUTING.md](../CONTRIBUTING.md) và phần kế hoạch liên quan trước khi code.
