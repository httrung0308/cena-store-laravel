# Hướng Dẫn Kiểm Thử Dữ Liệu Mẫu (`FullDemoSeeder`)

Tài liệu này mô tả dữ liệu được tạo bởi `FullDemoSeeder` (`.\scripts\php.ps1 artisan db:seed --class=FullDemoSeeder --no-interaction`).

> **Lưu ý:** Dữ liệu mẫu sẽ thay đổi trạng thái/tồn kho khi bạn thao tác trên web (duyệt đơn, giao hàng, nhận hàng hoàn, đánh giá...). Khi muốn khôi phục lại trạng thái mẫu ban đầu trên môi trường local, chạy `.\scripts\php.ps1 artisan migrate:fresh --no-interaction` rồi chạy lại `FullDemoSeeder`.

---

## 1. Tài khoản đăng nhập mẫu

Mật khẩu dùng chung trong gói bàn giao local: `CenaDemo@2026`

| Vai trò | Email | Họ tên hiển thị | Phạm vi kiểm tra chính |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin.qa@demo.cena.test` | Quản Trị Viên QA | Tổng quan (`/admin`), Phiếu nhập kho, Duyệt hoàn tiền, Duyệt ghi hỏng hàng hoàn, Biến động kho (kèm giá vốn), Bài viết/Banner/Coupon/Người dùng |
| **Staff** | `staff.qa@demo.cena.test` | Nhân Viên QA | Công việc xử lý (`/admin/operations-work-items` — trang mặc định), Xử lý đơn, Nhận/kiểm tra hàng hoàn, Báo cáo vận hành/tồn kho (bị chặn Tổng quan & Phiếu nhập kho) |
| **Khách 1** | `khach1.qa@demo.cena.test` | Nguyễn Minh Anh | Thông báo mời đánh giá, đánh giá gộp 2 đôi giống nhau, đánh giá nhiều sản phẩm, đơn đang chờ/đóng gói/thất bại/hủy hoàn tiền |
| **Khách 2** | `khach2.qa@demo.cena.test` | Trần Bảo Ngọc | Đánh giá đã gửi (4.5 sao + phản hồi shop), đơn đang giao, đơn hoàn nhận một phần / đã xử lý |
| **Demo cơ bản** | `admin@demo.cena.test`, `staff@demo.cena.test`, `customer@demo.cena.test` | Tài khoản demo nền | Tài khoản demo cơ bản từ seeder nền |

---

## 2. Cách tìm nhanh các tình huống mẫu (không cần nhớ mã đơn dài)

Trên trang **Đơn hàng** (`/admin/orders`) hoặc **Công việc xử lý** (`/admin/operations-work-items`), dùng bộ lọc **Trạng thái** / **Hàng hoàn** hoặc tìm theo **tên người nhận** (`Nguyễn Minh Anh`, `Trần Bảo Ngọc`):

### A. Sản phẩm & Biến động kho
- **`Cena AeroStride Pro QA`** (mẫu chính, 2 màu `Đen Than`, `Trắng Kem`): có đầy đủ biến động nhập kho, giữ cho đơn, xuất kho, nhập lại hàng hoàn, ghi hỏng, đánh giá công khai (`4.5` sao) và 1 đánh giá ẩn.
- **`Cena CloudRun Lite QA`** (`Xanh Navy`):
  - Size `39`: sắp hết hàng (`stock_qty = 4`, có 1 đôi hỏng chờ Admin xử lý).
  - Size `40`: đã hết hàng (`stock_qty = 0`).
  - Hiện có **0 đánh giá công khai** để kiểm tra trạng thái chưa có đánh giá.
- **Phiếu nhập kho (chỉ Admin thấy):**
  - 1 phiếu đã ghi sổ (`POSTED`) và 1 phiếu nháp (`DRAFT`).

### B. Đơn hàng theo trạng thái
Lọc theo trạng thái ở `/admin/orders`:
- **`PENDING` (Chờ xác nhận):** đơn của `Nguyễn Minh Anh` (1 đôi `Cena AeroStride Pro QA` Đen Than 40).
- **`PACKING` (Đang đóng gói):** đơn của `Nguyễn Minh Anh` (Đen Than 41) — test bàn giao vận chuyển.
- **`ON_DELIVERY` (Đang giao):** đơn của `Trần Bảo Ngọc` (Trắng Kem 40) — test xác nhận giao thành công (kèm ảnh COD) hoặc báo giao thất bại.
- **`DELIVERED` (Đã giao):**
  - Đơn `Nguyễn Minh Anh` mua **2 đôi giống nhau** (`Đen Than 40 × 2`), **chưa đánh giá** — test lời mời đánh giá ở chuông thông báo và gộp `2 đôi` khi gửi đánh giá.
  - Đơn `Nguyễn Minh Anh` mua **2 sản phẩm khác nhau** (`Trắng Kem 41` + `Xanh Navy 39`), **chưa đánh giá**.
  - Đơn `Trần Bảo Ngọc` đã đánh giá `4.5` sao kèm phản hồi của cửa hàng.
  - Nhiều đơn lịch sử rải từ `11/2025`, `05/2026`, `08/2026`, `10/2026`, `11/2026` để xem biểu đồ báo cáo theo tuần/tháng/quý/năm.
- **`FAILED` (Giao thất bại — Hàng hoàn):**
  - **Chờ nhận:** đơn `Nguyễn Minh Anh` (2 đôi đang hoàn) — test thao tác 1 bước (`Hàng tốt — Nhập lại kho`, `Hàng hỏng`, `Chưa kiểm tra — Đưa vào cách ly`).
  - **Đã nhận một phần:** đơn `Trần Bảo Ngọc` (1 đôi ở `Hàng chờ kiểm tra`, 1 đôi chưa về kho).
  - **Hàng hỏng chờ xử lý:** đơn `Nguyễn Minh Anh` (1 đôi đã nhập lại kho, 1 đôi Staff đã ghi nhận hỏng chờ Admin duyệt ghi tổn thất).
  - **Đã xử lý:** đơn `Trần Bảo Ngọc` (1 đôi nhập lại kho, 1 đôi Admin đã ghi hỏng tổn thất).
- **`CANCELLED` (Trả trước mô phỏng chờ hoàn tiền):**
  - Đơn `Nguyễn Minh Anh` (`Cena CloudRun Lite QA` Xanh Navy 40) đã thanh toán `PREPAID_DEMO` rồi hủy — Admin thấy nút **Duyệt hoàn tiền mô phỏng**.

### C. Nội dung & Khuyến mãi (chỉ Admin quản lý)
- **Nhà cung cấp:** có cả nhà cung cấp đang hoạt động và ngừng hoạt động (`QA-NCC-ACTIVE`, `QA-NCC-INACTIVE`).
- **Bài viết (`/admin/articles`, `/news`):** có bài `Đã đăng`, `Nháp` và `Đã ẩn`.
- **Banner (`/admin/banners`):** có banner đang bật và banner đang tắt (`[QA] Khuyến mãi Mùa Thu (Đã tắt)`).
- **Mã giảm giá (`/admin/coupons`):**
  - `CENAQA-FIXED`: giảm `100.000đ` cho đơn từ `500.000đ` (đang bật).
  - `CENAQA-FREESHIP`: miễn phí vận chuyển cho đơn từ `300.000đ` (đang bật).
  - `CENAQA-EXPIRED`: đã hết hạn.
  - `CENAQA-OFF`: đang tạm tắt.

---

## 3. Lưu ý về ảnh minh họa

- Ảnh sản phẩm lõi (`cena-demo-*`), sản phẩm QA (`Cena AeroStride Pro QA`, `Cena CloudRun Lite QA`), bài viết và banner đều dùng ảnh có sẵn trong repo (`public/legacy/img/`), không cần tải gói ngoài.
- Một số sản phẩm phân loại trong `ProductClassificationDemoSeeder` sử dụng URL ảnh ngoài (`images.unsplash.com`), cần kết nối internet để tải ảnh khi xem.
