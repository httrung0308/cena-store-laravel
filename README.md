# Cena Store — Laravel

Website bán giày viết bằng Laravel 13 / PHP 8.4, MySQL 8.4 và Blade / Tailwind / Alpine.

- **Cửa hàng:** Trang chủ (`/`), danh mục giày (`/shoes`), tin tức (`/news`), giỏ hàng, đặt hàng (`COD`, thanh toán trả trước mô phỏng, mã giảm giá), đơn hàng khách (`/account/orders`), thông báo và đánh giá theo đôi đã nhận.
- **Quản trị (`/admin`):**
  - **Admin** mặc định vào **Tổng quan** (`/admin`), quản lý sản phẩm, **Phiếu nhập kho** (chỉ Admin), người dùng, bài viết, banner, mã giảm giá, duyệt hoàn tiền và duyệt ghi hỏng hàng hoàn.
  - **Staff** mặc định vào **Công việc xử lý** (`/admin/operations-work-items`), xử lý đơn hàng, xem báo cáo vận hành/tồn kho và tiếp nhận/kiểm tra hàng hoàn (bị chặn Tổng quan và Phiếu nhập kho).

---

## 1. Chuẩn bị, lấy code và cài đặt lần đầu (Windows)

**Yêu cầu trên máy:** Git, PowerShell 7 (`pwsh`), Microsoft VC++ runtime x64, MySQL 8.4 và Mailpit. Script cài đặt sẽ tự tải PHP, Composer và Node đúng phiên bản cho project.

1. **Lấy code và vào thư mục project:**

```powershell
git clone https://github.com/httrung0308/cena-store-laravel.git
cd cena-store-laravel
```

2. **Cài đặt và khởi chạy bằng một lệnh:**

```powershell
.\scripts\setup-project.ps1
```

Nếu MySQL hoặc Mailpit không nằm ở đường dẫn Laragon mặc định (`C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin` và `C:\laragon\bin\mailpit\1.22.3\mailpit.exe`), chạy lệnh dưới với đường dẫn trên máy bạn:

```powershell
.\scripts\setup-project.ps1 -MySqlBin 'D:\tools\mysql84\bin' -MailpitBinary 'D:\tools\mailpit.exe'
```

Script tự tạo `.env` (nếu chưa có), cấu hình MySQL riêng ở `127.0.0.1:3307` (database mặc định `cena_store`, user `cena_app`), cài thư viện, migrate, seed dữ liệu nền và mở web server.

---

## 2. Tạo dữ liệu mẫu bằng PHP Artisan

Seeder `FullDemoSeeder` (chỉ chạy trên môi trường `local` / `testing`, bị chặn trên `production`) tự động tạo đủ vai trò, địa danh, tài khoản mẫu, sản phẩm (còn hàng, sắp hết, hết hàng), phiếu nhập nháp/đã ghi sổ, đơn hàng mọi trạng thái, hàng hoàn, đánh giá công khai/ẩn, bài viết, banner, mã giảm giá và số liệu báo cáo nhiều kỳ qua service nghiệp vụ.

- **Trường hợp 1 — Database đã migrate (chưa có dữ liệu mẫu):**

```powershell
.\scripts\php.ps1 artisan db:seed --class=FullDemoSeeder --no-interaction
```

- **Trường hợp 2 — Xóa toàn bộ dữ liệu cũ để tạo lại từ đầu** *(Cảnh báo: `migrate:fresh` sẽ xóa sạch toàn bộ bảng và dữ liệu hiện có trong database đang cấu hình ở `.env`)*:

```powershell
.\scripts\php.ps1 artisan migrate:fresh --no-interaction
.\scripts\php.ps1 artisan db:seed --class=FullDemoSeeder --no-interaction
```

- **Tạo liên kết ảnh và xóa cache** (chạy sau khi seed):

```powershell
.\scripts\php.ps1 artisan storage:link
.\scripts\php.ps1 artisan optimize:clear
```

> **Lưu ý về ảnh:** Không cần tải gói ảnh rời. Ảnh sản phẩm lõi (`cena-demo-*`), sản phẩm QA, bài viết và banner đều dùng ảnh có sẵn trong repo (`public/legacy/img/`). Một số sản phẩm minh họa trong `ProductClassificationDemoSeeder` vẫn dùng URL ảnh ngoài (`images.unsplash.com`), cần kết nối internet để hiển thị ảnh các sản phẩm đó.

### Tài khoản đăng nhập mẫu

Mật khẩu mặc định là `CenaDemo@2026` (hoặc giá trị `STORE_DEMO_PASSWORD` dài **12 đến 72 bytes** nếu bạn đã cấu hình trong `.env` trước khi seed):

| Vai trò | Email |
|---|---|
| Admin | `admin.qa@demo.cena.test` |
| Staff | `staff.qa@demo.cena.test` |
| Khách hàng 1 | `khach1.qa@demo.cena.test` |
| Khách hàng 2 | `khach2.qa@demo.cena.test` |

*(Ngoài ra có thêm bộ tài khoản nền `admin@demo.cena.test`, `staff@demo.cena.test`, `customer@demo.cena.test` cùng mật khẩu trên.)* Xem hướng dẫn tìm nhanh từng tình huống kiểm thử tại [TEAM_DEMO_TESTING_GUIDE.md](docs/TEAM_DEMO_TESTING_GUIDE.md).

---

## 3. Mở web

Chạy server (nếu chưa chạy):

```powershell
.\scripts\start-local.ps1
```

Mở trình duyệt:
- Cửa hàng: [http://127.0.0.1:8000](http://127.0.0.1:8000)
- Quản trị: [http://127.0.0.1:8000/admin](http://127.0.0.1:8000/admin)
- Mail local (Mailpit): [http://127.0.0.1:8025](http://127.0.0.1:8025)

---

## 4. Mở lại và cập nhật code

Mỗi lần mở lại máy để chạy web:

```powershell
.\scripts\start-local-services.ps1
.\scripts\start-local.ps1
```

Sau khi `git pull`, chỉ chạy lệnh tương ứng với phần có thay đổi:
- Nếu `composer.lock` thay đổi: `.\scripts\composer.ps1 install`
- Nếu `package-lock.json` thay đổi: `.\scripts\npm.ps1 ci`
- Nếu giao diện (Blade/CSS/JS) thay đổi: `.\scripts\npm.ps1 run build`
- Nếu có file migration mới: `.\scripts\php.ps1 artisan migrate --no-interaction`

---

## 5. Lỗi thường gặp

| Hiện tượng | Cách xử lý |
|---|---|
| Thiếu MySQL / Mailpit | Kiểm tra đường dẫn `-MySqlBin` và `-MailpitBinary` rồi chạy lại `.\scripts\setup-project.ps1`. |
| PowerShell báo sai phiên bản | Mở PowerShell 7 (`pwsh`), không dùng Windows PowerShell 5.1. |
| Port `8000` đang bận | Chạy server ở port khác: `.\scripts\php.ps1 artisan serve --host=127.0.0.1 --port=8001`. |
| Không kết nối được DB (`3307`) | Chạy `.\scripts\start-local-services.ps1` và kiểm tra thông số kết nối trong `.env`. |
| Giao diện cũ / thiếu Vite manifest | Chạy `.\scripts\npm.ps1 run build` rồi tải lại trang. |
| Ảnh không hiển thị sau khi seed | Chạy `.\scripts\php.ps1 artisan storage:link` để liên kết thư mục `public/storage`. |

---

## 6. Tài liệu khác

- **Quy trình nhánh, PR, CI và chạy test:** [CONTRIBUTING.md](CONTRIBUTING.md)
- **Hướng dẫn kiểm thử dữ liệu mẫu:** [TEAM_DEMO_TESTING_GUIDE.md](docs/TEAM_DEMO_TESTING_GUIDE.md)
- **Tài liệu kỹ thuật:** [Schema DB](docs/database/schema.md) · [Luồng nghiệp vụ & ERD](docs/NGHIEP_VU_ERD_CHUC_NANG.md) · [Quyết định kiến trúc](docs/architecture/decisions.md)
