# Cena Store — Laravel

Website bán giày viết bằng Laravel13/PHP8.4, MySQL8.4 và Blade/Tailwind/Alpine, giữ giao diện Cena gốc. Source Java/Next cũ được giữ riêng, không cần chạy để mở bản Laravel.

## Chạy lần đầu trên Windows

**Chuẩn bị:** Git, PowerShell7 (`pwsh`), Microsoft VC++ runtime x64; có sẵn bộ MySQL8.4 và Mailpit. Script sẽ tải PHP/Composer/Node đúng phiên bản và kiểm checksum; không cần tự cài các bản đó vào PATH. MySQL/Mailpit cần cài sẵn, không được script tải tự động.

**1. Lấy code** — mở PowerShell7 ở thư mục bạn muốn lưu project:

```powershell
git clone https://github.com/httrung0308/cena-store-laravel.git
cd cena-store-laravel
```

**2. Cài và mở web bằng một lệnh:**

```powershell
.\scripts\setup-project.ps1
```

Nếu MySQL/Mailpit của bạn không nằm ở đường dẫn Laragon mặc định, dùng lệnh này **thay cho** lệnh trên và sửa hai đường dẫn cho đúng máy:

```powershell
.\scripts\setup-project.ps1 -MySqlBin 'D:\tools\mysql84\bin' -MailpitBinary 'D:\tools\mailpit.exe'
```

`MySqlBin` là thư mục có cả `mysql.exe` và `mysqld.exe`. Mặc định: `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin`; Mailpit mặc định: `C:\laragon\bin\mailpit\1.22.3\mailpit.exe`.

Script tự cài thư viện theo lockfile, mở dịch vụ local, tạo `.env`/`.env.testing` nếu chưa có, migrate database phát triển, seed vai trò và tỉnh/phường, liên kết ảnh rồi mở server. Lỗi ở bước nào thì dừng ở bước đó; sửa lỗi rồi chạy lại. Env và APP_KEY đã có được giữ nguyên. Script không tạo dữ liệu sản phẩm/tài khoản demo mặc định và không chạy test/reset database test.

**3. Mở trình duyệt:** [http://127.0.0.1:8000](http://127.0.0.1:8000).

- Đăng ký/đăng nhập: `/register`, `/login`; quản trị: `/admin` (cần Admin/Staff).
- Mail local: [Mailpit](http://127.0.0.1:8025); kiểm server: `/up`.
- Script dùng MySQL riêng tại `127.0.0.1:3307`, dữ liệu trong `%LOCALAPPDATA%/CenaStore`; không dùng datadir Laragon cũ.

## Những lần mở lại

Trong thư mục project, chỉ chạy:

```powershell
.\scripts\start-local-services.ps1
.\scripts\start-local.ps1
```

Mở lại http://127.0.0.1:8000. Không cần cài thư viện, seed hay chạy toàn bộ test mỗi lần mở web.

Sau khi lấy code mới: chạy `composer.ps1 install` nếu `composer.lock` đổi; `npm.ps1 ci` nếu `package-lock.json` đổi; `npm.ps1 run build` nếu giao diện đổi; `php.ps1 artisan migrate --no-interaction` nếu có migration mới. Các lệnh này nằm trong thư mục `scripts` như ví dụ bên dưới.

## Tài khoản và sản phẩm demo (tùy chọn)

Chỉ dùng trên máy local. Sửa hai biến trong `.env` (thêm nếu chưa có): `STORE_DEMO_USERS=true` và `STORE_DEMO_PASSWORD` là mật khẩu riêng bạn tự chọn, dài12–72bytes. Không gửi hoặc commit mật khẩu này.

```powershell
.\scripts\php.ps1 artisan config:clear
.\scripts\php.ps1 artisan db:seed --class=CoreFoundationSeeder --no-interaction
.\scripts\php.ps1 artisan db:seed --class=StockImportDemoSeeder --no-interaction
```

Đăng nhập bằng mật khẩu vừa đặt:

| Vai trò | Email |
|---|---|
| Admin | admin@demo.cena.test |
| Staff | staff@demo.cena.test |
| Customer | customer@demo.cena.test |

Seeder sản phẩm tạo phiếu nhập thực qua service, không gán tồn trực tiếp. Chạy lại không nhân bản phiếu; không đổi mật khẩu/quyền tài khoản đã bị thay đổi. Catalog `/shoes` thuộc X4 đang được tích hợp riêng; có dữ liệu demo không có nghĩa X4 đã merge.

## Gửi code cho team: một task, một PR

Thành viên **đã có quyền ghi repo** clone trực tiếp; chưa có quyền thì fork trước, clone fork và mở PR về repo này. Không bắt buộc fork với người đã là collaborator. GitHub CLI (`gh`) là tùy chọn; mở PR/merge bằng giao diện GitHub cũng được.

**1. Tạo nhánh từ main mới nhất** (chỉ switch khi thư mục đang sạch):

```powershell
git switch main
git pull --ff-only
git switch -c feat/ten-task
```

**2. Sửa đúng task và kiểm tra phần liên quan.** Đọc [CONTRIBUTING.md](CONTRIBUTING.md) và phạm vi task được giao. Thay đổi PHP thì chạy test liên quan và Pint; giao diện thì build và xem thử. Không cần chạy lại mọi bước setup hoặc tự chạy toàn bộ CI trên máy trước mỗi lần push.

Ví dụ kiểm thử PHP trên **schema test riêng của bạn** đã cấu hình, không phải DB development:

```powershell
# Chỉ lần đầu cần dựng schema test:
.\scripts\php.ps1 artisan migrate --env=testing --no-interaction
# Ví dụ test liên quan; thay bằng file test của task bạn làm:
.\scripts\php.ps1 artisan test --compact tests/Feature/SharedFoundationTest.php
.\scripts\php.ps1 vendor/bin/pint --dirty --format agent
# Nếu sửa giao diện:
.\scripts\npm.ps1 run build
```

Mỗi máy riêng dùng schema test local mặc định được setup tạo. Trên máy có nhiều người/agent cùng làm, dùng schema riêng đã được guard cho phép hoặc chạy test luân phiên; không chạy hai suite cùng schema. Cấu hình PHPUnit Codex riêng ở ngoài Git phải truyền `--configuration=...` theo cấu hình đã giao, vì `phpunit.xml` mặc định ép `cena_store_test`. Không chạy lệnh test mặc định phía trên nếu worktree của bạn dùng cấu hình Codex đó.

**3. Commit, push và mở PR:**

```powershell
git status
# Chỉ add file của task, ví dụ:
git add app/Services/TenService.php tests/Feature/TenTest.php
git commit -m "feat: mo ta thay doi"
git push -u origin HEAD
```

Ví dụ tên file phía trên cần thay bằng file thật. Trên GitHub bấm **Compare & pull request**, base=`main` của `httrung0308/cena-store-laravel`. PR chỉ cần: làm gì, đã kiểm tra gì, còn thiếu gì. Với EXT task ghi thêm mã task và base SHA; không cần báo cáo nghiệm thu riêng.

**4. Chờ CI xanh và người có quyền review/merge.** CI GitHub tự chạy dependency checks, build, migrate, toàn bộ tests MySQL và Pint. CI đỏ thì sửa rồi push lại; **không merge khi có check fail hoặc pending**. Nếu phải cập nhật nhánh theo main/giải quyết xung đột, chờ CI xanh lại trên commit mới. Chọn **Squash and merge**, sau đó có thể xóa nhánh.

CLI tương đương cho người merge (thay `123` bằng số PR thật, cần đăng nhập `gh`):

```powershell
gh pr checks 123 --repo httrung0308/cena-store-laravel --watch
# Chỉ chạy sau khi tất cả check bắt buộc đã PASS và review xong:
gh pr merge 123 --repo httrung0308/cena-store-laravel --squash --delete-branch
```

Không push trực tiếp vào main. Worktree chỉ cần khi cùng máy làm nhiều task song song; không bắt người chỉ làm một task phải tạo nhiều worktree.

## Gặp lỗi thì xem đây

| Hiện tượng | Cách xử lý |
|---|---|
| Thiếu MySQL/Mailpit | Kiểm hai đường dẫn ở bước cài lần đầu, rồi chạy lại setup với tham số đúng. |
| PowerShell báo cần phiên bản7 | Mở `pwsh`/PowerShell7, không dùng Windows PowerShell5.1. |
| PHP không khởi động, thiếu DLL | Kiểm Microsoft VC++ runtime x64. |
| Port8000 bị chiếm | Dừng đúng server của bạn hoặc dùng `.\scripts\php.ps1 artisan serve --host=127.0.0.1 --port=8001`; mở URL port8001. |
| Port3307/1025/8025 bị chiếm | Kiểm tiến trình đang dùng; script dừng nếu đó không phải dịch vụ Cena tương ứng. Không tắt nhầm dịch vụ người khác. |
| Access denied/connection refused | Chạy `start-local-services.ps1`, kiểm cấu hình `.env`. `configure-env.php --local` không ghi đè env cũ; không xóa DB/credential để chữa lỗi. |
| Thiếu Vite manifest hoặc giao diện cũ | Chạy `.\scripts\npm.ps1 run build`, tải lại trang. |
| Test từ chối DB/config cache | Chạy `.\scripts\php.ps1 artisan config:clear`, kiểm `.env.testing` và đúng PHPUnit config/schema/user. Không bỏ guard để chạy. |

## Thông tin kỹ thuật khi cần

- PHP8.4 (extensions theo `composer.json`), Composer2.9.4, Node22.x>=22.12, MySQL8.4; phiên bản khóa tại [runtime-versions.json](docs/runtime-versions.json) và lockfiles.
- Wrapper `scripts/php.ps1`, `composer.ps1`, `npm.ps1` nhận tham số như `php`, `composer`, `npm`. Nếu đã có runtime đúng trên PATH, có thể dùng lệnh gốc với database/env riêng đã cấu hình tương đương.
- `.env`/`.env.testing`, mật khẩu local, uploads, vendor/node_modules và build không đưa lên Git. Không chia sẻ file credential của máy tác giả.
- Không dùng `migrate:fresh` trên database phát triển; test có thể reset schema test sau guard. Không dùng SQLite, npm `--force` hoặc tắt kiểm tra để vượt lỗi.
- MySQL app/test dùng user khác nhau, test không truy cập DB phát triển. Không chạy mode `configure-env.php --ci` trên server đang dùng chung: mode này chỉ cho MySQL disposable của CI.
- `public/storage` chỉ nối disk public; chứng từ private không đặt vào đó. Demo không dùng trên staging/production.

## Tiến độ và tài liệu

Đợt7 chức năng trên main đã có đăng ký/đăng nhập, tỉnh/phường, sổ địa chỉ, backend/drawer giỏ, admin danh mục/NCC/sản phẩm và phiếu nhập. X4 catalog/chi tiết đang làm riêng; checkout/thanh toán và các phase sau chưa hoàn tất. Không dùng các ghi chú P00/P01 cũ làm trạng thái tính năng hiện tại.

- [Kế hoạch7 chức năng và phân chia phạm vi](docs/collaboration/PLAN_7_CHUC_NANG.md)
- [Nhật ký mới nhất](docs/ai-progress.md) · [GitHub Actions](https://github.com/httrung0308/cena-store-laravel/actions)
- [Luồng nghiệp vụ và ERD](docs/NGHIEP_VU_ERD_CHUC_NANG.md) · [Schema](docs/database/schema.md)
- [Đặc tả đầy đủ](docs/specification.md) · [Quyết định kiến trúc](docs/architecture/decisions.md)
- [Task EXT và prompt](docs/collaboration/EXTERNAL_TASKS_AND_PROMPTS.txt) — chỉ đọc task được giao, không cần đọc toàn bộ để mở web.

UI/asset gốc theo [LICENSE-legacy-ui](LICENSE-legacy-ui), giữ attribution cena261/nike.com; thư viện giữ giấy phép tương ứng. Không chỉnh source Java/Next cũ khi làm project Laravel này.
