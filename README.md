# Cena Store — Laravel

Website bán giày viết bằng Laravel 13 / PHP 8.4, MySQL 8.4 và Blade / Tailwind / Alpine.

- **Cửa hàng:** Trang chủ (`/`), danh mục giày (`/shoes`), tin tức (`/news`), giỏ hàng, đặt hàng (`COD`, thanh toán trả trước mô phỏng, mã giảm giá), đơn hàng khách (`/account/orders`), thông báo và đánh giá theo đôi đã nhận.
- **Quản trị (`/admin`):**
  - **Admin** mặc định vào **Tổng quan** (`/admin`), quản lý sản phẩm, **Phiếu nhập kho** (chỉ Admin), người dùng, bài viết, banner, mã giảm giá, duyệt hoàn tiền và duyệt ghi hỏng hàng hoàn.
  - **Staff** mặc định vào **Công việc xử lý** (`/admin/operations-work-items`), xử lý đơn hàng, xem báo cáo vận hành/tồn kho và tiếp nhận/kiểm tra hàng hoàn (bị chặn Tổng quan và Phiếu nhập kho).

---

## 1. Chuẩn bị, lấy code và cài đặt lần đầu (Windows)

**Yêu cầu trên máy:** Git, PowerShell 7 (`pwsh`), Microsoft VC++ runtime x64, MySQL 8.4 và Mailpit. Script cài đặt sẽ tự tải PHP, Composer và Node đúng phiên bản cho project.

1. **Lấy code và chuyển sang nhánh tương thích (`feat/cena-redesign`):**

```powershell
git clone https://github.com/httrung0308/cena-store-laravel.git
cd cena-store-laravel
git switch feat/cena-redesign
```

> Lưu ý: Các thay đổi giao diện mới và gói dữ liệu mẫu `demo-20261118` hiện nằm trên nhánh `feat/cena-redesign` (PR #36, đang chờ merge vào `main`).

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

## 2. Chọn dữ liệu mẫu

Chọn **một trong hai cách** dưới đây để có dữ liệu thử nghiệm. **Không chạy seeder của Cách 1 đè lên database đã import SQL ở Cách 2** để tránh trùng dữ liệu.

### Cách 1 — Cơ bản (có sẵn trong repo, không cần tải gói riêng)

Phù hợp khi chỉ cần tài khoản và vài sản phẩm mẫu tối thiểu. Thêm `STORE_DEMO_USERS=true` và `STORE_DEMO_PASSWORD` (mật khẩu riêng dài từ **12 đến 72 bytes**) vào file `.env` local trên máy bạn, rồi chạy:

```powershell
.\scripts\php.ps1 artisan config:clear
.\scripts\php.ps1 artisan db:seed --class=CoreFoundationSeeder --no-interaction
.\scripts\php.ps1 artisan db:seed --class=StockImportDemoSeeder --no-interaction
```

**Tài khoản tạo bởi seeder (mật khẩu là giá trị `STORE_DEMO_PASSWORD` bạn vừa đặt):**
- Admin: `admin@demo.cena.test`
- Staff: `staff@demo.cena.test`
- Customer: `customer@demo.cena.test`

### Cách 2 — Đầy đủ (tải gói dữ liệu mẫu từ GitHub Release)

Phù hợp khi muốn thử ngay đầy đủ đơn hàng các trạng thái, hàng hoàn, đánh giá, bài viết, banner, mã giảm giá và biểu đồ báo cáo.

- **Trang Release:** [demo-20261118](https://github.com/httrung0308/cena-store-laravel/releases/tag/demo-20261118)
- **Tải trực tiếp (11.65 MB):** [cena-store-demo-20261118.zip](https://github.com/httrung0308/cena-store-laravel/releases/download/demo-20261118/cena-store-demo-20261118.zip)

> **Lưu ý an toàn:** Nếu bạn đã có dữ liệu riêng trong `cena_store` cần giữ lại, hãy sao lưu trước và import vào một **database mới** (xem hướng dẫn thu gọn bên dưới).

1. Giải nén `cena-store-demo-20261118.zip`.
2. **Chỉ dừng web server** (`Ctrl + C` ở cửa sổ đang chạy web), **giữ MySQL (`127.0.0.1:3307`) đang chạy** (nếu MySQL chưa chạy, mở bằng `.\scripts\start-local-services.ps1`).
3. **Import file SQL** vào database mặc định `cena_store` (lấy `DB_PASSWORD` trong file `.env` trên máy bạn và nhập khi được hỏi):

```powershell
Get-Content -Raw -Encoding utf8 'D:\duong-dan-toi\cena-store-demo-20261118.sql' | & 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe' -h 127.0.0.1 -P 3307 -u cena_app -p cena_store
```

<details>
<summary>Tùy chọn: Tạo database mới riêng (nếu không muốn ghi đè dữ liệu trong cena_store)</summary>

Dùng tài khoản `root` của MySQL `127.0.0.1:3307` (mật khẩu `admin_password` nằm trong `%LOCALAPPDATA%\CenaStore\services\local-credentials.json` trên máy bạn) để tạo database mới và cấp quyền cho `'cena_app'@'127.0.0.1'`:

```sql
CREATE DATABASE IF NOT EXISTS `cena_store_demo` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES ON `cena_store_demo`.* TO 'cena_app'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Sau đó đổi `DB_DATABASE=cena_store_demo` trong `.env` trên máy bạn và thay `cena_store` ở lệnh import phía trên thành `cena_store_demo`.

</details>

4. **Chép ảnh và file đính kèm:** chép nội dung `storage\app\public\` và `storage\app\private\` từ gói vừa giải nén vào đúng `storage\app\public\` và `storage\app\private\` trong thư mục project (giữ tách riêng `public` và `private`).
5. **Tạo liên kết ảnh, xóa cache và build giao diện (nếu cần):**

```powershell
.\scripts\php.ps1 artisan storage:link
.\scripts\php.ps1 artisan optimize:clear
.\scripts\npm.ps1 run build
```

**Tài khoản mẫu của gói dữ liệu đầy đủ (chỉ dùng cho môi trường local, mật khẩu chung `CenaDemo@2026`):**

| Vai trò | Email |
|---|---|
| Admin | `admin.qa@demo.cena.test` |
| Staff | `staff.qa@demo.cena.test` |
| Khách hàng 1 | `khach1.qa@demo.cena.test` |
| Khách hàng 2 | `khach2.qa@demo.cena.test` |

Xem hướng dẫn tìm từng tình huống kiểm thử tại [TEAM_DEMO_TESTING_GUIDE.md](docs/TEAM_DEMO_TESTING_GUIDE.md). Hãy giữ bản gốc `.sql` và `storage/` để import lại khi cần khôi phục dữ liệu ban đầu.

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
