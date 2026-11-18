# Cena Store — Laravel

Website bán giày viết bằng Laravel 13 / PHP 8.4, MySQL 8.4 và Blade / Tailwind / Alpine, giữ giao diện Cena gốc.

- **Cửa hàng:** Trang chủ (`/`), danh mục giày (`/shoes`), tin tức (`/news`), giỏ hàng, đặt hàng (`COD`, `PREPAID_DEMO`, mã giảm giá), đơn hàng khách (`/account/orders`), thông báo và đánh giá theo đôi đã nhận.
- **Quản trị (`/admin`):**
  - **Admin** mặc định vào **Tổng quan** (`/admin`), quản lý sản phẩm, **Phiếu nhập kho** (chỉ Admin), người dùng, bài viết, banner, mã giảm giá, duyệt hoàn tiền và duyệt ghi hỏng hàng hoàn.
  - **Staff** mặc định vào **Công việc xử lý** (`/admin/operations-work-items`), xử lý đơn hàng, xem báo cáo vận hành/tồn kho và tiếp nhận/kiểm tra hàng hoàn (bị chặn Tổng quan và Phiếu nhập kho).

---

## 1. Cài lần đầu trên Windows

**Chuẩn bị:** Git, PowerShell 7 (`pwsh`), Microsoft VC++ runtime x64, MySQL 8.4 và Mailpit. Script sẽ tự tải PHP, Composer, Node đúng phiên bản (không cần tự thêm vào PATH).

1. **Lấy code:**

```powershell
git clone https://github.com/httrung0308/cena-store-laravel.git
cd cena-store-laravel
```

2. **Cài đặt và khởi chạy bằng một lệnh:**

```powershell
.\scripts\setup-project.ps1
```

Nếu MySQL hoặc Mailpit không nằm ở đường dẫn Laragon mặc định, chạy lệnh dưới và sửa đường dẫn cho đúng máy bạn:

```powershell
.\scripts\setup-project.ps1 -MySqlBin 'D:\tools\mysql84\bin' -MailpitBinary 'D:\tools\mailpit.exe'
```

3. **Mở trình duyệt:**
   - Web: [http://127.0.0.1:8000](http://127.0.0.1:8000)
   - Quản trị: [http://127.0.0.1:8000/admin](http://127.0.0.1:8000/admin)
   - Mail local (Mailpit): [http://127.0.0.1:8025](http://127.0.0.1:8025)

---

## 2. Dùng dữ liệu mẫu của nhóm (khuyên dùng)

Dùng gói bàn giao **`cena-store-demo-20261118`** (gồm file `.sql` và thư mục `storage/`) để có sẵn sản phẩm, đơn hàng đủ trạng thái, hàng hoàn, đánh giá, bài viết, banner, mã giảm giá và số liệu báo cáo.

> **Lấy gói ở đâu:** Nhận thư mục `cena-store-demo-20261118` từ kênh chia sẻ nội bộ của nhóm (Google Drive / Discord / máy bàn giao).

### Các bước import

1. Hoàn tất bước **Cài lần đầu** ở trên (hoặc chạy `.\scripts\start-local-services.ps1`), rồi **dừng web server** (`Ctrl + C`) nếu đang chạy.
2. Nếu bạn đã có dữ liệu riêng trong DB local, hãy sao lưu trước và tạo một database mới riêng cho bản mẫu (cập nhật `DB_DATABASE` trong `.env` trên máy bạn; không dùng `.env` của người khác).
3. Import file `cena-store-demo-20261118.sql` vào database local của bạn. **Không chạy thêm seeder demo** lên database vừa import để tránh trùng dữ liệu.
4. Chép hai thư mục `storage/app/public/` và `storage/app/private/` từ gói vào đúng `storage/app/` trong project (giữ tách riêng `public` và `private`).
5. Tạo liên kết ảnh, xóa cache và mở lại server:

```powershell
.\scripts\php.ps1 artisan storage:link
.\scripts\php.ps1 artisan optimize:clear
.\scripts\npm.ps1 run build
.\scripts\start-local.ps1
```

### Tài khoản đăng nhập nhanh

Mật khẩu chung trong gói bàn giao local: `CenaDemo@2026`

| Vai trò | Email |
|---|---|
| Admin | `admin.qa@demo.cena.test` |
| Staff | `staff.qa@demo.cena.test` |
| Khách hàng 1 | `khach1.qa@demo.cena.test` |
| Khách hàng 2 | `khach2.qa@demo.cena.test` |

Xem chi tiết cách tìm từng tình huống kiểm thử (đơn hàng, hàng hoàn, đánh giá, tồn kho, coupon) tại [Hướng dẫn kiểm thử dữ liệu mẫu](docs/TEAM_DEMO_TESTING_GUIDE.md). Lưu ý giữ nguyên gói `.sql` và `storage/` gốc để import lại khi cần khôi phục dữ liệu ban đầu.

<details>
<summary>Tùy chọn phụ: Tự tạo dữ liệu tối thiểu bằng seeder (không cần gói bàn giao)</summary>

Nếu chưa có gói bàn giao, thêm `STORE_DEMO_USERS=true` và `STORE_DEMO_PASSWORD` (12–72 ký tự tự chọn) vào `.env` local rồi chạy:

```powershell
.\scripts\php.ps1 artisan config:clear
.\scripts\php.ps1 artisan db:seed --class=CoreFoundationSeeder --no-interaction
.\scripts\php.ps1 artisan db:seed --class=StockImportDemoSeeder --no-interaction
```

Tài khoản tạo ra gồm `admin@demo.cena.test`, `staff@demo.cena.test`, `customer@demo.cena.test` với mật khẩu bạn vừa đặt.

</details>

---

## 3. Mở lại web và cập nhật code

Mỗi lần mở lại máy để chạy web:

```powershell
.\scripts\start-local-services.ps1
.\scripts\start-local.ps1
```

Sau khi `git pull` có thay đổi mới:

```powershell
.\scripts\composer.ps1 install
.\scripts\npm.ps1 ci
.\scripts\npm.ps1 run build
.\scripts\php.ps1 artisan migrate --no-interaction
```

---

## 4. Lỗi thường gặp

| Hiện tượng | Cách xử lý |
|---|---|
| Thiếu MySQL / Mailpit | Kiểm tra đường dẫn ở bước cài lần đầu rồi chạy lại `setup-project.ps1 -MySqlBin ... -MailpitBinary ...`. |
| PowerShell báo sai phiên bản | Mở PowerShell 7 (`pwsh`), không dùng Windows PowerShell 5.1. |
| Port `8000` đang bận | Chạy server ở port khác: `.\scripts\php.ps1 artisan serve --host=127.0.0.1 --port=8001`. |
| Không kết nối được DB (`3307`) | Chạy lại `.\scripts\start-local-services.ps1` và kiểm tra thông số kết nối trong `.env`. |
| Giao diện cũ / thiếu Vite manifest | Chạy `.\scripts\npm.ps1 run build` rồi tải lại trang. |
| Ảnh không hiển thị sau khi chép gói mẫu | Kiểm tra lại thư mục `storage/app/public/` và chạy `.\scripts\php.ps1 artisan storage:link`. |

---

## 5. Tài liệu dành cho thành viên lập trình

- **Quy trình nhánh, PR, CI, chạy test & Pint:** [CONTRIBUTING.md](CONTRIBUTING.md)
- **Tình huống kiểm thử & tài khoản mẫu:** [TEAM_DEMO_TESTING_GUIDE.md](docs/TEAM_DEMO_TESTING_GUIDE.md)
- **Tài liệu kỹ thuật & kiến trúc:** [Schema DB](docs/database/schema.md) · [Luồng nghiệp vụ & ERD](docs/NGHIEP_VU_ERD_CHUC_NANG.md) · [Quyết định kiến trúc](docs/architecture/decisions.md) · [Đặc tả](docs/specification.md)
- **Chỉ dẫn riêng cho AI/Agent:** [AGENTS.md](AGENTS.md)

UI/asset gốc tuân theo [LICENSE-legacy-ui](LICENSE-legacy-ui), giữ attribution `cena261/nike.com`. Không chỉnh sửa source Java/Next cũ khi làm project Laravel này.
