# Cộng tác Cena Store

Thành viên chỉ cần mở web chạy thử hoặc xem dữ liệu mẫu hãy xem [README.md](README.md) và [TEAM_DEMO_TESTING_GUIDE.md](docs/TEAM_DEMO_TESTING_GUIDE.md). Tài liệu này dành cho việc phát triển code, chạy kiểm thử và gửi PR.

---

## 1. Quy trình nhánh, Commit và PR

Thành viên đã có quyền ghi repo clone trực tiếp; chưa có quyền thì fork trước, clone fork và mở PR về `httrung0308/cena-store-laravel:main`.

1. **Tạo nhánh từ `main` mới nhất:**

```powershell
git switch main
git pull --ff-only
git switch -c feat/ten-task
```

2. **Đọc phạm vi & quy tắc liên quan:**
   - Đọc `AGENTS.md` và phần đặc tả liên quan.
   - Với đợt 7 chức năng, xem [Kế hoạch 7 chức năng](docs/collaboration/PLAN_7_CHUC_NANG.md); với EXT, xem [Task và allowlist](docs/collaboration/EXTERNAL_TASKS_AND_PROMPTS.txt).

3. **Kiểm tra phần đã thay đổi trước khi push:**

```powershell
# Chỉ lần đầu cần dựng schema test riêng (cena_store_test):
.\scripts\php.ps1 artisan migrate --env=testing --no-interaction

# Chạy file test liên quan (thay bằng file test của task bạn làm):
.\scripts\php.ps1 artisan test --compact tests/Feature/OrderWorkflowTest.php

# Format PHP bằng Pint (bắt buộc trước khi push để CI không fail bước git diff):
.\scripts\php.ps1 vendor/bin/pint --dirty --format agent

# Nếu có sửa giao diện (Blade/CSS/JS):
.\scripts\npm.ps1 run build
```

4. **Commit đúng file, push và tạo PR:**

```powershell
git status
git add app/Services/TenService.php tests/Feature/TenTest.php
git commit -m "feat: mo ta thay doi"
git push -u origin HEAD
```

Mở PR vào `main` trên GitHub, mô tả ngắn: thay đổi gì, đã kiểm tra gì, còn thiếu gì (EXT ghi thêm task ID và `BASE_SHA`).

5. **Chờ CI xanh và review trước khi Squash and merge:**
   - CI GitHub tự chạy `composer validate/install`, `npm ci && npm run build`, `migrate`, toàn bộ suite PHPUnit trên MySQL 8.4 và `pint --format agent` + `git diff --exit-code`.
   - **Không merge khi CI fail hoặc pending.** Nếu cập nhật theo `main` hoặc xử lý xung đột, phải chờ CI pass lại trên commit mới nhất.
   - Repo chỉ bật **Squash and merge** (không dùng merge commit/rebase trực tiếp); không push thẳng lên `main`.

```powershell
gh pr checks 123 --repo httrung0308/cena-store-laravel --watch
gh pr merge 123 --repo httrung0308/cena-store-laravel --squash
```

---

## 2. Quy tắc kỹ thuật & cô lập môi trường

- **Phiên bản runtime:** PHP 8.4, Composer 2.9.4, Node 22.x (>= 22.12), MySQL 8.4 (khóa tại [docs/runtime-versions.json](docs/runtime-versions.json) và lockfiles).
- **Database test cô lập:** Test bắt buộc chạy trên MySQL 8.4 riêng (`cena_store_test` hoặc schema test đã được guard cho phép), **không** dùng DB phát triển, SQLite, root hoặc app user. Không chạy hai suite PHPUnit đồng thời trên cùng một schema test.
- **Không dùng `migrate:fresh` trên DB phát triển.** Không chạy `scripts/configure-env.php --ci` trên máy phát triển dùng chung (chỉ dành cho MySQL disposable của CI).
- **Không commit file nhạy cảm / tạo sinh:** `.env`, `.env.testing`, mật khẩu/token, `storage/` uploads, `vendor/`, `node_modules/`, `public/build/`, `.ai/` hoặc archive lồng.
- **Giữ UI Cena & source cũ:** Tái sử dụng Blade / Tailwind 3 / Alpine 3; không chỉnh sửa source Java/Next cũ và không thêm dependency ngoài phạm vi khi chưa trao đổi.
- **Nhật ký `docs/ai-progress.md`:** Chỉ thêm mục mới ở cuối nếu task có yêu cầu cập nhật; giữ nguyên mục của người khác khi có xung đột.

---

## 3. Nếu chưa có quyền hoặc tool

- Không có quyền ghi: fork và mở PR, hoặc nhờ chủ repo cấp collaborator.
- GitHub CLI (`gh`) là tùy chọn; mọi thao tác tạo PR, xem Checks và Squash and merge đều có thể làm trực tiếp trên giao diện web GitHub.
