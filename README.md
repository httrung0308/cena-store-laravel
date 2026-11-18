# Cena Store — Laravel

Website bán giày viết bằng Laravel 13 / PHP 8.4, MySQL 8.4 và Blade / Tailwind / Alpine.

- **Cửa hàng:** Trang chủ (`/`), danh mục giày (`/shoes`), tin tức (`/news`), giỏ hàng, đặt hàng (`COD`, thanh toán trả trước mô phỏng, mã giảm giá), đơn hàng khách (`/account/orders`), thông báo và đánh giá theo đôi đã nhận.
- **Quản trị (`/admin`):**
  - **Admin** mặc định vào **Tổng quan** (`/admin`), quản lý sản phẩm, **Phiếu nhập kho** (chỉ Admin), người dùng, bài viết, banner, mã giảm giá, duyệt hoàn tiền và duyệt ghi hỏng hàng hoàn.
  - **Staff** mặc định vào **Công việc xử lý** (`/admin/operations-work-items`), xử lý đơn hàng, xem báo cáo vận hành/tồn kho và tiếp nhận/kiểm tra hàng hoàn (bị chặn Tổng quan và Phiếu nhập kho).

---

## 1. Chuẩn bị và cài lần đầu trên Windows

**Yêu cầu trên máy:** Git, PowerShell 7 (`pwsh`), Microsoft VC++ runtime x64, MySQL 8.4 và Mailpit. Script cài đặt sẽ tự tải PHP, Composer và Node đúng phiên bản cho project.

1. **Lấy code và vào thư mục project:**

```powershell
git clone https://github.com/httrung0308/cena-store-laravel.git
cd cena-store-laravel
```

2. **Chạy script cài đặt lần đầu:**

```powershell
.\scripts\setup-project.ps1
```

Nếu MySQL hoặc Mailpit không nằm ở đường dẫn Laragon mặc định (`C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin` và `C:\laragon\bin\mailpit\1.22.3\mailpit.exe`), chạy lệnh dưới với đường dẫn thực tế trên máy bạn:

```powershell
.\scripts\setup-project.ps1 -MySqlBin 'D:\tools\mysql84\bin' -MailpitBinary 'D:\tools\mailpit.exe'
```

Script tự tạo `.env` (nếu chưa có), cấu hình MySQL riêng ở `127.0.0.1:3307` (database mặc định `cena_store`, user `cena_app`), cài thư viện, migrate, seed dữ liệu nền và mở web server.

---

## 2. Dùng gói dữ liệu mẫu của nhóm (`cena-store-demo-20261118`)

> **Liên hệ trưởng nhóm để nhận gói `cena-store-demo-20261118`** (gồm file `cena-store-demo-20261118.sql` và thư mục `storage/`).

> **Lưu ý trước khi import:** Nếu bạn **đã có dữ liệu riêng cần giữ**, hãy sao lưu trước và import vào một **database mới** thay vì ghi đè `cena_store`.

### Các bước import

1. **Chỉ dừng web server** (`Ctrl + C` ở cửa sổ đang chạy web), **giữ MySQL (`127.0.0.1:3307`) đang chạy** (nếu MySQL chưa chạy, mở bằng `.\scripts\start-local-services.ps1`).
2. **Xác định database đích và quyền truy cập:**
   - Nếu dùng database mặc định `cena_store` (cài mới, chưa có dữ liệu cần giữ): user `cena_app` đã được cấp sẵn quyền; lấy `DB_USERNAME` và `DB_PASSWORD` trong file `.env` trên máy bạn.
   - Nếu tạo **database mới** (ví dụ `cena_store_demo`), dùng tài khoản `root` của MySQL `127.0.0.1:3307` (mật khẩu `admin_password` nằm trong `%LOCALAPPDATA%\CenaStore\services\local-credentials.json` trên máy bạn) để tạo DB và cấp quyền cho `cena_app`@`127.0.0.1`, rồi sửa `DB_DATABASE=cena_store_demo` trong `.env` của bạn:

```sql
CREATE DATABASE IF NOT EXISTS `cena_store_demo` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES ON `cena_store_demo`.* TO 'cena_app'@'127.0.0.1';
FLUSH PRIVILEGES;
```

3. **Import file SQL** (thay đường dẫn file `.sql`, đường dẫn `mysql.exe` và tên database cho đúng máy bạn; nhập `DB_PASSWORD` khi được hỏi):

```powershell
Get-Content -Raw -Encoding utf8 'D:\duong-dan-toi\cena-store-demo-20261118\cena-store-demo-20261118.sql' | & 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe' -h 127.0.0.1 -P 3307 -u cena_app -p cena_store
```

**Không chạy thêm seeder demo** lên database vừa import để tránh trùng dữ liệu.

4. **Chép ảnh và file đính kèm:** chép nội dung `storage\app\public\` và `storage\app\private\` từ gói mẫu vào đúng hai thư mục `storage\app\public\` và `storage\app\private\` trong project (giữ tách riêng `public` và `private`).
5. **Tạo liên kết ảnh, xóa cache và build giao diện (nếu cần):**

```powershell
.\scripts\php.ps1 artisan storage:link
.\scripts\php.ps1 artisan optimize:clear
.\scripts\npm.ps1 run build
```

### Tài khoản mẫu (chỉ dành cho gói dữ liệu local này)

Mật khẩu chung trong gói bàn giao local: `CenaDemo@2026`

| Vai trò | Email |
|---|---|
| Admin | `admin.qa@demo.cena.test` |
| Staff | `staff.qa@demo.cena.test` |
| Khách hàng 1 | `khach1.qa@demo.cena.test` |
| Khách hàng 2 | `khach2.qa@demo.cena.test` |

Xem hướng dẫn tìm nhanh từng tình huống kiểm thử tại [TEAM_DEMO_TESTING_GUIDE.md](docs/TEAM_DEMO_TESTING_GUIDE.md). Hãy giữ bản gốc `.sql` và `storage/` để import lại khi cần khôi phục dữ liệu ban đầu.

<details>
<summary>Tùy chọn phụ: Tự tạo dữ liệu tối thiểu bằng seeder (nếu không dùng gói bàn giao)</summary>

Thêm `STORE_DEMO_USERS=true` và `STORE_DEMO_PASSWORD` (mật khẩu riêng dài từ **12 đến 72 bytes**) vào `.env` local rồi chạy:

```powershell
.\scripts\php.ps1 artisan config:clear
.\scripts\php.ps1 artisan db:seed --class=CoreFoundationSeeder --no-interaction
.\scripts\php.ps1 artisan db:seed --class=StockImportDemoSeeder --no-interaction
```

</details>

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
| Lỗi quyền truy cập DB mới | Cấp quyền cho `'cena_app'@'127.0.0.1'` trên database mới theo mục 2. |
| Giao diện cũ / thiếu Vite manifest | Chạy `.\scripts\npm.ps1 run build` rồi tải lại trang. |
| Ảnh không hiển thị sau khi import | Kiểm tra thư mục `storage\app\public\` và chạy `.\scripts\php.ps1 artisan storage:link`. |

---

## 6. Tài liệu khác

- **Quy trình nhánh, PR, CI và chạy test:** [CONTRIBUTING.md](CONTRIBUTING.md)
- **Hướng dẫn kiểm thử dữ liệu mẫu:** [TEAM_DEMO_TESTING_GUIDE.md](docs/TEAM_DEMO_TESTING_GUIDE.md)
- **Tài liệu kỹ thuật:** [Schema DB](docs/database/schema.md) · [Luồng nghiệp vụ & ERD](docs/NGHIEP_VU_ERD_CHUC_NANG.md) · [Quyết định kiến trúc](docs/architecture/decisions.md)
