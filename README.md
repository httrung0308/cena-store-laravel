# Cena Store — Laravel

Project mới tách biệt với `Shoe-Store_HQTCSDL`. Đã triển khai nền tảng P00, schema P01.01, models/Resources P01.02 và helpers/exception contract P01.03 theo `docs/specification.md`. **Giữ giao diện Cena của source gốc**: homepage/hero/collections/navbar/footer được port sang Blade, không dùng trang marketing thay thế. Source Java/Next cũ không bị chỉnh sửa.

## Trạng thái hiện tại

- Laravel13/PHP8.4, MySQL8.4; Blade/Vite/Tailwind3.4/Alpine3/Chart.js4/DomPDF, dependency lock đầy đủ.
- Homepage và shell menu/cart theo thiết kế gốc; font Montserrat tự phục vụ, ảnh gốc có hash/giấy phép. Đây chưa phải catalog/giỏ hàng/đăng nhập thật: nút nghiệp vụ hiển thị thông báo sắp mở, drawer chỉ là trạng thái trống.
- Schema P01.01 có34 bảng miền +6 bảng hạ tầng + `migrations` =41 bảng thực tế,70 FK,4 generated unique helpers và110 CHECK. P01.02 có34 model miền, custom pivot, casts,31 string enums,34 factory cấu trúc và Resource whitelist tách Customer/Admin. Tài liệu: `docs/database/schema.md`. Chưa seed user demo/admin hoặc triển khai workflow bán hàng.
- P01.03: Money integer VND/BCMath, WAC scale4, size canonical, BusinessClock UTC/VN, fingerprint SHA256 và envelope/lỗi Blade–AJAX dùng chung. Các helper không ghi kho/đơn/ledger; FormRequest và workflow thật được nối ở phase tương ứng.
- P01.04 đang làm phần core: roles seed theo slug, demo users opt-in chỉ local/testing, palette12 màu giữ key/hex source gốc và cấu hình shipping. Dataset/validator chờ EXT-001/002; toàn task chưa PASS và DatabaseSeeder chưa nối seed đầy đủ.
- MySQL test riêng, tài khoản test không được truy cập database phát triển, PHPUnit guard, CI và bằng chứng cài sạch.
- Nhật ký: `docs/ai-progress.md`; nghiệm thu: `docs/evidence/p00-verification.md`; contract: `docs/architecture/decisions.md`; rubric: `docs/requirements/traceability.md`.

## Phiên bản và điều kiện

Phiên bản thực cài ở `docs/runtime-versions.json`. PHP8.4 (bcmath, ctype, curl, dom, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, tokenizer, xml, xmlwriter, zip), Composer2.9.4, Node22.x>=22.12 (đã kiểm22.23.4/npm10.9.9), MySQL8.4. Không dùng npm `--force` hoặc SQLite để bỏ qua lỗi.

## Chạy trên máy Windows hiện tại

Mở **PowerShell7** tại thư mục project. Scripts dùng runtime portable ở `%LOCALAPPDATA%/CenaStore`; không đổi PATH hệ thống, không dùng datadir Laragon hiện có. Máy khác cần có MySQL8.4/Mailpit và chỉ đường dẫn bằng tham số của setup-local-runtime.

```powershell
Set-Location 'C:\Users\trung\Downloads\cena-store-laravel'
.\scripts\setup-local-runtime.ps1
.\scripts\start-local-services.ps1
.\scripts\php.ps1 scripts/configure-env.php --local
.\scripts\composer.ps1 validate --strict
.\scripts\composer.ps1 install --no-interaction --prefer-dist
.\scripts\composer.ps1 check-platform-reqs
.\scripts\npm.ps1 ci
.\scripts\npm.ps1 run build
.\scripts\php.ps1 artisan migrate --force --no-interaction
.\scripts\php.ps1 artisan migrate --env=testing --force --no-interaction
.\scripts\php.ps1 artisan storage:link --no-interaction
.\scripts\php.ps1 artisan test --compact
.\scripts\start-local.ps1
```

Trang web: http://127.0.0.1:8000. Health: `/up`. Mailpit: http://127.0.0.1:8025 (SMTP1025). MySQL:127.0.0.1:3307. Server PHP này chỉ dùng phát triển local.

Lệnh bọc `scripts/php.ps1`, `composer.ps1`, `npm.ps1` có cùng tham số tương ứng với `php`, `composer`, `npm`. Nếu PATH đã đúng PHP8.4/Node22, có thể dùng lệnh gốc. Setup tải PHP/Node/Composer chính thức và kiểm SHA256, yêu cầu VC++ runtime x64 sẵn có. MySQL/Mailpit không tự tải; có thể chỉ đường dẫn cài đặt:

```powershell
.\scripts\setup-local-runtime.ps1 -MySqlBin 'D:\tools\mysql84\bin' -MailpitBinary 'D:\tools\mailpit.exe'
```

## Database, env và storage

- `start-local-services.ps1` chỉ quản lý instance riêng trong `%LOCALAPPDATA%/CenaStore/services/mysql84`, không động vào DB cũ. Nếu port bị tiến trình khác chiếm, script dừng thay vì provision nhầm.
- `cena_store`/`cena_app` và `cena_store_test`/`cena_test` có password khác nhau, quyền giới hạn schema. Mật khẩu bootstrap giữ trong `%LOCALAPPDATA%/CenaStore/services/local-credentials.json` (ACL tài khoản hiện tại/SYSTEM); không hiển thị/commit.
- `.env` và `.env.testing` được tạo từ example, APP_KEY ngẫu nhiên, bị Git ignore. Script local không ghi đè env đã tồn tại. Máy mới không sao chép credential của tác giả: tự provision và tạo env mới.
- Không chạy `migrate:fresh` vào DB phát triển. Nâng cấp P01.01 chỉ dùng migrate để thêm schema. SchemaContractTest dùng transaction trên MySQL thật; MigrationLifecycleTest reset/rollback/dựng lại chỉ database test sau guard, kiểm41 bảng và schema có thể tái tạo. Không chạy đồng thời nhiều test process dùng chung schema test này.
- Nếu từng cache cấu hình, chạy `php artisan config:clear` trước test. Test cố tình từ chối config cache; `DB_DATABASE=cena_store` trong config sẽ không được phép chạy qua guard.
- `public/storage` chỉ liên kết `storage/app/public`; `storage/app/private` không có symlink/route tự serve. Không đặt chứng từ/ảnh bằng chứng private vào `public/legacy` hoặc public disk.
- Local session/cache/queue dùng database; PHPUnit session/cache array, queue sync, mail array để tránh side effects. Payment key thật không có trong P00; VNPay đang tắt.

## CI và cài sạch trên máy khác

`.github/workflows/ci.yml` chuẩn bị PHP8.4/Node22.23.4 và MySQL8.4 disposable. `php scripts/configure-env.php --ci` (chỉ khi `CI=true`) tạo DB/app+test user ngẫu nhiên và env mới; root chỉ bootstrap, ứng dụng/test chạy bằng user hạn chế. CI không cần secret từ máy tác giả.

Sau khi chuẩn bị runtime + service MySQL disposable và env `CI=true`, `CENA_DB_PORT`, `CENA_MYSQL_ROOT_PASSWORD`, thực hiện chính các bước trong workflow: configure-env, composer validate/install/check-platform-reqs, npm ci/build, migrate hai DB, test, Pint và git diff. **Không chạy mode CI trên server phát triển/production đang dùng chung**, vì mode này dành cho service trống và có thể đặt lại password hai user CI cố định.

Repo công khai: [httrung0308/cena-store-laravel](https://github.com/httrung0308/cena-store-laravel), được người dùng cho phép công bố ngày31/10/2026. `main` là nhánh tích hợp; bên ngoài fork và gửi PR để chủ repo review/merge. Đọc [CONTRIBUTING.md](CONTRIBUTING.md) và [48 task + prompt](docs/collaboration/EXTERNAL_TASKS_AND_PROMPTS.txt). Các log P00 trong evidence là lịch sử trước khi publish, không phải trạng thái remote hiện tại. Baseline P01.03 đã có [GitHub CI thực PASS](https://github.com/httrung0308/cena-store-laravel/actions/runs/41180343375) trên Linux: dependency/build/migrate/tests/Pint đều qua. Xem [Actions](https://github.com/httrung0308/cena-store-laravel/actions) cho commit mới nhất; không coi kết quả local là remote pass. Không commit env/uploads/vendor/node_modules/build hoặc bản project giải nén lồng.

## Cấu trúc cần đọc

| Khu vực | Vai trò hiện tại |
|---|---|
| `app/Support/TestDatabaseGuard.php`, `tests/TestCase.php` | Chặn test trỏ sai DB/user hoặc config cache |
| `resources/views/storefront`, `components/storefront` | Homepage và shell gốc chuyển sang Blade |
| `resources/css/app.css`, `tailwind.config.js` | Tokens/CSS source gốc, animation CSS thay Framer |
| `public/legacy`, `LICENSE-legacy-ui` | Ảnh/favicon bản gốc và giấy phép/attribution |
| `database/migrations` |3 migration hạ tầng P00 giữ nguyên +6 migration P01.01 tạo34 bảng miền và các ràng buộc |
| `docs/database/schema.md`, `tests/Fixtures/schema-contract.json` | Dictionary kỹ thuật và contract để đối chiếu metadata MySQL thực tế |
| `app/Models`, `app/Enums`, `database/factories` |34 model miền, custom pivot/timestamps/history,31 enum và34 factory cấu trúc dùng kiểm thử |
| `app/Http/Resources/Customer`, `Admin` |19 Resource khách,34 Resource Admin; whitelist, owner404 và current-admin403; không phải endpoint mới |
| `config/store.php` | VND, cost scale, timezone nghiệp vụ, 1 kho/1 shipment |
| `tests/Feature`, `tests/Unit` | HTTP/config/PDF/MySQL/safety smoke và unit guard |
| `scripts` | Runtime portable, service riêng, env, server local |
| `docs` | Đặc tả, ADR, rubric, tiến độ và evidence |

## Tiếp theo

P01.03 đã hoàn thành Money/clock/enum labels/exception contract. Task tiếp theo: **P01.04 — Foundation seed, factories và dataset địa chỉ**. Auth/catalog/cart/payment/ledger services chưa được triển khai. Các model chứng từ, role, stock/cost và trạng thái tiền được guard; service phải gán thuộc tính đã kiểm tra một cách tường minh, không truyền request.all() vào forceFill. Mọi màn hình chuyển sau tiếp tục dùng giao diện source gốc; không tự thiết kế lại UI.

## Nguồn tham chiếu

UI chuyển từ source do người dùng cung cấp (cena261, MIT — `LICENSE-legacy-ui`). Footer giữ nguồn cena261/nike.com như bản gốc; ảnh không được coi là do AI tạo. Laravel skeleton giữ các thông báo giấy phép trong package dependencies. Hướng dẫn chính thức: [Laravel13](https://laravel.com/docs/13.x), [Tailwind3](https://v3.tailwindcss.com/docs/installation), [PHPUnit12.5](https://docs.phpunit.de/en/12.5/), [setup-php](https://github.com/shivammathur/setup-php), [setup-node](https://github.com/actions/setup-node).

## Core foundation trong lúc chờ dataset

Đây là phần identity/config của P01.04, không phải toàn FoundationSeeder. Seed roles idempotent, mặc định không tạo demo user:

```powershell
.\scripts\php.ps1 artisan db:seed --class=CoreFoundationSeeder --no-interaction
```

Demo chỉ bật có chủ đích trong local/testing bằng STORE_DEMO_USERS=true và STORE_DEMO_PASSWORD riêng trong env không commit (12..72 bytes, không có mật khẩu mặc định). Khi bật, tạo ba tài khoản .test theo config, một role và giỏ trống mỗi user. Seed lại không đổi password/profile, không mở khóa/khôi phục/tái cấp quyền cho tài khoản đã thay đổi; email trùng không được dùng làm cớ nâng quyền. Không gửi mail. Không bật trên staging/production, kể cả dùng --force. Không chạy config:show store khi có secret demo trong cấu hình.

Palette config tái sử dụng12 key của source: red/blue/green/yellow/black/white/gray/pink/purple/orange/brown/lime. Không có bảng palette mới. Shipping giữ30k/40k và ngưỡng2triệu VND; HCM province code/dataset version chưa thiết lập cho tới khi dữ liệu được duyệt. Đây chưa phải PricingService. Factory wardOf chỉ là fixture synthetic cho test, không được dùng làm địa giới demo thật.

Hợp đồng với bên cộng tác: [administrative-data-contract.md](docs/collaboration/administrative-data-contract.md). Giữ nguyên48 allowlist và prompt đã giao; owner không tự làm lại EXT-001/002/003/004.

## Chuẩn bị độc lập ledger/outbox trong lúc chờ EXT

Nhánh codex/p01-05-ledger-outbox-primitives chuẩn bị phần persistence của P01.05, dựa trên core PR#1. Nhánh codex/p01-05-inventory-primitives bổ sung InventoryService và xếp sau PR#2. P01.04 vẫn chờ dataset/validator; chưa đóng gate P01.05 hoặc có workflow checkout/delivery/refund thật. Không thay routes/UI/schema/lockfiles, không có producer/scheduler/transport được bật và không gửi email thật.

SalesLedgerService::postUnique nhận operation key, enum type, signed VND deltas, source IDs và occurredAt snapshot; chỉ gọi trong transaction nghiệp vụ. Service kiểm whitelist/type/range/shape/source chain và insert hoặc replay đúng intent; sửa sai bằng entry bù mới. Nó không thay kiểm tra paid/delivered/refund budget/Policy/row locks của service nghiệp vụ tương lai. Không truyền request.all() vào service.

OutboxService::enqueue phải cùng transaction nghiệp vụ, dedup key chỉ dùng cho đúng một intent. Payload chỉ có JSON scalar/array đã whitelist phía producer; known password/OTP/token/bank credential keys, float/object/depth/UTF8/size lỗi bị chặn. Cần message DTO theo event ở P10; kiểm key chung không chứng minh một chuỗi bất kỳ không chứa bí mật. Replay không đổi payload, schedule hoặc trạng thái đã gửi.

ProcessOutbox chỉ mang event ID và afterCommit=true. Claim/delivery/ACK không được chạy trong transaction nghiệp vụ. Lease mặc định120giây, job timeout60giây, tối đa10lần thử, backoff30giây tăng đến3600giây; stale/expired worker không thể ACK/fail lease mới. Registry mặc định rỗng: handler thiếu thành FAILED chứ không success giả. Chỉ đăng ký transport thông báo từ code server đã review, không chọn class từ payload và không chạy lại tiền/kho trong delivery. Chưa nối scheduler/worker CLI hoặc mail templates của EXT-045. Email là ít-nhất-một-lần; crash sau provider send trước ACK có thể gửi lặp, transport nên dùng dedup_key khi provider hỗ trợ.

Kiểm tra trọng tâm:

```powershell
.\scripts\php.ps1 artisan test --compact tests/Feature/AtomicInfrastructureTest.php tests/Feature/OutboxDeliveryTest.php tests/Feature/AtomicConcurrencyTest.php
```

Concurrency test dùng hai PHP process/MySQL connection thật và barrier ép cả hai đọc key chưa tồn tại trước insert. Worker fixture tự kiểm TestDatabaseGuard; credential truyền qua process env, không argv/output. Committed fixtures được xóa theo namespace/IDs của chính test; không dùng migrate:fresh, không chạy song song các test suite dùng chung schema. Đây là bằng chứng cho primitive, chưa phải race gate payment/inventory của các phase sau.

## InventoryService chuẩn bị độc lập

InventoryChange là intent do server dựng, không nhận trực tiếp request: operationKey, variantId, action enum, referenceType/refId, quantity, input cost khi nhập hàng, actorId và reason. InventoryService cung cấp reserve/release/issue/receive/receiveQuarantine/restock/writeOff/adjust và applyBatch. Service bắt buộc transaction của caller; savepoint rollback toàn batch khi một SKU lỗi. Các biến thể được khóa theo ID tăng trước khi ghi, kết quả trả theo thứ tự intent. Tất cả movement có delta, before/after ba bucket và giá vốn snapshot; không có API sửa/xóa movement.

Caller phải kiểm Policy và khóa actor/quyền, chứng từ gốc, dòng chứng từ/unit liên quan theo thứ tự canonical trước khi gọi; các locking-read của service đọc lại dữ liệu hiện hành, không thay quy trình khóa root. Retry deadlock bọc toàn transaction SQL của nghiệp vụ; không SMTP/HTTP/file I/O trong closure retry. Đổi size phải gộp hai movement vào cùng transaction/batch. Service không tự chuyển trạng thái order/import/RMA, không kiểm thay deadline/payment/inspection/supplier hay tạo unit/COGS sale/refund; những workflow đó còn ở phase sau.

Quantity chỉ int/digit string dương; tối đa2147483647 cho mỗi movement vì delta là INT signed, trong khi mỗi bucket cho phép tới4294967295. Không nhận float hoặc delta do client tự tính. Reserve không vượt available hoặc lượng còn lại của dòng đơn/RMA; release/issue phải tiêu thụ reserve đúng reference của mình. Receipt import kiểm SKU, cost và quantity của dòng DRAFT. Quarantine theo từng shipment-return-item/RMA, mỗi nguồn một đôi: chỉ nhập lại/hủy đúng hàng của nguồn đó, không lấy hàng cách ly từ chứng từ khác. WarrantyRepair của khách không tạo movement kho.

WAC dùng Cost/BCMath scale4, bao gồm stock đang reserve và loại quarantine. Xuất snapshot WAC tại lúc xuất; restock dùng basis của lần nhận cách ly. Stock về0 giữ giá vốn cuối, lần nhập tiếp theo tính theo hàng mới. Replay exact key/intent trả nguyên row cũ dù WAC/actor/catalog/source status đã đổi; key giống nhưng khác nội dung hoặc khác chữ hoa/thường trả IDEMPOTENCY_MISMATCH. Key mới cũng không được nhận lại/tiêu thụ cùng tài sản vượt lượng chứng từ.

Điều chỉnh thủ công cần Admin hiện hành, reason ít nhất20 ký tự và reference_id biên bản do caller xác minh. MANUAL_ADJUST_OUT chỉ lấy available và ghi INVENTORY_LOSS cùng transaction, expense làm tròn một lần theo tổng basis×quantity; overflow hủy cả thay đổi kho. QUARANTINE_WRITEOFF cần Admin/reason và reference nguồn cách ly thật; RMA còn mở bị chặn, không dùng MANUAL_ADJUSTMENT để rút hàng cách ly không rõ chủ. RETURN_WRITTEN_OFF là disposition của workflow kiểm hàng; workflow quyết định bút toán tổn thất nếu chưa ghi chi phí, tránh ghi hai lần.

Trước ghi mới, service cộng và kiểm chuỗi movement từ0, before/after/cost và ownership của reserve/quarantine so với số dư hiện tại. Mismatch chặn bằng STATE_CONFLICT; assertConsistent chỉ kiểm, không sửa số dư/lịch sử. Phiên bản PREP đọc toàn history của SKU dưới lock để ưu tiên đúng; cần đo tải và thiết kế đối chiếu/checkpoint ở P04 trước production. Chưa có scheduled reconciliation hoặc endpoint được bật.

```powershell
.\scripts\php.ps1 artisan test --compact tests/Feature/InventoryServiceTest.php tests/Feature/InventoryConcurrencyTest.php
```

InventoryConcurrencyTest giữ khóa SKU ở connection cha, đợi cả hai worker bước vào locking-read rồi mới nhả khóa, buộc tranh chấp thực. Worker có read snapshot cũ trước khi gọi service để kiểm current-read trong MySQL REPEATABLE READ. Chỉ fixture test dùng file barrier; code nghiệp vụ không I/O. Child workers được join/stop trước cleanup và không chạy cùng suite/lifecycle khác trên schema test.
