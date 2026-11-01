# EXT-047 — Cài sạch Linux / Codex Cloud cho Cena Store (PREP)

**Mốc kiểm chứng:** upstream `main` SHA `d2771892dddc6aa20f8fce29dc11df59b3f566a7`, 31/10/2026. Không sửa CI, dependency, bootstrap hay runtime. Tham chiếu: `README.md`, `.github/workflows/ci.yml`, `docs/runtime-versions.json`, `composer.lock`, `package-lock.json` và tài liệu Codex Cloud chính thức: https://help.openai.com/en/articles/20001545-using-codex-cloud .

## Phiên bản và môi trường

- PHP **8.4** (runtime P00 được xác nhận `8.4.27`), Composer **2.9.4**; PHP extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `xmlwriter`, `zip`.
- Node **22.23.4**, npm **10.9.9** (`package.json` yêu cầu Node >=22.12 <23, npm >=10 <11), `package-lock.json` lockfile v3. MySQL **8.4** thật (workflow service `mysql:8.4`; runtime local `8.4.3`). Laravel 13 / Tailwind 3.4 / Alpine 3 / Chart.js 4 / DomPDF theo lockfiles. Không dùng SQLite, PHP thấp hơn yêu cầu, `npm --force`, `--ignore-platform-reqs`.
- GitHub Actions `.github/workflows/ci.yml` pin `setup-php@v2` PHP 8.4 và `setup-node@v4` Node 22.23.4; dùng Composer2.9.4 và MySQL8.4 disposable. Lockfile SHA-256 tại ngày freeze: `composer.lock` `020f91ba909ab06e84e1272f9592d0463a56616811b1f012ab8bb39db7d36c7d`; `package-lock.json` `8aa7a27ab243f899b09af6abc309d1dc8ca62d207ff4d3e8f520d4b557feb8ba`. Phải kiểm lại nếu `main` thay đổi.

## Phân quyền và hai schema

Dùng **một MySQL8.4 mới/riêng** trong container hoặc service cloud, không tái sử dụng server chứa dữ liệu thật/nhóm. Có hai schema: `cena_store` cho ứng dụng (`cena_app`) và `cena_store_test` cho test (`cena_ci` trong CI; `cena_test` khi setup local). Password riêng, không dùng root để migrate/test. Không chạy hai bộ test/migration lifecycle song song trên cùng schema. Không copy `.env` hoặc credential `%LOCALAPPDATA%` của tác giả.

`php scripts/configure-env.php --ci` **chỉ chạy** khi `CI=true`, trên MySQL disposable chưa có `cena_store`, `cena_store_test`, `cena_app`, `cena_ci`, và không tồn tại `.env/.env.testing`. Script từ chối server đã provision và tạo file env mới bị Git ignore. Root chỉ có vai trò bootstrap instance trống; không truyền root vào Laravel runtime hay phpunit. Không chạy trên DB dùng chung/production và không xóa schema khác để ép script xanh. Nếu DB chưa được cung cấp, ghi **BLOCKED: MySQL 8.4 instance needed**, không chuyển SQLite.

## Lệnh tham chiếu trong shell sạch (KHÔNG có bằng chứng đã chạy đầy đủ)

```bash
# Checkout repo từ nhánh đã được reviewer cấp quyền, không reset công việc người khác.
git fetch origin main
# Trên branch codex/ext-007-048-prep, giữ base riêng, không force-push/rebase khi có người đang review.
php -v; composer --version; node -v; npm -v; mysql --version
php -m  # xác nhận mọi extension bắt buộc trước install
composer validate --strict
composer install --no-interaction --prefer-dist --no-progress
composer check-platform-reqs
npm ci
npm run build
# Trước khi migrate: bảo đảm .env / .env.testing là các file độc lập, DB user riêng.
php artisan migrate --force --no-interaction
php artisan migrate --env=testing --force --no-interaction
php artisan config:clear --no-interaction
php artisan test --compact
php vendor/bin/pint --format agent
git diff --check
```

CI-only bootstrap được thực hiện *trước* Composer migrate trên disposable MySQL sạch, với `CI=true`, `CENA_DB_HOST`, `CENA_DB_PORT`, `CENA_MYSQL_ROOT_PASSWORD` chỉ truyền qua secret environment (không ghi vào command history, chat, source hay Git). Chạy `php scripts/configure-env.php --ci` theo `.github/workflows/ci.yml`; script sẽ tự tạo `.env/.env.testing` từ examples. Không chạy `migrate:fresh` đối với `cena_store` phát triển. Cân nhắc dùng MySQL service riêng cho từng developer/test runner.

## Codex Cloud/GitHub và Draft PR tích lũy

- Tài liệu chính thức Codex Cloud: https://help.openai.com/en/articles/20001545-using-codex-cloud . Tạo môi trường project với repository đã cấp quyền; cài dependencies theo lockfile và service MySQL disposable riêng. Môi trường cloud cần repository GitHub thực sự khả dụng, không chỉ ChatGPT GitHub connector. Credential cung cấp qua secret store của môi trường, không commit.
- Tài khoản chủ upstream `httrung0308` được chỉ đạo dùng nhánh `codex/ext-007-048-prep` và một **Draft PR** hướng `main`, commit riêng từng EXT; contributor dùng fork nếu cần. Không push main, không auto-merge, không chạm CI/guard để làm test xanh. Tiếp tục branch đã có thay vì tái tạo; ghi `TASK_START_SHA` từng EXT.
- GitHub workflow chỉ là bằng chứng nếu có URL run cụ thể. `npm run build` không chứng minh keyboard/browser và unit tests không chứng minh DB/Policy.

## Readiness / recovery checklist

- [ ] Checkout clean, branch đúng người sở hữu, main/base SHA đối chiếu; làm worktree khác nếu nhánh đang có thay đổi.
- [ ] PHP 8.4 extensions + Composer lock ổn; Node22/npm10 + npm ci chạy; build artifacts được sinh ra từ lockfile.
- [ ] MySQL8.4 private, hai schema/user riêng, không có migration test chạy cùng lúc; config cache đã clear.
- [ ] App key tạo local riêng, `.env` ignored; test database guard bật; không có credentials/traces bị commit.
- [ ] Unit/feature/Pint/npm build/keyboard/browser được ghi **theo lệnh và kết quả thực**, không suy từ ví dụ hướng dẫn.
- [ ] Nếu service không lên: kiểm port/health/log riêng; kiểm `.env` và quyền DB; dừng khi schema dùng chung thay vì drop DB, thay vì bypass TestDatabaseGuard.
- [ ] Draft PR giữ trạng thái WIP cho PREP và CONTRACT_PENDING; owner duyệt tích hợp từng gate.

**Giới hạn hiện tại:** trong workspace bàn giao EXT-047 không có MySQL8.4 riêng, repository đầy đủ/vendor/npm cache và cloud shell chuẩn để cài sạch; hướng dẫn trên là quy trình trích từ repo/CI, **NOT RUN** end-to-end. Không có bằng chứng hoàn tất clean install.
