Warning: truncated output (original token count: 32788)
Total output lines: 582

# Nhật ký triển khai AI — Cena Store

## 20/11/2026 — Album Nike cho demo local

Thay album Bape/Converse trước đây bằng bộ dữ liệu 12 mẫu Nike, mỗi mẫu 4 liên kết ảnh cùng mã phối màu và trang sản phẩm gốc tại `database/data/nike-multiview-demo.json`. Seeder `MultiAngleShoeDemoSeeder` chỉ chạy khi bật demo ở local/testing; tạo hàng qua ProductManagementService và StockImportService, chạy lại không nhân kho. Hai mẫu cũ được ngừng hiển thị nếu đúng mã demo, không xóa chứng từ. Gallery bốn ảnh và hover zoom tiếp tục dùng thành phần hiện có. Ảnh phụ thuộc nguồn Nike bên ngoài; giá, size và tồn kho là mô phỏng.

Đặc tả: `specification.md`. Ngày làm việc:31/10/2026. Yêu cầu bổ sung: giữ giao diện source gốc; homepage đã chuyển từ JSX sang Blade, các phase sau tiếp tục dùng giao diện gốc.

| Task | Trạng thái hiện tại | Bằng chứng / phần còn lại |
|---|---|---|
| P00.01 — Freeze contract/rubric | **Hoàn thành** | decisions.md, traceability.md, snapshot spec và source-manifest;34+6 bảng/enum/invariants đúng canonical; rubric gốc đã đối chiếu |
| P00.02 — Laravel/dependency/runtime | **Hoàn thành** | PHP8.4.27/Laravel13.39.0/MySQL8.4.3; composer validate/install/platform, npm ci/build qua; homepage gốc Blade và desktop/mobile đã kiểm |
| P00.03 — Tests/config/CI | **Hoàn thành local** |19 tests/39 assertions trên DB test thật và clean-copy DB mới; actionlint qua; env/private ignore; negative guards qua;95 file source/config khớp;320 file nguồn cũ giữ nguyên |
| P01.01 — Canonical schema/migrations | **Hoàn thành** |34 bảng miền;483 cột miền gồm4 helper;70 FK,79 index/unique khai báo,110 CHECK;135 tests/2929 assertions qua trên MySQL thật, gồm rollback/dựng lại và thứ tự test ngẫu nhiên |
| P01.02 — Models/relationships/casts/Resource whitelist | **Hoàn thành** |34 model miền + DomainModel,147 quan hệ typed,31 enums,34 factory cấu trúc,19 Customer +34 Admin Resources;423 test mới qua; toàn suite558 tests/5808 assertions PASS |
| P01.03 — Money/clock/enums/exception contract | **Hoàn thành** |115 test mới; toàn suite673 tests/6848 assertions PASS trên MySQL, random order;214 file PHP syntax PASS; nguồn cũ/UI/schema giữ nguyên |
| P01.04 — Foundation seed/factories/dataset | **Đang làm: core, chờ EXT-001/002** | Core roles/demo/config/factory:700tests/7067assertions PASS; dataset/validator/import và code ship chờ EXT-001/002; chưa chuyển P01.05 |
| P01.05 — Inventory/ledger/outbox chuẩn bị độc lập | **PREP, gate chưa mở** | Chưa vượt P01.04; 819tests/7543assertions PASS; inventory/ownership/WAC/replay/rollback và persistence/lease/retry trên nhánh riêng, chưa kích hoạt workflow nghiệp vụ |

**Gate P00: PASS**, xem [biên bản nghiệm thu](evidence/p00-verification.md) và JSON/log/ảnh trong `evidence`. Tại thời điểm nghiệm thu P00/P01.03 chưa có commit/remote; việc công bố GitHub được người dùng ủy quyền sau đó, xem mục cộng tác cuối nhật ký. Không gắn kết quả local thành CI remote hoặc sửa source cũ. Chưa có các chức năng auth/catalog/checkout/kho/payment/RMA thật; menu/cart hiện là shell giao diện.

**Gate P01.01, P01.02 và P01.03: PASS**. Task tiếp theo: **P01.04 — Foundation seed, factories và dataset địa chỉ**. Schema giữ41 bảng thực tế, các lớp chặn DB/user/config-cache giữ nguyên. Toàn phase P01 chưa hoàn tất: foundation seed/dataset/state nghiệp vụ và nền transaction/outbox vẫn ở các task P01.04/P01.05.

Yêu cầu giao diện áp dụng xuyên suốt: port theo source Cena gốc ở mọi màn hình tiếp theo. Không tự thay bố cục/font/slogan/ảnh bằng thiết kế mới. Font phục vụ local, ảnh/favicon giữ hash, SVG icons giữ path/license; Framer đổi sang CSS/Alpine do stack Laravel.

Các giới hạn hồ sơ: rubric quy định nộp sau khi kết thúc học phần một tuần, ngày cụ thể và duyệt đề tài chưa có; danh sách/phân công nhóm chưa nhận, không bịa thông tin/đóng góp/điểm số.

## P01.01 — Nhật ký và kiểm tra từng yêu cầu (31/10/2026)

Yêu cầu người dùng: chỉ triển khai P01.01, giữ nguyên source cũ và giao diện gốc, kiểm tra đủ rồi cập nhật nhật ký; **không tạo báo cáo nghiệm thu riêng**. Kết quả và giới hạn được ghi trực tiếp tại đây; `database/schema.md` là tài liệu kỹ thuật schema theo task.

### Tệp thay đổi

- Sáu migration mới trong `database/migrations`, từ `2026_10_31_021526_create_identity_and_locations_tables.php` đến `2026_10_31_021531_add_canonical_schema_constraints.php`. Các nhóm ở giữa: catalog/inventory; carts/orders; payments/shipments/reviews; after-sales/refunds/ledgers. Ba migration `0001_*` của P00 giữ nguyên byte/hash.
- `tests/Feature/SchemaContractTest.php`, `MigrationLifecycleTest.php`, `tests/Fixtures/schema-contract.json`; cập nhật InfrastructureConnectionTest cho schema sau P01.01, không bỏ kiểm tên database/tài khoản/quyền truy cập chéo.
- `docs/database/schema.md`: danh sách34 bảng,483 cột/kiểu/null/default,70 FK,79 index/unique khai báo,4 helper expressions và110 CHECK; ranh giới giữa bảo vệ SQL và nghiệp vụ service.
- README, AGENTS, decisions, traceability và nhật ký được cập nhật trạng thái. Không thay dependencies/lockfiles, env, routes, Blade/CSS/JS, ảnh hoặc source Java/Next cũ. Không tạo models/seed/services vượt sang task sau; không commit/push.

### Đối chiếu yêu cầu P01.01

| Yêu cầu | Kiểm tra thực tế | Kết quả |
|---|---|---|
| Đúng thứ tự phụ thuộc §6.5, đủ34 miền+6 hạ tầng | Metadata bảng/cột/FK/index/CHECK cho từng bảng; development `db:show --counts` xác nhận41 bảng | PASS |
| Không password_reset_tokens, không tạo trùng sessions/cache/jobs | SchemaContractTest đối chiếu chính xác tập41 bảng; ba migration P00 nguyên trạng | PASS |
| FK, CHECK, unique, generated helper |70 FK đúng parent, DELETE/UPDATE rule;110 CHECK đều ENFORCED=YES; toàn bộ index canonical được tìm thấy bằng danh sách cột/tính unique | PASS |
| Default/thumbnail/RMA mở không trùng | SQL insert/update bị1062 khi giữ trùng slot; soft-delete/bỏ thumbnail/đóng RMA giải phóng slot; kiểm đủ6 trạng thái RMA mở và3 trạng thái terminal | PASS |
| Một payment PENDING/đơn, vẫn giữ lịch sử | Pending slot từ chối attempt trùng; EXPIRED giải phóng slot và giữ row cũ | PASS |
| Nghiệm thu theo metadata DB, không theo số file | information_schema.COLUMNS/STATISTICS/KEY_COLUMN_USAGE/REFERENTIAL_CONSTRAINTS/TABLE_CONSTRAINTS + Schema::getTableListing | PASS |
| Database trắng migrate có41 bảng | MigrationLifecycleTest reset chỉ DB test về còn bảng migrations, migrate lại đúng41 và định nghĩa cột giữ nguyên | PASS |
| Rollback rồi migrate lại | Reset toàn bộ rồi dựng lại; rollback riêng6 migration P01 còn7 bảng P00, migrate lại41; không tắt FK để che thứ tự sai | PASS |
| FK/SKU/reserved vượt stock bị từ chối | Foreign key sai1452; xóa chứng từ có con1451; SKU/size trùng1062 kể cả sau soft-delete; reserved>stock3819 | PASS |
| Không hồi quy nền tảng/giao diện | Suite đầy đủ và random order; home/health200;320 file nguồn cũ và23 file UI/config/dependency khớp baseline trước task | PASS |

### Lệnh đã chạy và kết quả

Sử dụng PHP8.4.27/Laravel13.39.0/MySQL8.4.3 từ runtime P00, lệnh bọc `scripts/php.ps1`.

- `php artisan migrate:fresh --env=testing --force --no-interaction`: thành công trên **cena_store_test** sau khi xác nhận tên database; dùng trong quá trình dựng schema test. Không chạy fresh/reset/rollback database phát triển.
- `php artisan test --compact tests/Feature/SchemaContractTest.php`: **115 tests/2874 assertions PASS**. Bao gồm bảng/columns/default/null/identity/engine/collation/keys/checks, dữ liệu sai và valid cases: decimal4 chữ số, size42.5, ledger signed, ảnh URL, guest cart.
- `php artisan test --compact tests/Feature/MigrationLifecycleTest.php`: **1 test/10 assertions PASS**; full reset/migrate và rollback riêng phần P01/migrate được kiểm trên DB test đã guard.
- `php artisan test --compact tests/Feature/InfrastructureConnectionTest.php`: **3 tests/12 assertions PASS**, xác nhận MySQL test8.4/UTC/user riêng, các bảng hạ tầng còn, test user không đọc được development DB.
- `php artisan migrate --force --no-interaction`: thành công cho development **cena_store**; batch1 là3 migration P00, batch2 là6 migration P01.01. Chỉ thêm schema, không seed/tạo đơn hay thao tác tiền/kho.
- `php artisan migrate:status` và `php artisan db:show --counts`:9 migrations Ran,41 bảng development;34 bảng miền đều0 row, sessions hiện hữu được giữ.
- `php artisan test --compact --order-by=random --random-order-seed=20261031`: **135 tests/2929 assertions PASS**;135/135 test qua, thời gian khoảng145 giây. Đây là kết quả toàn suite sau thay đổi, không phải chỉ đếm thêm vào kết quả P00.
- `php vendor/bin/pint --format agent` và `--dirty --format agent`: PASS sau định dạng; PHP `-l` trên12 migration/test files: PASS; `git diff --check`: PASS.
- Đối chiếu SHA256: **320/320 file source cũ giữ nguyên**, **23/23 file giao diện/config/dependency giữ nguyên**, **3/3 migration P00 giữ nguyên**. HTTP `/` và `/up`200; HTML vẫn slogan/hero gốc. Không chỉnh giao diện trong P01.01, nên không tạo ảnh hoặc báo cáo UI mới.

### Vấn đề đã xử lý và giới hạn

- Index administrative_units theo4 cột vượt giới hạn64 ký tự khi dùng tên tự sinh Laravel: đã đặt tên ngắn, giữ nguyên thứ tự cột. Lỗi chỉ xảy ra khi phát triển trên DB test và đã dựng lại/test thành công.
- Sửa hai lỗi trong test harness mới: không phát lệnh UPDATE với mảng rỗng ở trường hợp payment mặc định PENDING; chuẩn hóa metadata objects thành arrays trước assertSame khi so schema sau dựng lại. Chạy lại các file bị sửa và suite toàn bộ đều qua.
- P01.01 chỉ bảo vệ schema cục bộ; owner nhiều cấp, state machine, quota/row-lock/idempotency, snapshot bất biến, tổng unit/line/order, palette/size và Policy vẫn cần triển khai/test ở các task tương ứng. Các fixtures SQL là dữ liệu cấu trúc tối thiểu, không phải demo nghiệp vụ đã vận hành.
- Giữ nguyên guard DB/user/config-cache. MigrationLifecycleTest reset schema test, nên không chạy nhiều test process chung `cena_store_test` đồng thời.
- Không có bút toán tiền/kho ở database phát triển; không có số liệu trước/sau ledger để báo như nghiệp vụ đã chạy. Workflow CI hiện hữu sẽ chạy các test mới khi có remote, nhưng chưa có GitHub Actions remote run trong task này.

## P01.02 — Nhật ký và kiểm tra từng yêu cầu (31/10/2026)

Yêu cầu: triển khai P01.02 theo kế hoạch, giữ nguyên source cũ và giao diện, kiểm tra đầy đủ rồi cập nhật nhật ký; **không tạo báo cáo nghiệm thu riêng**. Kết quả ghi trực tiếp tại đây, phần tham chiếu kỹ thuật bổ sung vào `database/schema.md` hiện hữu.

### Tệp và phạm vi đã triển khai

- `app/Models`:34 model miền cho đủ34 bảng và một abstract DomainModel dùng chung HasFactory/dateFormat; User extends Authenticatable, UserRole extends Pivot. Thay scaffold User dùng `name` bằng `full_name` đúng schema.
-147 relationships khai báo return type cụ thể, gồm70 belongsTo theo FK, inverse relations, User↔Role qua custom pivot, Product.variants qua ProductColor, thumbnail có điều kiện và các relation lịch sử có chủ đích.
- `app/Enums`:31 string-backed enums;18 enum canonical §5.2 và discriminator/domain hữu hạn còn lại. PHP case TitleCase, giá trị DB/JSON giữ nguyên. Chưa làm Money/clock/exception contract/tiện ích labels của P01.03.
- Cast rõ integer cho tiền/delta/IDs, decimal string cho cost/rating/size_numeric, boolean/immutable date/datetime/array/enum. User.password hashed; Refund.destination_ciphertext encrypted:array. Date format microseconds UTC; append-only không ghi cột updated_at không tồn tại.
- Model fillable chỉ mở các trường mô tả/đầu vào cho phép. Stock/cost/role/owner FK/trạng thái tiền/helpers và các chứng từ hệ thống bị guard; dữ liệu secret/cost/helper/private path tương ứng có hidden bảo vệ raw model serialization thêm một lớp.
- `app/Http/Resources/Customer`:19 Resource whitelist + OwnedResource abstract; `Admin`:34 Resource whitelist + AdminResource abstract. Customer tài liệu cá nhân kiểm owner/active và trả404 cho guest/owner khác; Admin tài chính kiểm active/not-trashed/current admin role và trả403 khi không đủ quyền. Không tạo API route mới hoặc đổi giao diện.
- `database/factories`:34 factory cấu trúc có FK parents và state cần cho kiểm thử (inactive, administrator, thumbnail, stocked, succeeded, guest). Sửa UserFactory theo full_name và password hashed cast; không seed vào development. Seed nền/dataset thật/demo xuyên workflow vẫn thuộc P01.04/P10.
- `tests/Feature/ModelRelationsTest.php`, `ModelSerializationTest.php`, `tests/Fixtures/model-contract.json`, `resource-contract.json`: contract và kiểm tra MySQL/serialization. README/AGENTS/decisions/traceability/schema reference được cập nhật trạng thái và giới hạn.

### Đối chiếu yêu cầu P01.02

| Yêu cầu | Kiểm tra hành vi thực tế | Kết quả |
|---|---|---|
| Đủ models, pivot custom, relationships §6.6 |34 factory persist+refresh;70 belongsTo query đúng FK/parent thật; UserRole assignment ID/assigned_at, quan hệ ngược và role slug; Unit→item→order→user/RMA resolve | PASS |
| Timestamps đúng bảng append-only | Factory persist đủ34 bảng không chèn cột lạ;5 bảng chỉ created_at dùng UPDATED_AT=null; UserRole/PaymentEvent timestamps=false; UTC immutable/microseconds123456 round-trip | PASS |
| Cast enum/boolean/date/array rõ, tiền nguyên/cost string |182 backing-value cases hydrate/set chính xác; mọi cột hiện hữu được kiểm cast qua model persist; money integer999999999, cost123456.7891, size42.5 và JSON giữ đúng kiểu | PASS |
| Không mở mass assignment stock/cost/role/trạng thái tiền |58 trường hợp fill dữ liệu không tin cậy không đổi thuộc tính protected; UserRole khóa cả user_id/role_id/assigned_by/assigned_at; helper không nhận dữ liệu client | PASS |
| Tách Resource Customer/Admin và whitelist | Serialize đủ19 Customer/34 Admin Resource; cột private giả thêm vào model không lọt payload; kiểm credential/helper/proof paths không xuất hiện | PASS |
| Không lộ giá vốn, proof path, staff note cho khách | Duyệt payload lồng nhau; RMA đã eager load histories/attachments chỉ trả CUSTOMER history, không nội bộ/path; Admin xem cost đúng whitelist nhưng Customer view của chính Admin vẫn không có cost | PASS |
| Owner/privacy và quyền hiện tại | Guest/owner khác404 cho cả14 Resource cá nhân; Staff/customer/inactive/deleted/revoked-admin403 ở Admin tài chính; hasRole không tin collection roles cũ | PASS |
| Chứng từ còn đọc được khi catalog bị ẩn | Xóa mềm category/product/color/variant, đổi tên catalog rồi serialize đơn từ snapshot cũ; quan hệ historical vẫn resolve; review bị ẩn không làm đơn404 | PASS |
| Chống N+1 theo §6.6 |4 product eager load category/colors.thumbnail/colors.variants đúng5 query; serialize catalog đã eager load thêm0 query | PASS |
| Không hồi quy/bảo toàn nguồn | Suite đầy đủ random order, schema vẫn41 bảng;320 source cũ và32 file UI/dependency/migrations khớp baseline; home/health200 | PASS |

### Lệnh và kết quả cuối

- `php artisan test --compact tests/Feature/ModelRelationsTest.php tests/Feature/ModelSerializationTest.php`: **423 tests/2879 assertions PASS** (349 model/cast/relationship/protection cases +74 Resource cases).
- `php artisan test --compact --order-by=random --random-order-seed=20261031`: **558 tests/5808 assertions PASS**, khoảng149 giây; gồm cả135 test nền/schema/migration lifecycle đã có. Không chạy test song song dùng chung schema test.
- `php vendor/bin/pint --format agent` và `--dirty --format agent`: PASS sau format. PHP `-l` trên **157 file** models/enums/Resources/factories/hai test mới: PASS. `git diff --check`: PASS.
- Đối chiếu SHA256 baseline trước task: **320/320 file source cũ giữ nguyên**; **32/32 file protected giữ nguyên**, gồm23 file UI/config/dependency và9 migration. Không tạo/sửa/xóa migration hoặc nâng dependency trong P01.02.
- HTTP `/`, `/up`:200; HTML vẫn slogan “Sport, the smart choice.” và hero gốc. `route:list --except-vendor` vẫn chỉ có route ứng dụng `home`, không thêm endpoint giả. Database development vẫn41 bảng; không chạy migration/seed nghiệp vụ trong task.
- Test chỉ dùng MySQL `cena_store_test` bằng user test đã guard; toàn bộ factory data nằm trong transaction test. MigrationLifecycleTest hiện hữu reset/dựng lại chỉ DB test. Không commit/push, không chạy GitHub Actions remote.

### Vấn đề đã xử lý và quyết định cần giữ khi làm task sau

- Pivot mặc định của Laravel có guarded rỗng: test phát hiện UserRole nhận role/user/actor/time qua fill. Đã đặt guarded=['*'] rõ ràng, giữ Fillable rỗng. RoleService ở P02 phải ghi pivot bằng thuộc tính server đã xác thực và gán tường minh, không mở lại mass assignment chỉ để dùng attach/sync metadata. Test/factory tạo pivot bằng cơ chế Factory dành cho dữ liệu test; relation roles/assignment đọc đúng custom pivot.
- Request trong test Resource bị AuthServiceProvider thay user resolver khi rebind vào container: đã đặt resolver sau rebind, chạy lại owner/admin tests. Guard không bị nới lỏng để làm test qua.
- Review invisible nếu serialize trực tiếp bị404; khi nằm trong đơn đã mua, Resource unit bỏ review đó thành null để không làm mất cả chứng từ. Đã thêm test hồi quy và chạy lại.
- Các bảng model có default attributes khớp default DB để instance vừa create có is_active/status đúng trước refresh; generic model tests vẫn persist+refresh để kiểm dữ liệu thật.
- Giữ ranh giới: chưa có middleware/Policy/AuthService/RoleService, nghiệp vụ kho/thu-hoàn/RMA, JSON envelope/exception contract hoặc API mới. Admin Resources có cost chỉ dành Admin; Staff vận hành cần DTO theo quyền module sau này. Customer CartResource hiện không nhận guest cart chưa có owner user; guest-token resolver thuộc P05.
- Factories chỉ tạo cấu trúc để kiểm relationships/casts/DTO, không mô phỏng đầy đủ state machine/ledger/outbox. Không dùng state succeeded/stocked như chứng cứ đã có PaymentService/InventoryService. Không seed tài khoản Admin hoặc dataset địa giới giả vào môi trường phát triển.

## P01.03 — Money, clock, enums và exception contract (31/10/2026)

Yêu cầu: triển khai đúng task P01.03 theo kế hoạch hợp nhất, giữ source cũ/UI gốc, kiểm từng yêu cầu rồi cập nhật nhật ký, **không tạo báo cáo nghiệm thu riêng**. Chưa chuyển sang P01.04/P06.

### Tệp thay đổi

- app/Support: Money, Cost, ShoeSize, CanonicalPayload, BusinessClock, ApiResponse, RequestIdentity và ExceptionRenderer; app/Exceptions/DomainException.php; config/store.php; AppServiceProvider bind clock.
-31 enum miền bổ sung label(), không đổi backing values; ErrorCode mới29 mã dùng chung Blade/AJAX.
- bootstrap/app.php, Http/Middleware/RequestId; hai base StoreResource/StoreResourceCollection và7 base/public Resource chuyển sang kế thừa base chung. Không đổi field whitelist hoặc bỏ owner/admin guards của P01.02.
- Unit MoneyAllocationTest/BusinessClockTest/FingerprintTest, Feature ApiContractTest; pint.json loại bản project giải nén lồng khỏi formatter. README/AGENTS/decisions/traceability/schema cập nhật tài liệu kỹ thuật hiện hữu. Không đổi dependency, migration, UI, routes/web.php hoặc source cũ.
- Xóa hai scaffold enum rỗng không sử dụng app/RoleSlug.php và app/Enums/Enums/ErrorCode.php do generator tạo nhầm namespace; actual App\Enums\RoleSlug/ErrorCode giữ nguyên đúng vị trí.

### Đối chiếu từng yêu cầu

| Yêu cầu | Bằng chứng trong test | Kết quả |
|---|---|---|
| Integer VND, half-up, overflow | Chặn float/bool/null/âm/exponent; boundary PHP_INT_MAX; cộng/nhân/round vượt giới hạn bị từ chối;1 tỷ VND hợp lệ, lớn hơn bị chặn | PASS |
| Largest remainder1 VND/3 unit | Tổng đúng1, unit_no nhỏ nhất nhận dư, đảo thứ tự input không đổi kết quả; weight khác nhau ưu tiên remainder; matrix gồm0/1/2/100/1 tỷ/PHP_INT_MAX và weight sum/product vượt integer64 | PASS |
| Null cap, zero cap, phép nhân lớn | percent half-up trước min; null/0/cap khác nhau; percent100 tại PHP_INT_MAX không tràn intermediate | PASS |
| BCMath WAC scale4/COGS | Cost trả string, công thức/carry half-up chữ số4, existing0/gift0, UINT32_MAX và DECIMAL20,4 max; COGS round tổng line một lần, overflow bị từ chối | PASS |
| Size40.5 không float/parseInt |040.5000→40.5,40.0→40; reject float40.5,40.1,âm/exponent/0/overflow; Resource thực tế trả size_value string40.5 | PASS |
| Clock freeze/exclusive/VN→UTC | Microsecond trước/bằng/sau deadline; nullable coupon window; config OTP/payment/COD/RMA; snapshot đổi7 ngày/bảo hành6 tháng, ngày cuối tháng/năm nhuận/gần nửa đêm; ngày VN thành half-open UTC | PASS |
| Fingerprint cùng dữ liệu | Recursive map key order, enum và UTC instant tương đương cho cùng SHA256; cart/address/method/coupon/list order/type thay đổi làm hash khác; reject float/object/UTF8/depth lỗi | PASS |
| Enum và lỗi dùng chung |29 Domain codes; status/label riêng; success Resource/collection/pagination;401 thực qua auth middleware,419 handler,422 fields,409,429 Retry-After,404/405 Allow,500 debug không lộ nội bộ; Blade error bag/flash và lọc input nhạy cảm | PASS |
| Không hồi quy/giữ nguồn |673 tests/6848 assertions PASS;320 source cũ,32 file protected và304 tệp bản ZIP lồng khớp; HTTP home/health200 | PASS |

### Sự cố và cách xử lý

- Test đầu phát hiện kỳ vọng thứ tự map tie của numeric ID có leading zero chưa đúng quy tắc binary fallback; sửa expected theo toàn thứ tự đã định nghĩa, giữ kiểm đảo input và tổng tiền. Không bỏ test overflow/độ ổn định.
- Resource mới tạo bằng factory có wasRecentlyCreated=true nên Laravel chuẩn trả201. Fixture GET được load lại bằng fresh(), giữ đúng hành vi framework cho POST tạo mới. Fixture nested size dùng đúng cột canonical size_value, không thêm cột size_string vào DB.
- Route login tạm trong test cần refreshNameLookups sau đặt tên; production load route thông thường không cần workaround đó. Test kiểm cả browser redirect và AJAX401 qua middleware auth thật.
- Tính warranty trên UTC có thể sai ngày VN khi giao gần nửa đêm: đã đổi sang cộng tháng tại business timezone rồi trả UTC; test31/10 00:30 và leap-year bảo vệ lỗi này.
- Một lượt test bị ngắt vì MySQL riêng3307 không còn lắng nghe. Dừng đúng tiến trình test đang chờ, khởi động lại đúng isolated datadir/user hiện hữu, chạy lại thành công. Script start-local-services sau khi đưa MySQL lên đã từ chối Mailpit do1025 đang thuộc Mailpit Laragon khác; không chiếm/tắt SMTP đó, task này không gửi mail. PHP server được chạy lại cho kiểm HTTP.
- Formatter ban đầu quét cả bản giải nén lồng cena-store-laravel/cena-store-laravel ngoài phạm vi. Đã chứng minh114 khác biệt chỉ do cùng phiên bản Pint định dạng từ ZIP, hoàn nguyên chính xác và đối chiếu304/304 tệp bằng bytes với ZIP. Không xóa/di chuyển/hợp nhất bản lồng; pint.json ngăn lặp lại. Nguồn Java/Next cũ không bị format.

### Lệnh và kết quả cuối

- Nhóm mới MoneyAllocationTest/BusinessClockTest/FingerprintTest/ApiContractTest đã chạy hẹp trong quá trình triển khai; lượt114 tests/997 assertions PASS trước khi thêm regression HttpResponseException. Lượt cuối `php artisan test --compact tests/Feature/ApiContractTest.php`: **44 tests/669 assertions PASS**. Toàn suite cuối chứa đủ **115 test mới** của P01.03.
- `php artisan test --compact --order-by=random --random-order-seed=20261031`: **673 tests/6848 assertions PASS**,149,265 giây. Bao gồm migration lifecycle reset/rollback/dựng lại schema, metadata MySQL, protection/ownership và các test P00/P01.01/P01.02. Không chạy nhiều test process đồng thời trên schema test.
- PHP `-l` trên **214 file PHP** thuộc app/bootstrap/config/database/routes/scripts/tests: **0 lỗi**. `php vendor/bin/pint --format agent` format toàn project trong phạm vi pint.json, sau đó `--dirty --format agent`: PASS. `git diff --check`: PASS; repo mới chưa commit nên nguồn còn untracked, việc kiểm PHP thực tế dựa thêm full Pint/syntax và suite, không chỉ git diff.
- SHA256 baseline trước task: **320/320 file source Java/Next cũ giữ nguyên**, **32/32 file protected giữ nguyên** (UI/assets/dependencies/migrations/routes). Snapshot specification.md khớp nguyên bản kế hoạch hợp nhất. Bản project giải nén lồng đã khớp bytes **304/304 file** với ZIP, không được đưa vào phần triển khai này.
- HTTP `http://127.0.0.1:8000/` và `/up`: **200**, có X-Request-ID UUID server; home vẫn chứa “Sport, the smart choice.”. Không sửa Blade/CSS/JS/assets và không cần rebuild UI. `php artisan route:list --except-vendor --no-interaction` vẫn chỉ có ứng dụng route home; các route __contract chỉ tồn tại trong process test.
- `php artisan db:show --counts --no-interaction`: MySQL8.4.3, `cena_store`/`cena_app` trên127.0.0.1:3307, **41 bảng**,9 migration,34 bảng miền đều0 row. Session hạ tầng có row do kiểm HTTP; không có seed/giao dịch tiền-kho-ledger trong development.
- Test dùng MySQL thật `cena_store_test` qua user test đã guard; factory dữ liệu test rollback bằng transaction. MigrationLifecycleTest chỉ reset DB test. Source cũ `git status --short` vẫn chỉ có tệp phân tích TXT untracked từ trước task; không sửa file đó. Không commit/push, không chạy GitHub Actions remote và không tạo báo cáo nghiệm thu P01.03 riêng.

### Ranh giới và lưu ý cho task tiếp theo

- P01.03 là helper/contract nền, không phải PricingService/QuoteDTO/checkout/OTP/InventoryService; không có nghiệp vụ ghi tiền/kho/ledger hoặc endpoint giả mới. Không có bảng/ledger trước-sau để minh họa giao dịch chưa triển khai. Store config cần được dùng lại và thực thi trong FormRequest/service ở đúng phase.
- Chi tiết API, tie-break, timezone, fingerprint và lỗi: ADR-009 trong architecture/decisions.md. Caller dùng weight gross, không phân bổ shipping vào unit; Q của WAC là stock bao gồm reserved và loại quarantine. Money hỗ trợ amount không âm; signed ledger delta thuộc service có chủ đích.
- Fingerprint chỉ hash payload đã chuẩn hóa/validated. Future PricingService phải dựng đủ cart version, address snapshot, method, coupon và stable line identity/order; hash không tự cấp quyền hay thay idempotency transaction.
- Error contract/Resource guards không thay route middleware active/role/Policy của P02.419 hiện kiểm bằng TokenMismatchException đi qua handler; chưa tuyên bố đã kiểm toàn bộ form/CSRF ở các chức năng chưa làm. Blade có flash/error bag sẵn; việc hiển thị toast trong màn hình nghiệp vụ dùng lại UI gốc ở phase tương ứng.
- Task tiếp theo: P01.04 Foundation seed/factories/dataset địa chỉ. Không seed admin/demo/dataset giả trong P01.03, không commit/push hoặc tự chạy CI remote.

## Công bố GitHub và chuẩn bị cộng tác (31/10/2026)

Người dùng yêu cầu đưa project lên GitHub, cho phép commit/push và chọn repository công khai để bên khác tự fork/gửi PR. Repo đã tạo: https://github.com/httrung0308/cena-store-laravel . Không mời cộng tác viên write vào main, không tự merge PR bên ngoài.

- Có CONTRIBUTING.md, CODEOWNERS chủ repo, PR template và48 gói hỗ trợ kèm prompt riêng trong docs/collaboration/EXTERNAL_TASKS_AND_PROMPTS.txt; bản TXT đồng nhất được xuất ra Downloads/PHAN_CONG_TASK_CHATGPT_WEB_CENA_STORE.txt.38 gói PREP (chỉ bắt đầu khi EXT dependency đủ),10 gói WAIT_CONTRACT. Đây là subtask hỗ trợ, không thay51 task canonical hoặc tự vượt gate.
-75 tệp TSX/CSS chỉ đọc được copy từ source gốc vào docs/reference/legacy-ui với manifest SHA256; không gồm backend/API/payment implementation. Snapshot tham chiếu không chạy được như app độc lập, không dùng làm contract bảo mật. Gitattributes giữ bytes của đặc tả và reference để không đổi checksum khi checkout Linux.
- .gitignore loại bản project giải nén lồng; không xóa/di chuyển bản đó. Bản source Java/Next cũ chỉ đọc. Preflight:386 tệp Git-eligible, khoảng4,78MB,0 exact-match secret local và0 token/private-key pattern; env/private files/vendor/node_modules/build không nằm tập công bố.
- Không sửa PHP/Blade/CSS/JS runtime hoặc dependencies/schema của baseline673test/6848assertions. CI full đã chạy trên checkout GitHub Linux thành công, xem run thật bên dưới.

### Kết quả công bố đã kiểm chứng

- Push baseline thành công: main tracking origin/main, commit `795f371cd8d7bb90d991edf733e7737c54e457a6`,386 tệp source/docs/reference, repository PUBLIC. Không thay runtime PHP/Blade/CSS/JS của baseline.
- GitHub Actions Linux/MySQL8.4 **SUCCESS**: https://github.com/httrung0308/cena-store-laravel/actions/runs/41180343375 . Job foundation chạy checkout sạch, Composer validate/install/platform, npm ci/build, migrate hai DB, toàn suite và Pint/git diff thành công. Đây là run remote thật, khác với evidence local P00 trước đây.
- Main protection: required status `foundation`, branch up-to-date, ít nhất1 approval và code-owner review `httrung0308`, dismiss review cũ khi đổi commit, resolve conversations, linear history; chặn force-push/delete. Chỉ squash merge, auto-merge tắt. Chủ repo vẫn giữ admin bypass tiêu chuẩn để quản lý baseline; bên ngoài chỉ fork/PR, không được cấp write vào main.
- Staged diff check PASS; frozen specification và75 UI reference có checksum đúng cả trong Git index. Giữ nguyên formatting/line-ending của tài liệu nguồn, reference và terminal log cũ bằng gitattributes hẹp, không sửa byte source để làm sạch whitespace của snapshot.
- Đối chiếu sau publish:320 source cũ và32 tệp UI/dependency/migration protected giữ nguyên SHA256. Homepage và /up vẫn200. Bản project giải nén lồng, env, local credentials, vendor/node_modules/build không có trong Git tree.
- Bản TXT Downloads khớp nội dung bản project;48 task có allowlist/dependency/checks/prompt/fork-PR workflow và phân vai.38 PREP không có nghĩa có thể làm cùng lúc hoặc vượt phase;10 WAIT_CONTRACT chỉ bắt đầu khi API/DTO upstream có thật.
- Đánh giá tiến độ:6/51 task canonical xong,45 còn lại; task tiếp theo vẫn P01.04. Demo tối31/10 hiện chỉ là nền tảng/UI/test, chưa có login/cart/checkout thật; các mốc và ước lượng có điều kiện ghi trong TXT. Không triển khai hoặc tuyên bố hoàn tất P01.04/P02+ trong lần chuẩn bị cộng tác này.

## P01.04 — Core không chồng lấn với bên cộng tác (31/10/2026)

Người dùng yêu cầu tiếp tục, giữ các phần đã giao cho bên kia. Khi bắt đầu chưa có PR mở. Không sửa48 allowlist EXT, không nghiên cứu lại dataset hoặc viết AdministrativeDatasetValidator, không đụng UI/source cũ và không tạo báo cáo nghiệm thu riêng.

### Phần đã triển khai

- RolesSeeder upsert ba role theo slug; CoreFoundationSeeder bao transaction roles + demo identity. Chưa tạo FoundationSeeder/importer đầy đủ hoặc thay DatabaseSeeder vì cần EXT-001/002 được review trước.
- DemoUsersSeeder mặc định tắt; bật trong local/testing bằng config/private password12..72bytes. Ba email .test, hash qua User cast, role lookup slug, pivot direct server attributes giữ guarded, giỏ trống. Replay không đổi profile/hash/metadata; tài khoản locked/deleted/revoked/password đổi hoặc email trùng bị từ chối, rollback batch. Không gửi mail, không seed stock/cost/ledger/outbox.
- config/store.php palette12 key/hex đối chiếu FilterColors/FilterColor và Tailwind3 đã cài; shipping threshold2triệu vàfee30k/40k. Code HCM/version để null chờ nguồn xác minh, không đoán ID/tên tỉnh. Hai env example chỉ thêm flagfalse/secretblank/codesblank; env thật không sửa.
- RoleFactory có administrator/staff/customer; AdministrativeUnitFactory có wardOf liên kết province/version/date. Đây là synthetic fixture, không file địa giới thật.
- Hợp đồng dữ liệu ../collaboration/administrative-data-contract.md; README/AGENTS/ADR cập nhật phân công và giới hạn. File TXT/75 UI references và các tệp của EXT giữ nguyên.

### Kiểm tra và vấn đề đã sửa

- Test hẹp FoundationCoreTest:27tests/219assertions PASS trước format. Bao replay/roleID/FK/timestamps, bcrypt/privateconfig, production/staging/thiếu role/configsai, email collision rollback, không mở lại Admin, không reset password, profile/cart, palette/shipping/factory.
- Negative test ban đầu có thể nhầm exception của prompt xác nhận CLI production với LogicException nghiệp vụ. Đã dùng --force chỉ trong test DB guarded để vào đúng seeder và assert đúng class LogicException; không dựa Mockery exception để báo pass. Fixture được refresh trước so sánh raw attributes, ward sai phải được dựng bằng parent hợp lệ để SQL CHECK không che kiểm tra factory.
- Core seed local chạy2lần với demo flagfalse bằng process env, không ghi env file. Tạo roles thật theo slug, không tạo người dùng/dữ liệu địa giới hoặc giày/tồn/chứng từ giả.
- Full Pint và dirty Pint đã chạy sau test hẹp; suite random và đối chiếu nguồn đều PASS.

### Kết quả kiểm tra core cuối

- `php artisan test --compact --order-by=random --random-order-seed=20261031`: **700tests/7067assertions PASS**,100,587 giây; gồm27test core mới và673test nền/schema/model/helper hiện hữu. MySQL test riêng đã guard, không chạy các process test cùng schema đồng thời.
- PHP syntax7 file PHP thay đổi/mới PASS; full Pint và `--dirty --format agent` PASS. Dependencies/lockfiles/migrations không thay đổi. Không cần build lại UI vì không sửa frontend.
- `db:seed --class=CoreFoundationSeeder --no-interaction` chạy local2lần, cưỡng chế demo flagfalse chỉ trong process. `db:show --counts`:41 bảng,roles3,users0/user_roles0/carts0/administrative_units0, toàn kho/đơn/thu-hoàn/ledger/outbox0. Không in/lưu demo password, env thật giữ nguyên.
- SHA256:320/320 file source cũ và32/32 UI/dependency/migration protected giữ nguyên; frozen specification/reference/48-task TXT không sửa. So14 file thay đổi/mới với allowlist của cả48EXT: **0 xung đột đường dẫn**. Homepage và /up200.
- Nhánh làm việc `codex/p01-04-foundation-core`, không sửa main trực tiếp. Core sẵn sàng review độc lập nhưng toàn task P01.04 vẫn chưa PASS. Draft PR thật: https://github.com/httrung0308/cena-store-laravel/pull/1 . CI GitHub Linux cho commit code2fcdcd7 PASS cả push và pull_request: https://github.com/httrung0308/cena-store-laravel/actions/runs/41182926397 và https://github.com/httrung0308/cena-store-laravel/actions/runs/41182932890 . Đây là run remote thật; PR vẫn draft, không tự merge hoặc báo P01.04 hoàn thành.

### Phần chưa hoàn tất

EXT-001 cần giao proposal dataset/manifest/sources/codes thật; EXT-002 cần validator đã chốt API và test; owner review, tạo adapter/importer/canonical files, nối HCM code/version và FoundationSeeder/DatabaseSeeder rồi chạy full seed hai lần. P01.04 hiện WIP, số task canonical hoàn tất vẫn6/51; P01.05 chưa bắt đầu. EXT-003/004 và các task còn lại vẫn do bên kia nhận theo dependency.

## Chuẩn bị ledger/outbox độc lập trong lúc chờ EXT (31/10/2026)

Yêu cầu mới: tiếp tục các phần độc lập chưa giao bên kia, giữ source cũ/UI, kiểm đủ và cập nhật nhật ký, không báo cáo riêng. Chọn phần persistence của P01.05 để PREP trên nhánh codex/p01-05-ledger-outbox-primitives, xếp sau core PR#1. Đây không phải hoàn tất P01.05 hoặc nới gate; P01.04 vẫn chờ EXT-001/002.

### Tệp và hành vi đã chuẩn bị

- SalesLedgerService.postUnique: insert/replay trong transaction, signed integer VND, whitelist source/delta, shape7types và source chain. Key giống/intent khác bị conflict; sửa sai phải entry bù. Không thay validation delivered/paid/refund budget/Policy/locks của các service tương lai.
- OutboxService.enqueue cùng transaction, aggregate ID thật và alias đóng; canonical dedup, không reset SENT/retry metadata. JSON scalar/array strict, chặn known secrets/float/object/UTF8/depth/size lỗi; producer tương lai vẫn phải safe DTO whitelist.
- OutboxLease readonly, registry/transport interface, safe failure enum và ProcessOutbox. FOR UPDATE claim, lease120s/timeout60s, max10attempt, retry backoff cap3600s, ACK/fail token bảo vệ lease mới, exact-expiry reclaim, poison/attempt exhaustion terminal. Chưa đăng ký transport thật, producer, scheduler hoặc route; thiếu handler thành FAILED, không success giả.
- OperationKey validator và registry singleton trong AppServiceProvider; không đổi schema/dependencies/enum backing cũ/Resources/UI. Không tạo InventoryService, không giả gateway/SMTP hoặc làm phần EXT-045.
- Test AtomicInfrastructure/OutboxDelivery/AtomicConcurrency + committed fixture base và guarded worker fixture. Test dùng hai process MySQL riêng với barrier sau missing-key read, không chỉ mô phỏng request tuần tự. Fixture xóa đúng namespace/IDs của test, không reset schema development hoặc chạy suite song song.

### Kiểm tra theo yêu cầu

- Nhóm mới43tests/197assertions PASS trước format: canonical replay/different intent/signed bounds/shape/source/rollback; dedup không hồi sinh sự kiện; strict payload; after-commit; lease/retry/exhaustion/poison; handler thiếu/fail không báo gửi; registry replacement; serialization chỉ eventID.5test race dùng SQL/process thật, có cả hai intent khác nhau và lease claimant.
- Hai test đầu đọc model instance cũ sau update worker nên expected retry/attempt sai; đã refresh fixture đúng thời điểm và chạy lại cả nhóm. Không nới guard, lease rule hoặc lỗi nghiệp vụ để làm test qua.
- Full Pint và dirty Pint đã chạy trước full random suite; suite và đối chiếu nguồn đã PASS.

### Kết quả kiểm tra cuối của gói PREP

- `php artisan test --compact tests/Feature/AtomicInfrastructureTest.php tests/Feature/OutboxDeliveryTest.php tests/Feature/AtomicConcurrencyTest.php`: **43tests/197assertions PASS**. Barrier concurrency ép cả hai connection đã đọc key chưa tồn tại trước insert; test khác intent có đúng1winner/1IDEMPOTENCY_MISMATCH. Hai worker claim chỉ1lease sống. Đây là race SQL thật, không phải mock query builder hoặc HTTP tuần tự.
- `php artisan test --compact --order-by=random --random-order-seed=20261031`: **743tests/7272assertions PASS**,147,483giây; gồm700test cũ/core và43test mới. Các committed fixtures cleanup theo namespace/IDs và child process guard đều hoạt động cùng migration lifecycle/random order.
- Full Pint và `--dirty --format agent`: PASS sau format. PHP syntax **15 file PHP** mới/thay đổi PASS. Không thay dependencies/lockfiles/migrations/Resources hoặc backing values của enum hiện hữu.
- Đối chiếu **19 file** thay đổi/mới với allowlist48EXT:0xungđột. SHA256:320file source cũ và32file UI/dependency/migration protected giữ nguyên; spec/reference/TXTtask không sửa. Home/up200, không đổi giao diện hoặc route.
- Development MySQL vẫn41bảng;roles3/users0/order-payment-refund-ledger-outbox-inventory movement/import đều0. Không chạy service mới trên database development, không ghi dữ liệu tiền/kho hoặc gửi thông báo thật. Test-only fixtures không được coi kịch bản bán hàng đã hoàn thành.
- Nhánh `codex/p01-05-ledger-outbox-primitives` dựa trên `codex/p01-04-foundation-core`; PR PREP sẽ target nhánh core để diff không trộn phần đã có trong PR#1. Không merge main hoặc PR bên ngoài. Draft PR thật: https://github.com/httrung0308/cena-store-laravel/pull/2 . GitHub Linux/MySQL8.4 CI cho code commit c88f1b2 **PASS** cả push và pull_request: https://github.com/httrung0308/cena-store-laravel/actions/runs/41185512309 và https://github.com/httrung0308/cena-store-laravel/actions/runs/41185516682 . Log PR run xác nhận743tests/7272assertions. Đây là run remote thật; gói vẫn PREP, không đóng phase gate.

### Gate và phần giữ lại

Số task canonical hoàn thành vẫn6/51. P01.04 chờ dataset/validator/import/shipping review; P01.05 chưa complete vì chưa có InventoryService/stock movement primitives và chưa tích hợp qua prerequisite đầy đủ. Không có sales/delivery/payment/RMA/notification thật được kích hoạt; không đưa code mới lên main hoặc tự merge PR ngoài. Giữ toàn bộ phạm vi48EXT cho bên kia.

## InventoryService độc lập của P01.05 trong lúc chờ EXT (31/10/2026)

Yêu cầu người dùng: tiếp tục phần InventoryService độc lập, giữ source cũ/giao diện, kiểm đầy đủ và cập nhật nhật ký, không tạo báo cáo riêng. Nhánh codex/p01-05-inventory-primitives xếp trên ledger/outbox PR#2; không lấy code từ PR PREP#3 của bên kia, không sửa48 allowlist EXT hoặc main. Trạng thái dưới đây cập nhật phần “chưa có InventoryService” của bản ghi trước; toàn phase vẫn chưa complete.

### Phần đã triển khai và ranh giới

- InventoryChange readonly chứa server intent; InventoryService có reserve/release/issue/receive/receiveQuarantine/restock/writeOff/adjust/applyBatch và assertConsistent. Caller transaction bắt buộc, savepoint toàn batch, variant locks ID tăng và current-read trong REPEATABLE READ. Không có caller-provided delta hoặc API sửa/xóa movement. Giữ schema, guarded models, enums, routes và UI.
- Mỗi movement ghi delta/before/after ba bucket, WAC trước/sau, basis và actor snapshot. Quantity strict int/digit string dương ≤2147483647 theo INT signed của delta; bucket ≤4294967295. Giữ reserved<=stock, không xuất hàng chưa reserve hoặc điều chỉnh lấy phần đang giữ.
- Sở hữu reserve theo đúng Order/RMA/reference/variant; cap held+cumulative issued theo dòng đơn hoặc một target RMA. Quarantine theo receipt thực của từng ShipmentReturnItem/RMA, mỗi nguồn một đôi; không lấy hàng cách ly của chứng từ khác, không nhận/restock/writeoff lần hai bằng key mới. WarrantyRepair không cộng kho. Đổi size kiểm source/target cùng color, khác variant; hai hướng movement chung transaction/batch.
- Import kiểm DRAFT/item/SKU/qty/cost, WAC bằng Cost/BCMath4 có reserve và loại quarantine. Receipt cách ly giữ basis nguồn; restock/writeoff dùng basis đã ghi, không dùng snapshot sửa về sau. Stock0 giữ last cost, lần nhập mới tính từ hàng mới. Outgoing snapshot WAC tại lúc xuất, không sửa giá vốn movement cũ.
- Replay exact key/intent trả nguyên row kể cả source đã POSTED/actor renamed-inactive/catalog deleted/WAC đổi. Different key case, action, variant, source, quantity, input basis, actor hoặc reason trả IDEMPOTENCY_MISMATCH. Unique race rollback savepoint trước đọc lại; deadlock để caller retry toàn SQL transaction.
- Manual cần current active Admin, reason≥20 và reference biên bản; không cấp lại quyền hoặc bypass Policy. MANUAL_ADJUST_OUT ghi INVENTORY_LOSS cùng transaction theo basis hiện hành, order null, movement FK, VND làm tròn tổng một lần. Overflow ledger rollback cả kho/movement. Manual QUARANTINE_WRITEOFF chỉ nguồn thực, chặn RMA mở; workflow disposition bình thường chịu trách nhiệm expense khi chưa ghi, tránh double cost.
- Reconcile đọc history từ0, kiểm delta shape/cost chain/ownership/balances; mismatch STATE_CONFLICT chặn ghi mới, không tự sửa. assertConsistent chỉ kiểm. Bản PREP đọc toàn history SKU dưới lock, cần đo tải/checkpoint và scheduled report ở P04 trước production.
- Caller tương lai phải authorize và prelock actor/role/roots/items/units theo canonical, kiểm supplier/cost0 approval/order state/payment/deadline/inspection và đổi status/unit snapshots; service không tự làm các workflow đó. Không bật producer/endpoint/scheduler/transport, không ghi stock giả vào development DB.

### Kiểm tra và sửa lỗi tìm được

- Nhóm InventoryService/InventoryConcurrency: **76tests/271assertions PASS** trên MySQL thật. Bao16action, strict input/cost/key/evidence/actor guards, INT32/UINT32 bounds, reserve ownership/caps, quarantine ownership/duplicate disposition, WAC/replay/snapshot, soft-delete historical release, reconciliation drift và atomic rollback cả stock/movement/loss/outbox.
- InventoryConcurrency có6race và1transaction-guard test. Hai PHP worker/MySQL connection riêng, parent giữ SKU lock rồi chờ cả hai tới locking-read trước nhả; worker thiết lập snapshot cũ trước service để kiểm current-read. Cases: last pair, same key/same intent, different quantity, same key/different SKU, reversed batch order, release-vs-dispatch. Chỉ một winner khi cần, không oversell hoặc nửa batch/outbox dư; connection IDs được assert khác nhau. Guard DB và process env riêng, join/stop trước cleanup theo IDs, không reset/mutate schema development.
- Lần test đầu phát hiện dùng nhầm name thay vì full_name của User, expected error chưa đúng ở duplicate RMA reservation, và raw factory chưa refresh trước so sánh rollback. Đã sửa code snapshot sang full_name, sửa fixture/expected theo domain rule thực và chạy lại nhóm; không nới ràng buộc nghiệp vụ để test qua.
- Audit ban đầu so TXT working CRLF với Git blob LF nên báo khác byte dù git không có diff. Đã đối chiếu đúng working-byte baseline có từ trước: reference/spec/TXT đều giữ nguyên. Không đổi line-ending/tài liệu EXT để làm audit qua.
- Khi tiếp tục phiên làm việc, MySQL3307 và web8000 đã dừng, khiến lượt rerun chờ kết nối. Đã dừng đúng hai PHP process test do lượt này tạo, khởi động lại MySQL bằng my.ini/datadir Cena hiện hữu và web bằng start-local.ps1, kiểm migration status trước chạy lại suite. Không initialize/provision/reset credential hoặc sửa env/data development; lỗi chờ hạ tầng không được ghi là test PASS.
- Toàn suite random trên MySQL: **819tests/7543assertions PASS**, gồm743test cũ và76test Inventory; lượt xác minh sau khởi động runtime182,187giây. Full Pint và dirty Pint PASS, PHP syntax5file mới PASS. Không thay dependencies/lockfiles/migrations hoặc build lại frontend không đổi.
- SHA256 source cũ320/320 và UI/dependency/migration protected32/32 giữ nguyên; frozen spec SHA2564736603bf64d9ac2794e48cca1c1bbdeb18a5e0346d60ec509de8ff87942ad2e. Reference/spec/TXT79tệp working baseline không đổi;48EXT không xung đột đường dẫn. Homepage và /up200, slogan giao diện gốc còn nguyên.
- Development DB vẫn41bảng,roles3/users0; product/import/inventory-movement/order/payment/refund/ledger/outbox đều0. Không chạy InventoryService trên development DB, không gửi thông báo thật; before/after/delta và rollback được kiểm trong database test riêng.
- Draft PR thực tế: https://github.com/httrung0308/cena-store-laravel/pull/4 , base codex/p01-05-ledger-outbox-primitives; code commit fb6c9ace4fa153a0a28bbf3a3841b409cdf28e32. CI GitHub Linux/MySQL8.4 **SUCCESS** cả push https://github.com/httrung0308/cena-store-laravel/actions/runs/41198584097 và pull_request https://github.com/httrung0308/cena-store-laravel/actions/runs/41198594131 . Pipeline gồm clean install/platform/build, migrations hai schema, toàn suite và Pint/clean diff. PR vẫn draft, không merge main hoặc PR EXT; remote evidence là run thật, không suy ra từ local.

### Gate sau gói Inventory

Inventory primitives đã được triển khai và kiểm độc lập. Số task canonical hoàn tất vẫn6/51: P01.04 chờ dataset/validator/import/shipping review; toàn P01.05 chờ prerequisite và tích hợp workflow đúng gate. Không coi tests primitive là checkout/delivery/payment/RMA đã hoàn thiện, không merge PR owner/external tự động. Nhật ký/README/ADR/AGENTS hiện có được cập nhật; không tạo báo cáo nghiệm thu riêng.

## Đợt 7 chức năng — PR-0 nền chung (02/11/2026, Claude)

Leader giao 7 chức năng tối thiểu (tỉnh/phường, đăng nhập, sổ địa chỉ, quản lý sản phẩm, nhập kho, trang sản phẩm, giỏ hàng). Chia hai luồng Claude/Codex theo `docs/collaboration/PLAN_7_CHUC_NANG.md`; PR-0 tạo các file dùng chung để hai bên không sửa trùng file.

- Route tách theo khu vực: `routes/storefront.php` (Codex), `routes/admin.php` (Codex, tự có prefix `/admin`, tên `admin.`, middleware `auth` + `role:admin,staff`), `routes/account.php` và `routes/cart.php` (Claude). `/shoes` (`shoes.index`) tạm hiện trang "Sắp ra mắt".
- Middleware `role` (`EnsureUserHasRole`): chỉ user còn active, chưa xóa và đang giữ một trong các vai trò được nêu; đọc lại vai trò mỗi request nên thu hồi quyền có hiệu lực ngay request sau.
- Layout `layouts/store` (navbar + panels + footer gốc, tắt hiệu ứng mở đầu của trang chủ) và `layouts/admin` (menu trái tự hiện mục khi route tồn tại). Navbar, menu mobile và nút Explore trỏ về `/shoes?gender=men|women|kids|unisex`.
- `app.js` đăng ký một lần 8 component Alpine đã có của EXT. Factory `User` thêm `staff()`, `customer()`, `withRole()`.
- Test `SharedFoundationTest`: khách vào `/admin` bị 401, customer/không vai trò/bị khóa/đã xóa bị 403, admin và staff vào được, thu hồi vai trò có hiệu lực ngay, `role:admin` chặn staff, escape tên và tiêu đề, liên kết theo giới tính.

## Đợt 7 chức năng — PR-C1 đăng ký, đăng nhập, đăng xuất (02/11/2026, Claude)

Theo WF-01/P02.01 ở mức tối thiểu của đợt này (chưa làm OTP, quản trị user).

- `AuthService::register`: một transaction tạo user active (mật khẩu băm), gán vai trò `customer` theo slug, tạo giỏ rỗng; thiếu role thì báo lỗi cấu hình và rollback; trùng email do race chuyển thành lỗi 422. Không nhận role/is_active/auth_version từ request.
- `RegisterRequest`: họ tên 2–100, email chuẩn hóa chữ thường, SĐT tùy chọn, mật khẩu ≥8 và **≤72 byte** (giới hạn của bcrypt, giống `DemoUsersSeeder`; đặc tả ghi 128 nhưng bcrypt bỏ phần sau byte 72 nên chặn rõ thay vì cắt ngầm). Thông báo lỗi tiếng Việt.
- Đăng nhập: chỉ tài khoản active, chưa xóa; cùng một thông báo cho sai mật khẩu và tài khoản khóa; khóa 60 giây sau 5 lần sai theo email + IP; đổi session ID sau khi đăng nhập; lưu `auth_version` vào phiên; admin/staff vào `/admin`, khách quay lại trang định vào.
- `EnsureUserIsActive` (nhóm web): mỗi request kiểm user còn active và `auth_version` trong phiên khớp DB, sai thì đăng xuất, hủy phiên, đổi CSRF token, chuyển về trang đăng nhập (AJAX nhận 401). Phiên từ cookie "ghi nhớ" nhận version hiện tại.
- Đăng xuất chỉ bằng POST có CSRF. Trang `/login`, `/register` dùng layout và form EXT-012/013/014 (thêm ô SĐT). Navbar: khách thấy liên kết đăng nhập; đã đăng nhập thấy panel tên, email, (Trang quản trị nếu là admin/staff), Đăng xuất.
- Test `AuthFlowTest` 14 ca: tạo user + role + giỏ, chặn chèn role, lỗi nhập, race email trùng, thiếu role rollback, đổi session ID, chuyển hướng theo vai trò, sai mật khẩu/khóa/đã xóa, chặn lần thứ 6, khóa hoặc đổi version chặn phiên cũ, đăng xuất, trang guest, navbar escape tên. `ApiContractTest` dùng route `login` thật thay route tạm.
- Đã thử trên trình duyệt: đăng ký báo lỗi nhập lại mật khẩu, đăng ký thành công tự đăng nhập, panel tài khoản, đăng xuất, đăng nhập sai báo lỗi. Ghi chú: ô checkbox "Ghi nhớ đăng nhập" của form EXT bị CSS gốc ẩn, chỉ thấy nhãn (bấm nhãn vẫn chọn được).

## Đợt 7 chức năng — PR-C2 dữ liệu tỉnh/phường (02/11/2026, Claude)

Nguồn người dùng chọn: Địa Chỉ Việt (diachiviet.com, CC BY 4.0), dựng 30/10/2026, theo văn bản có hiệu lực đến 20/09/2026. Đây là P01.04 phần dataset (thay EXT-001/002).

- Tải `tinh.json`, `xa.json`, `giay-phep.txt` ngày 02/11/2026 ra thư mục ngoài repo; SHA256 lưu trong `manifest.source_sha256`. Kiểm cấu trúc: 34 tỉnh/thành, 3.321 xã/phường/đặc khu, số xã từng tỉnh khớp `so_don_vi_cap_xa`, không trùng mã, không mồ côi, mã 79 = Thành phố Hồ Chí Minh.
- Khác tài liệu EXT-001: `xa.json` **không có cột `ten_day_du`**; cột `ten` đã gồm loại đơn vị ("Phường …", "Xã …", "Đặc khu …") nên dùng `ten` cho xã, `ten_day_du` cho tỉnh. Không sửa tên/mã nào.
- `scripts/build-administrative-dataset.php` tái tạo `database/data/administrative_units.json` + `manifest.json` (contract v1). Giá trị chốt: `dataset_version=dvc-20260920`, `effective_as_of=2026-09-20` (văn bản mới nhất nguồn đã áp dụng), `hcm_province_code=79`. `.gitattributes` giữ nguyên bytes `database/data/*.json` để checksum không đổi khi checkout.
- `App\Support\AdministrativeDataset` kiểm manifest (đúng 15 field + `source_sha256` tùy chọn, kiểu, URL HTTPS, ngày, đếm), checksum file, từng bản ghi (đúng 8 field, mã chuỗi 2/5 chữ số giữ số 0 đầu, chỉ PROVINCE/WARD, parent tồn tại, cùng version/ngày, `is_active === true`). Chỉ kiểm cấu trúc, không chứng minh đúng pháp lý.
- `AdministrativeUnitSeeder` thêm bản ghi thiếu theo `(dataset_version, code)`, gán `parent_id`; bản ghi đã có mà khác dữ liệu thì dừng, không tự sửa. `FoundationSeeder` = Roles → AdministrativeUnit → DemoUsers; `DatabaseSeeder` gọi nó. `store.shipping.hcm_province_code` / `dataset_version` mặc định lấy từ manifest (env vẫn ghi đè được).
- API tra cứu cho ô chọn địa chỉ: `GET /api/locations/provinces`, `GET /api/locations/provinces/{code}/wards` — chỉ snapshot hiện hành, chỉ `code/name/parent_code/dataset_version`, mã sai dạng hoặc không có → 404, giới hạn 120 request/phút.
- Test `AdministrativeDatasetTest`: snapshot thật đủ 34/3.355, checksum bị sửa bị chặn, 15 kiểu dữ liệu sai bị chặn, seeder idempotent + giữ parent, không tự sửa bản ghi đã nhập, seed toàn bộ 2 lần không trùng, API đúng version và giữ số 0 đầu.
- DB dev: `db:seed --class=FoundationSeeder` chạy 2 lần → 34 tỉnh, 3.321 xã, 1 version, 3 role.

## Đợt 7 chức năng — PR-X0 schema test Codex (02/11/2026, Codex)

- Ngoại lệ hai file guard/test đã được người dùng cho phép; kế hoạch trên main yêu cầu tách PR-X0 trước X1. Chỉ thêm cặp chính xác cena_store_test_codex/cena_test_codex; các cặp cena_store_test/cena_test và cena_ci hiện hữu giữ nguyên.
- Chặn tài khoản dùng sai schema, root/app user, SQLite và config cache. Không nới whitelist tên gần giống hoặc dùng wildcard database. Không thay phpunit.xml, CI, schema/model/migration hoặc credential trong Git.
- Schema/user Codex được provision riêng ngoài Git, chỉ grant schema Codex; PHPUnit config riêng ngoài Git vì config chung force tên cena_store_test. Không chạy test trên schema Claude.
- Unit guard:13 tests/10 assertions; kiểm positive pair và negative cross-pair/cache. Pint và diff check được chạy trước push; chờ CI xanh trước squash merge. Các phần X1 giữ ở worktree riêng tới khi nền X0 vào main.

## Đợt 7 chức năng — PR-X1 danh mục/NCC (02/11/2026, Codex)

- Nhánh feat/x1-categories-suppliers, worktree cena-codex-x1, base f137ff39ee6f07038b78a3a4dbcdc24dbefda34c; đã fetch/merge origin/main trước kiểm tra. Chỉ phạm vi Codex và hai ngoại lệ guard/test được người dùng cho phép; không thay source cũ, layouts/navigation, models/enums/migrations/Resou…2788 tokens truncated…g dependency/lockfile/model/enum/migration mới.
- StockImportService: tạo/sửa DRAFT, UUID command_key, từ chối trùng SKU và payload tự gán status/tổng/snapshot/actor; server chuẩn hóa Money/Cost, kiểm INT32 từng movement/UINT32 tổng lượng và overflow tiền. Nháp chỉ có chứng từ/items; không ghi tồn/cost/movement/ledger.
- Ghi sổ: kiểm quyền hiện hành và khóa actor/roles → root → NCC/items → toàn bộ variants tăng dần, gọi InventoryService::receive trong cùng transaction SQL-only retry3. Mỗi dòng dùng import:{id}:post:{variant}; root POSTED/posted_by/UTC clock cuối cùng. Replay POSTED trả metadata gốc, không cộng tồn lại, kể cả NCC đã ngừng. Một SKU lỗi/drift rollback tất cả balance/movement/status. Giá vốn bình quân qua InventoryService, không tự tính hoặc sửa service nền.
- NCC phải còn active lúc lưu và ghi sổ; SKU inactive được restock, soft-deleted bị chặn. Nhập tặng cost0 chỉ Admin và lý do >=20 ký tự, kiểm lại người ghi sổ. Giá nhập từng dòng phục vụ Staff/Admin; không lộ WAC hoặc aggregate tài chính khác.
- Hủy chỉ DRAFT, lý do bắt buộc; ghi UTC/user/reason vào note vì không thêm cột; không thay kho. POSTED/CANCELLED không sửa/hard-delete; sai phiếu posted cần quy trình điều chỉnh ngoài đợt này. Create-key replay so intent hiện tại; sau sửa/hủy nháp có thể trả IDEMPOTENCY_MISMATCH thay vì tự phục hồi intent cũ.
- StockImportDemoSeeder chỉ local/testing + opt-in + Admin có sẵn: 4 sản phẩm Cena minh họa ×2 màu ×3 size; hai phiếu POSTED thực qua service. Chạy lại giữ 4 products/24variants/2imports/48movements, stock7 mỗi SKU, tổng168, WAC357142.8571, không sales ledger. Không seed bằng gán stock; ảnh lấy asset Cena cũ sang media public.
- Test X3 hẹp25ca/138assertions PASS trên schema/user cena_store_test_codex/cena_test_codex: đúng/sai/kiểm quyền, duplicate/replay, WAC, overflow, note dài, SKU cuối lỗi rollback, final immutable, demo replay. Race hai Staff khác nhau dùng hai connection MySQL thực và barrier khóa root: cùng ghi một phiếu chỉ có2movements/stock5; joins/stops worker trước ID-scoped cleanup. Không chạy suite khác cùng schema.
- Ngoại lệ InfrastructureConnectionTest được người dùng duyệt: chấp nhận đúng cặp shared-test/cena_test hoặc cena_ci và Codex-test/cena_test_codex; vẫn giữ MySQL8.4/UTC và test account không đọc development. Không nới guard hoặc sửa phpunit.xml/CI; cấu hình PHPUnit Codex ngoài Git.
- Browser desktop1440x900/mobile360x800 trên server riêng8003: tạo nháp → ghi sổ SKU từ7 lên10; hủy nháp không thêm movement (tổng49 sau một dòng posted); note/reason render thật và khóa thao tác sau final. Sửa title mã dài làm tràn mobile ngay trong view Codex; table cuộn riêng, body350<=viewport360; không lỗi JS. npm build, route:list8routes và Pint PASS. Chỉ reset fixture Codex sau đóng tab/server; không dữ liệu development/Claude. Không báo cáo nghiệm thu riêng.
- Kiểm tra tích hợp cuối: full suite MySQL Codex randomized seed20261102 PASS998/998 tests, 8518assertions; đã chạy sau khi sửa assertion hạ tầng được duyệt. X2 CI trước đó PASS973tests/8380assertions: https://github.com/httrung0308/cena-store-laravel/actions/runs/41389247297 .

## Hướng dẫn team — đơn giản hóa cài local và PR (02/11/2026, Codex)

- Theo yêu cầu mới, làm riêng worktree cena-codex-docs-onboarding / nhánh docs/simplify-team-workflow từ main840b4e5; không sửa worktree/code X4 Claude, source cũ, giao diện, database hoặc dịch vụ đang chạy.
- Thêm scripts/setup-project.ps1: một lệnh gọi lại các script runtime/services/env/install/build/migrate/seed roles+địa giới/storage/server hiện có; truyền được đường dẫn MySQL/Mailpit. Kiểm binary trước, dừng ngay nếu child command fail. Không chạy key:generate/migrate:fresh/test reset; không tạo mặc định tài khoản/sản phẩm demo, không thay logic provisioning/seed sẵn có.
- README viết lại theo thứ tự cài lần đầu → hai lệnh mở lại → demo tùy chọn → bốn bước PR; bỏ trạng thái tính năng cũ gây hiểu nhầm. CONTRIBUTING và PR template rút gọn tương ứng; collaborator không cần fork, worktree chỉ cho công việc song song. Giữ phạm vi task, kiểm thử liên quan và điều kiện review/CI xanh trên commit cuối trước squash merge; workflow CI không sửa, không bỏ guard/check.
- Kiểm tra thực tế: PowerShell parser PASS; chạy script với child scripts giả lập trong fixture ngoài repo PASS luồng thành công +11ca lỗi dừng ngay tại mỗi child step, đường dẫn có khoảng trắng, giữ env; thêm2ca chạy lại bỏ storage link đã có và thiếu binary không thực thi child nào đều PASS. Markdown fences/local links và tham chiếu script/seeder/test đúng; git diff --check và audit phạm vi PASS.
- Không chạy lại cài/provision thật trên máy dùng chung để tránh ảnh hưởng môi trường Claude; kiểm tra trên là orchestration giả lập, không phải chứng nhận cài mới end-to-end trên Windows khác. CI sẽ chạy suite ứng dụng đầy đủ trên MySQL disposable của GitHub; không chạy PHPUnit local trên schema dùng chung. Không báo cáo nghiệm thu riêng.
## Đợt 7 chức năng — PR-X4 trang sản phẩm /shoes và /shoes/{slug} (02/11/2026, Claude tiếp quản WIP của Codex)

- Worktree cena-codex-x4, nhánh feat/x4-catalog, base main 840b4e56d405389181b9e47a6e9471163fc2d1c7 (đã có X3). Tiếp nhận code WIP có sẵn trong worktree, không apply lại patch. Chỉ sửa file X4 của cột Codex trong plan và đúng hai assertion /shoes của SharedFoundationTest (ngoại lệ đã được người dùng duyệt). Không đổi routes/web.php, app.js, layout/navigation, model/enum/migration, Money/InventoryService, cart Claude hay dependency.
- `CatalogQueryService` (read-only): public = product/category/category cha active và chưa xóa mềm, màu active có thumbnail và variant active. Mọi điều kiện màu + size + khoảng giá + khuyến mãi + còn hàng áp trên **cùng một variant** bằng một sub-query tương quan; giá "Từ" = MIN(price_override → promotion_price → base_price) của đúng tập variant khớp. Sale chỉ khi giá hiệu lực < giá gốc (override cao hơn gốc không gạch giá). Search tên/style-color escape `% _ !`. Sort newest/price/name có tie-break id; 9 sản phẩm/trang, giữ query string. Eager load + một truy vấn ID variant khớp cho cả trang, không N+1.
- Trang chi tiết: chỉ sản phẩm public (khác → 404); `style_color` phải thuộc đúng sản phẩm. Màu mặc định là màu đầu còn hàng, hết toàn bộ thì màu đầu. DTO chỉ gồm trường công khai (không giá vốn, stock/reserved, ledger, đường dẫn lưu trữ); còn bán = stock − reserved. Giá hiển thị dạng chuỗi do server định dạng (`price_label`) nên giá vượt Number.MAX_SAFE_INTEGER không bị JS làm tròn.
- Giao diện (component EXT có sẵn, giữ thiết kế Cena): card có swatch hover đổi ảnh, link `?style_color=`, nhãn Hết hàng; trang chi tiết gallery theo màu (trước/sau, bàn phím), đổi màu cập nhật album và bỏ size cũ, size hết hàng mờ và khóa, size guide dialog. Ảnh lỗi chuyển sang `legacy/img/men.jpg` cả sau khi đổi màu/hover. Nút "Thêm vào giỏ" có `data-add-to-cart` + `data-variant-id` của SKU đang chọn, dùng handler C4; khóa khi chưa chọn size, hết hàng hoặc giỏ đang xử lý.
- Lỗi tìm thấy khi review WIP và đã sửa: Blade `@endif@endforeach` dính một dòng làm trang chi tiết lỗi 500; nút giỏ đọc `$store.cart.busy` (không tồn tại) → `isBusy()`; ảnh fallback dùng `.once` nên không chạy lại sau khi đổi màu; select màu có lựa chọn rỗng; test so attributes chưa refresh.
- Test: `CatalogTest` 14 phương thức = 38 lượt chạy (data provider: 11 ca ẩn sản phẩm, 15 input sai) + SharedFoundation/ProductCard/CatalogFilter: 52 tests/205 assertions PASS. Full suite MySQL `cena_store_test`: **1036/1036 tests, 8658 assertions PASS**. `npm run build` PASS, Pint PASS.
- Browser QA (server tạm 127.0.0.1:8010, dữ liệu demo qua StockImportDemoSeeder chỉ trong `cena_store_test`, xóa bằng migrate:fresh sau QA; không động DB dev/Codex): desktop 1280px hai cột, mobile 360px không tràn ngang ở /shoes và trang chi tiết; swatch hover và ảnh fallback; đổi màu bỏ size; màu hết hàng toàn bộ hiện size khóa và thông báo; chọn Black 40.5 (giá riêng 1.390.000 ₫) → thêm giỏ → khung giỏ "Black · Size 40.5 · 1.390.000 ₫", tồn vẫn 7/reserved 0.
- Giới hạn: pane trình duyệt bị ẩn nên x-show chỉ cập nhật khi render (đã xác nhận sau khi chụp màn hình); album demo mỗi màu chỉ 1 ảnh nên nút trước/sau được ẩn, logic chuyển ảnh dùng module gallery EXT đã có test. Chưa có checkout/đánh giá (ngoài 7 chức năng).

## Giao diện giống bản demo tạm + đổi thương hiệu Nike (02/11/2026, Claude)

- Người dùng yêu cầu toàn bộ giao diện Laravel giống bản demo tạm (Next.js, nhánh local demo/basic-flows), chỉ khác công nghệ. Đã chốt: đổi thương hiệu sang Nike như bản demo; chỉ đổi giao diện, giữ đúng 7 chức năng (không thêm checkout/đơn hàng/quản lý người dùng); được sửa cả phần admin và trang sản phẩm của Codex.
- Thương hiệu: navbar, title, trang chủ, layout admin hiển thị Nike. Footer mới 4 cột như bản demo (liên hệ, Mua sắm, Tài khoản, Chính sách) kèm dòng "bản demo đồ án, không phải website chính thức của Nike". Tên sản phẩm trong StockImportDemoSeeder và tên hiển thị tài khoản demo đổi sang Nike; giữ slug/SKU/email để không tạo bản trùng.
- Đăng nhập/đăng ký: toàn màn hình, video nền (`public/legacy/video/register.mp4`, poster lấy từ source gốc), không navbar/footer, logo Nike về trang chủ, ô nhập trong suốt, nút trắng, nút hiện/ẩn mật khẩu, tiêu đề "Chào mừng trở lại." / "Gia nhập cùng Nike.". Giữ nguyên tên field, CSRF, throttle và redirect (Admin/Staff vào /admin).
- Admin: nằm trong khung cửa hàng (navbar + footer) như bản demo, nhãn nhỏ + tiêu đề lớn, thanh mô-đun dạng pill, bảng/thẻ bo tròn, badge trạng thái. Bảng điều khiển có thẻ thông tin tài khoản, thao tác nhanh, trạng thái hệ thống, lưới mô-đun. Bảng sản phẩm thêm ảnh, giá khuyến mãi, số size, tổng tồn (eager load + withCount/withSum, không N+1). Form danh mục/NCC/sản phẩm/phiếu nhập giữ nguyên tên field.
- Tài khoản: thêm trang `/account` (`account.index`) giống bản demo (lời chào, thông tin cá nhân, địa chỉ mặc định, nút quản lý địa chỉ); sổ địa chỉ dùng thẻ và nút màu như bản demo. Không có đơn hàng/đổi mật khẩu vì nằm ngoài 7 chức năng.
- Sửa lỗi: CSS gốc ẩn mọi checkbox; thêm class `native-checkbox` cho checkbox ở admin/đăng nhập (trước đó "Đang bán", "Ghi nhớ đăng nhập" không nhìn thấy).
- Test: thêm `StorefrontRedesignTest` (6 ca); cập nhật assertion footer/title cũ trong `SharedFoundationTest`, `FoundationSmokeTest`. Full suite MySQL `cena_store_test`: **1042/1042 tests, 8697 assertions PASS**. `npm run build`, Pint PASS.
- Browser QA (server tạm, DB dev có dữ liệu demo): đăng nhập Admin → /admin; danh sách/sửa sản phẩm, phiếu nhập, danh mục, trang chủ + footer, /account, form địa chỉ ở desktop; mobile 360px không tràn ngang ở đăng ký, admin (bảng cuộn trong khung), /account, chi tiết phiếu.
- Giới hạn: nhãn admin dùng tiếng Việt (bản demo dùng tiếng Anh) cho thống nhất với phần còn lại; chưa có "Quên mật khẩu", "Mua ngay"/checkout, đơn hàng, quản lý người dùng như bản demo vì ngoài phạm vi.

## Bổ sung chức năng như bản demo — PR-A đặt hàng COD (02/11/2026, Claude)

- Người dùng yêu cầu làm thêm các chức năng bản demo có mà Laravel còn thiếu, tuần tự từng PR: (A) đặt hàng COD, (B) quản lý đơn khách + admin, (C) đổi mật khẩu + quản lý người dùng, (D) quên mật khẩu, (E) đánh giá.
- `CheckoutService` theo WF-10/WF-11 (chưa có coupon): báo giá chỉ đọc + fingerprint; đặt hàng trong một transaction khóa user → đơn cùng checkout_key → giỏ → biến thể tăng dần, tính lại giá dưới khóa; cùng key trả lại đúng đơn, khác payload → IDEMPOTENCY_MISMATCH; giá/phí đổi → PRICE_CHANGED; thiếu hàng/ngừng bán → báo tên + size, rollback toàn bộ. Tạo order PENDING/UNPAID, items + units từng đôi (snapshot tên/màu/size/SKU/ảnh/chính sách), payment COD PENDING (hạn 24 giờ), history ORDER_CREATED, outbox ORDER_CREATED; giữ hàng bằng `InventoryService::applyBatch` (ORDER_RESERVED, không trừ stock); xóa dòng giỏ và tăng version.
- Phí ship theo chính sách: miễn phí từ 2.000.000 ₫, TP.HCM 30.000 ₫ (theo mã tỉnh cấu hình), tỉnh khác 40.000 ₫. Chỉ dùng địa chỉ của chính khách và thuộc bộ dữ liệu hiện hành.
- Giao diện như bản demo: trang `/checkout` hai cột (chọn địa chỉ, ghi chú, tóm tắt đơn, COD); thêm địa chỉ từ trang thanh toán rồi quay lại; nút "Mua ngay" ở trang sản phẩm (thêm giỏ xong chuyển tới thanh toán, chỉ nhận đường dẫn nội bộ); nút "Đặt hàng" trong khung giỏ; trang "Đặt hàng thành công"; `/account/orders` và chi tiết đơn (chỉ chủ đơn, đơn người khác 404); thẻ "Các đơn hàng gần đây" ở `/account`; link đơn hàng ở menu tài khoản và footer.
- Test: `CheckoutTest` 8 ca (tạo đơn + giữ hàng + xóa giỏ, idempotency, đổi giá, thiếu một SKU rollback, phí ship, địa chỉ/đơn của người khác, nút đặt hàng); JS thêm 1 ca "Mua ngay" (Node 29/29). Full suite MySQL `cena_store_test`: **1050/1050 tests, 8756 assertions PASS**. Build, Pint PASS.
- Browser (DB dev): khách demo chọn size 40.5 → "Mua ngay" → trang thanh toán → thêm địa chỉ TP.HCM → quay lại (ship 30.000 ₫, tổng 1.530.000 ₫) → đặt hàng → trang thành công; DB: stock 7, reserved 1, đơn PENDING/UNPAID, 1 movement ORDER_RESERVED.
- Sự cố trong lúc QA: junction `public/storage` của worktree tạm trỏ vào storage của `cena-demo`; khi worktree tạm bị xóa, thư mục ảnh demo bị xóa theo. Đã khôi phục 8 ảnh từ ảnh gốc `legacy/img` theo đúng tên trong `product_images`; từ nay worktree tạm chỉ dùng bản chép ảnh, không dùng junction.
- Giới hạn: chưa có coupon, VietQR/VNPay; tự hủy đơn COD quá hạn 24 giờ cần scheduler (chưa có).

## Bổ sung chức năng như bản demo — PR-B quản lý đơn hàng (02/11/2026, Claude)

- `OrderWorkflowService` theo WF-15/WF-16 (COD): mỗi chuyển trạng thái khóa đơn, so trạng thái người dùng đã thấy (`expected_status`), ghi lịch sử một lần theo command key (bấm hai lần không lặp lịch sử/kho). Cạnh hợp lệ: PENDING→CONFIRMED→PACKING→ON_DELIVERY→DELIVERED/FAILED; hủy từ PENDING/CONFIRMED/PACKING (khách chỉ hủy đơn PENDING của chính mình).
- Giao vận chuyển: `InventoryService` ORDER_DISPATCHED (trừ stock + reserved đúng một lần), chốt `cost_unit_snapshot`/COGS dòng và chia COGS từng đôi bằng `Money::allocate`; tạo shipment IN_TRANSIT (đơn vị + mã vận đơn).
- Giao thành công: bắt buộc mã phiếu thu COD (không dùng lại cho đơn khác), payment SUCCEEDED/APPLIED, order PAID + paid_amount, shipment DELIVERED, đôi giày OWNED_BY_CUSTOMER với hạn đổi/bảo hành từ snapshot; ghi sổ SALE_RECOGNIZED từng đôi + SHIPPING_RECOGNIZED qua `SalesLedgerService`.
- Hủy: ORDER_RELEASED nhả hàng giữ, đôi giày CANCELLED, COD attempt FAILED, lưu lý do. Giao thất bại: shipment RETURNING, không cộng lại kho (nhận hàng hoàn là quy trình riêng, chưa làm).
- Giao diện như bản demo: `/admin/orders` (tìm mã/tên/SĐT, lọc trạng thái, bảng bo tròn, badge), chi tiết đơn (sản phẩm, từng đôi, tổng tiền, đã thu, lịch sử, thao tác hợp lệ theo trạng thái, vận chuyển/thanh toán); mục "Đơn hàng" trong menu admin và bảng điều khiển; form tự hủy đơn ở trang chi tiết đơn của khách.
- Test: `OrderWorkflowTest` 6 ca (luồng đủ đến giao thành công + COGS bất biến khi nhập giá mới + ledger, hủy nhả hàng, khách chỉ hủy PENDING của mình, cạnh cấm + double click, phiếu thu trùng + giao thất bại, phân quyền + escape). Full suite MySQL `cena_store_test`: **1056/1056 tests, 8821 assertions PASS**. Build, Pint PASS.
- Browser (DB dev, tài khoản nhân viên demo): đơn của khách demo → Xác nhận → Đóng gói → Giao (GHN) → Giao thành công với phiếu thu; DB: stock 7→6, reserved 0, DELIVERED/PAID, 2 dòng sổ doanh thu.
- Giới hạn: chưa có nhận hàng hoàn/thất lạc, hoàn tiền, scheduler tự hủy COD quá 24 giờ, VietQR/VNPay.

## Bổ sung chức năng như bản demo — PR-C đổi mật khẩu + quản lý người dùng (02/11/2026, Claude)

- `UserAccessService` (WF-03): mọi thay đổi quyền khóa hàng vai trò admin làm mutex trước, nên hai request đồng thời không thể khóa/bỏ quyền Admin cuối cùng đang hoạt động; Admin không tự khóa hay tự bỏ quyền Admin; khóa/mở khóa và đổi vai trò tăng `auth_version` (người bị đổi bị đăng xuất ở request kế tiếp nhờ `EnsureUserIsActive`); vai trò thay theo tập, cần ít nhất một, vai trò chưa khởi tạo bị báo lỗi thay vì bỏ qua; ghi `assigned_by`.
- Đổi mật khẩu cần mật khẩu hiện tại, mật khẩu mới theo quy tắc đăng ký và khác mật khẩu cũ; đổi xong tăng `auth_version`, đăng xuất và bắt đăng nhập lại. Hồ sơ chỉ sửa họ tên/SĐT (chuẩn hóa +84), không nhận email/vai trò/trạng thái.
- Giao diện như bản demo: `/account/settings` (thông tin cá nhân + đổi mật khẩu), nút "Sửa thông tin / Đổi mật khẩu" ở `/account`; `/admin/users` (chỉ Admin: tìm tên/email/SĐT, lọc vai trò/trạng thái) và chi tiết người dùng (sửa thông tin, vai trò, khóa/mở khóa); mục "Người dùng" chỉ hiện với Admin, Staff thấy mô-đun bị làm mờ "(chỉ Admin)".
- Test: `UserManagementTest` 6 ca (phân quyền + tìm/lọc + escape, khóa đăng xuất phiên và mở khóa, chặn tự khóa/tự bỏ Admin + Admin cuối, vai trò theo tập, hồ sơ không đổi email/quyền, đổi mật khẩu sai/đúng + đăng nhập lại). Full suite MySQL `cena_store_test`: **1062/1062 tests, 8871 assertions PASS**. Build, Pint PASS.
- Browser (DB dev): Staff vào `/admin/users` bị 403; Admin thấy danh sách người dùng và trang cài đặt tài khoản.
- Giới hạn: chưa có ảnh đại diện; chưa có nhật ký audit riêng cho thay đổi quyền (ngoài `assigned_by`/`auth_version`).

## Bổ sung chức năng như bản demo — PR-D quên mật khẩu bằng OTP (02/11/2026, Claude)

- `PasswordResetService` theo WF-02/DB-04: một challenge mỗi người dùng; OTP 6 số từ `random_int`, chỉ lưu hash; hạn 15 phút; chờ 60 giây giữa hai lần gửi; tối đa 5 lần sai (đếm và commit ngay, lần thứ 6 không kiểm hash nữa); challenge gắn với trình duyệt đã yêu cầu bằng token ngẫu nhiên (DB lưu hash). Email không tồn tại nhận cùng thông điệp và token giả, không gửi mail. Đặt lại dùng challenge đúng một lần, đổi mật khẩu, tăng `auth_version`, đổi remember_token, bắt đăng nhập lại. Gửi lại mã làm mã cũ hết hiệu lực; đổi mật khẩu ở trang tài khoản cũng vô hiệu mã đang chờ.
- Mail gửi đồng bộ ngay sau commit, không đưa mã vào URL/log; lỗi gửi mail chỉ ghi log chung, trang vẫn nói "đã tiếp nhận". Giới hạn tần suất theo email + IP.
- Giao diện như bản demo (nền video): link "Quên mật khẩu?" ở trang đăng nhập, `/forgot-password`, `/reset-password` (nhập mã + gửi lại, rồi mật khẩu mới).
- Test: `PasswordResetTest` 5 ca (luồng đủ + dùng lại bị chặn, email lạ cùng thông điệp không gửi mail, 5 lần sai khóa cả mã đúng, gắn trình duyệt + chờ gửi lại + hết hạn, đổi mật khẩu vô hiệu mã). Full suite MySQL `cena_store_test`: **1067/1067 tests, 8926 assertions PASS**. Build, Pint PASS.
- Browser (DB dev + Mailpit local): tài khoản thử đăng ký → Quên mật khẩu → mã tới Mailpit → xác nhận → đặt mật khẩu mới → về đăng nhập với thông báo.

## Sửa ảnh xem trước đi theo con trỏ + tách tài khoản Admin khỏi mua sắm (02/11/2026, Codex bắt đầu, Claude hoàn thiện)

- Nguyên nhân lỗi ảnh đi theo con trỏ: khung xem trước dùng `:style` dạng chuỗi nên mỗi lần chuột di chuyển Alpine ghi đè cả thuộc tính `style`, xóa `display:none` của `x-show`; khung (ảnh rỗng/lỗi) vì thế luôn bám theo chuột. Đã đổi sang `:style` dạng object (chỉ cập nhật `transform`). Giữ phần Codex đã làm: ảnh chỉ hiện sau khi tải xong, lỗi thì tắt; tắt khi click, cuộn, Esc, mất focus, rời trang; thêm tắt khi chuột rời cụm Men/Women/Kids/Unisex; ẩn trên mobile.
- Admin không dùng để mua sắm (Codex): middleware `EnsureNonAdminShopping` chặn Admin ở giỏ, thanh toán, đơn cá nhân, hủy đơn, đánh giá (403, không tạo dữ liệu); ẩn icon giỏ, "Đơn hàng của tôi", sổ địa chỉ trong menu/footer/`/account`; `/account` của Admin chỉ còn thông tin cá nhân + lối vào quản lý đơn của khách. Đơn cũ vẫn giữ nguyên và xem được ở `/admin/orders`. Staff và khách giữ nguyên hành vi.
- Claude bổ sung: trang sản phẩm thay nút "Thêm vào giỏ"/"Mua ngay" bằng thông báo khi là Admin; đăng nhập Admin không gộp giỏ khách vãng lai và không quay về trang mua sắm (vào `/admin` thay vì 403).
- Test: `NavigationAccountSeparationTest` 20 lượt (Codex 19 + 1 ca mới) và assertion `:style` dạng object. Full suite MySQL `cena_store_test`: **1090/1090 tests, 9125 assertions PASS**. Build, Pint PASS.
- Browser (DB dev, 1280px và 360px): rê "Men" ảnh hiện cạnh chuột, rời chuột ảnh tắt và không bám theo khi tiếp tục di chuyển; Admin đăng nhập từ `/checkout` vào `/admin`, không có icon giỏ, trang sản phẩm hiện thông báo thay nút mua, `/account` không còn đơn/địa chỉ; không tràn ngang trên mobile.

## Nhiệm vụ Đợt 01: quy trình đơn, trả trước mô phỏng, ảnh giao hàng và coupon (05/11/2026, Codex)

- Gắn các yêu cầu trong `Nhom1_Dot-01.docx` vào schema hiện tại: staff xác nhận đơn PENDING và chuyển thẳng sang PACKING; CONFIRMED cũ vẫn có thể đóng gói; nhãn trạng thái hiển thị theo bốn bước. Các lệnh đều qua `OrderWorkflowService`, giữ kiểm tra trạng thái và lịch sử.
- Checkout hỗ trợ COD hoặc PREPAID_DEMO, thanh toán thành công/thất bại và thử lại theo số tiền snapshot; chỉ chủ đơn được thao tác. Giao COD yêu cầu ảnh JPEG/PNG/WebP tối đa 5 MB, lưu private với tên do storage sinh, SHA-256 duy nhất; trang quản trị phục vụ ảnh có kiểm tra quyền. Đơn trả trước được giao không cần ảnh. Hủy đơn đã thu tiền tạo yêu cầu hoàn mô phỏng; Admin duyệt trạng thái và số hoàn bị giới hạn bởi khoản đã thu. Ghi sổ doanh thu vẫn chạy khi giao thành công.
- Coupon Admin tạo/sửa/tắt mã phần trăm, số tiền cố định hoặc miễn phí vận chuyển. Checkout khóa hàng coupon khi đặt đơn và giữ quota bằng redemption RESERVED; giao thành công tiêu thụ quota, hủy/giao thất bại giải phóng. Snapshot sau giảm được dùng xuyên suốt thanh toán và hoàn tiền.
- Bổ sung migration CHECK cho PREPAID_DEMO và bảng `shipment_delivery_proofs`, model/factory, schema/model contract fixtures, tài liệu schema, giao diện, routes và feature tests cho workflow, prepaid/refund, coupon, phân quyền và schema. Không sửa lockfile, không commit hoặc push.
- Kiểm tra cú pháp bằng PHP 8.2 `php -l` cho các file PHP đã đổi: đạt; `composer validate --no-check-publish`: hợp lệ; `git diff --check`: đạt. Chưa chạy được PHPUnit: checkout không có `vendor/autoload.php`, máy chỉ có PHP 8.2 trong khi dự án yêu cầu PHP 8.4 và Docker Engine không chạy. `npm run build` cũng chưa chạy do chưa có `node_modules` (không tìm thấy Vite). Vì vậy chưa xác nhận runtime/integration; cần môi trường PHP 8.4, MySQL test và dependencies của dự án để chạy bộ test sau.

- Cập nhật thử demo (05/11/2026): đã cài dependencies vào `vendor` (bỏ qua kiểm tra platform chỉ để chẩn đoán; không sửa `composer.lock`). PHPUnit không khởi động được vì PHP hiện tại là 8.2, trong khi Symfony 8.1 cần cú pháp property hooks PHP 8.4 (`Request.php:117`). XAMPP MySQL chưa chạy nên `mysqladmin ping` thất bại. Đã thử mở Docker Desktop và khởi động `com.docker.service`; engine vẫn không lên, Windows báo không thể mở service. Do đó chưa có test case nào được thực thi. Cấu hình PHPUnit ép database là `cena_store_test`; không chuyển sang SQLite và không chạm database phát triển.

- Trạng thái sau khi người dùng bật service (05/11/2026): PowerShell phía người dùng xác nhận `com.docker.service` là `Running`. Docker daemon vẫn chưa kết nối; kiểm tra môi trường cho thấy WSL không được cài (`wsl --status` báo cần `wsl --install`). Chưa cài WSL hoặc yêu cầu reboot vì đó là thay đổi thành phần Windows ngoài workspace.

- Kiểm tra lại sau reboot (06/11/2026): Windows đã cài WSL/VirtualMachinePlatform, nhưng `docker desktop start` không lên Linux Engine; WSL báo WSL 2 không thể chạy vì virtualization chưa bật cho máy hiện tại. `docker version` vẫn không có Server. Chưa chạy test. Cần bật VT-x/Intel Virtualization hoặc AMD-V/SVM trong UEFI; nếu Windows chạy trong máy ảo thì host cần bật nested virtualization.

- Kiểm tra tập trung UI/UX (06/11/2026): checkout, trang thanh toán mô phỏng, chi tiết đơn và trang coupon dùng lại hệ thống Blade/Tailwind, component `x-admin.field`, `admin-card`, `btn-pill` và các layout đang có. `npm ci` cần bỏ qua engine strict vì repo yêu cầu Node 22/npm 10 nhưng máy có Node 24/npm 11; `npm run build` hoàn tất. Đây chỉ xác nhận asset frontend build, chưa render browser hoặc chạy tương tác Laravel do PHP 8.4/MySQL/Docker Engine chưa sẵn sàng. HEAD cục bộ vẫn trùng `origin/main` trước thay đổi; các thay đổi của nhiệm vụ chỉ tồn tại local, không push.

- Xác nhận lại theo yêu cầu đảm bảo UI/chức năng (08/11/2026): branch local `main` vẫn tại `93c4e83`, đúng với `origin/main` trước thay đổi; các màn hình checkout/order/payment/coupon dùng layout, Tailwind và admin components có sẵn. `npm run build` đã PASS ở lần kiểm tra 06/11 (Node 24/npm 11 được cài bỏ qua engine strict). Chưa thể xác nhận UI render/flow runtime: Docker Engine không có Server và WSL hiện trả `E_ACCESSDENIED`; runtime có thể gọi chỉ PHP 8.2.12 và XAMPP MariaDB 10.4.32, trong khi Laravel stack yêu cầu PHP 8.4 và test contract yêu cầu MySQL. Không push/commit.

## Hoàn thiện danh sách sản phẩm quản trị, bộ lọc và trang Tài khoản của tôi (14/11/2026)

- **Thống nhất dùng Đối tượng trên danh sách sản phẩm quản trị (`admin.products.index`):** bỏ bộ lọc `Danh mục` (`categories[]`) trùng lặp Nam/Nữ/Trẻ em/Unisex với bộ lọc `Đối tượng` (`genders[]`); chuyển cột `Danh mục` trong bảng desktop và nhãn tương ứng trên thẻ mobile thành **Đối tượng**, lấy nhãn từ `$product->category?->gender_group?->label() ?? '—'`. Giữ nguyên cấu trúc bảng `categories`, quan hệ Eloquent và bộ lọc `Kiểu dáng` (`silhouettes[]`), `Mục đích` (`activities[]`) riêng biệt.
- **Thiết kế lại bố cục bộ lọc (`resources/views/admin/products/index.blade.php`, `resources/js/components/admin-product-filters.js`):** tách thanh tìm kiếm thành hàng riêng toàn chiều ngang để đọc/sửa từ khóa thoải mái; hàng 2 dàn 3 bộ lọc phân loại sản phẩm (`Đối tượng`, `Kiểu dáng`, `Mục đích`) dạng nút chọn với khung xổ xuống rộng đúng bằng ô mở (`left-0 right-0 w-full max-w-full`, không tràn màn hình trên mobile), từng mục đã chọn hiển thị dấu kiểm rõ ràng (`bg-accent/10` + ô kiểm `✓` màu `bg-accent`), bấm lại bỏ chọn ngay, không thêm bộ đếm thừa ngoài bộ đếm của từng bộ chọn; hàng 3 đặt nhóm lọc quản lý (`Trạng thái bán`, `Tồn kho`, `Nổi bật`) xuống 1 hàng riêng trong khay viền nét đứt theo thiết kế Phương án B (hiển thị trực tiếp không qua nút đóng/mở `Lọc quản lý`), giúp phân biệt trực quan giữa phân loại sản phẩm và trạng thái quản lý; hàng 4 hiển thị chip điều kiện đang chọn kèm nút gỡ từng điều kiện (`×`) và `Xóa tất cả` (chỉ xuất hiện khi đang lọc). Bảo toàn tìm kiếm trong dropdown, AJAX, đồng bộ URL (`pushState`), `Back/Forward` (`popstate` đọc cả tham số mảng `name[]` và chỉ mục `name[0]`), phân trang và cập nhật danh sách khi bật/tắt trạng thái bán lúc đang lọc.
- **Điều hướng cửa hàng & Tài khoản của tôi (`layouts/admin.blade.php`, `User::compactDisplayName()`, `ProfileController`, `admin.profile.show`):** bỏ liên kết `Xem cửa hàng khách` ở cuối sidebar (desktop + mobile drawer); đổi `Xem Storefront` trên thanh trên cùng thành **Xem cửa hàng** (`route('home')`); gom khu vực tài khoản trên thanh trên cùng thành khối 2 dòng (Avatar chữ cái + Dòng 1 tên hiển thị + Dòng 2 `Vai trò · Tài khoản của tôi`) trỏ tới `GET /admin/profile` (`admin.profile.show`). Với tên dài, `User::compactDisplayName()` lấy trọn vẹn các chữ từ cuối lên (bottom-up, giữ nguyên tên gọi chính và tên đệm liền kề, không cắt chữ lưng chừng bằng `...`), đồng thời giữ họ tên đầy đủ ở `title`/`aria-label` và trang `admin.profile.show`.
- **Kiểm tra & Xác nhận:** `vendor/bin/pint --dirty` PASS; `npm run build` PASS; PHPUnit (`AdminProductFilterTest`, `UxPolishAndTitleEncodingTest`, `ProductManagementTest` trên `cena_store_test`): **40/40 tests, 277 assertions PASS**. Kiểm thử trình duyệt (Edge headless CDP trên `cena_store_test`, Desktop 1440×900 Light/Dark Mode & Mobile 390×844): xác nhận kết hợp nhiều bộ lọc, dấu kiểm mục đã chọn trong dropdown, độ rộng dropdown khớp nút mở (353px/353px trên desktop, 328px/328px trên mobile), gỡ từng chip, `Xóa tất cả`, `Back/Forward`, chuyển trạng thái khi đang lọc, rút gọn tên từ cuối lên và truy cập `Tài khoản của tôi` với cả Admin và Staff.

## Chuẩn hóa CRUD quản trị toàn diện (14/11/2026)

- **Thành phần dùng chung (`resources/views/components/admin/search-form.blade.php`, `resources/views/components/ui/dialog.blade.php`, `resources/js/components/admin-ajax-list.js`):** nâng cấp thanh tìm kiếm hỗ trợ xóa từ khóa nhanh, tự phát hiện mọi query lọc đang bật, chip điều kiện đang lọc (`#admin-filter-chips`), `Xóa tất cả`, và `adminAjaxList()` đồng bộ URL/`pushState`/`popstate`/phân trang qua AJAX; nâng cấp `<x-ui.dialog>` dùng semantic tokens (`bg-surface-card text-main border-border`) hỗ trợ Dark Mode.
- **Nhóm 1 — Danh sách & Bộ lọc (`categories`, `classifications`, `suppliers`, `coupons`, `banners`, `articles`, `dashboard`):** đồng bộ thanh lọc AJAX, bổ sung bộ lọc `gender_group`/`active` (Danh mục), `active` (Nhà cung cấp), `type`/`active` (Coupon), `status`/`sort` (Bài viết); đưa nút chuyển trạng thái dạng pill về cột Trạng thái tách khỏi nút Sửa; bổ sung route/service hai chiều `Hiện bài` (`PATCH articles/{article}/publish`) & `Ẩn bài` (`PATCH articles/{article}/hide`) hỗ trợ AJAX JSON cho Admin, bỏ emoji `👍`/`👎`, thay `confirm()` bằng `<x-ui.dialog>`, thêm thẻ mobile (`md:hidden`) và bổ sung thẻ `Phân loại`, `Banner trang chủ`, `Tài khoản của tôi` trên `dashboard`.
- **Nhóm 2 — Phiếu nhập kho & Báo cáo (`stock-imports`, `reports`):** thêm lọc Nhà cung cấp + thẻ mobile ở danh sách phiếu nhập; thay `<select>` biến thể bằng combobox tìm kiếm SKU trực tiếp, cảnh báo đỏ ngay tại dòng trùng `variant_id` (không tự cộng dồn), ô nhập đơn giá VND có dấu chấm hàng nghìn; truyền `?variant_id=` từ hộp thoại sắp hết hàng trên Báo cáo sang form Tạo phiếu nhập; gắn `<x-ui.dialog>` xác nhận trước `Ghi sổ nhập kho` và `Hủy phiếu nháp`.
- **Nhóm 3 — Đơn hàng, Người dùng & Đánh giá (`orders`, `users`, `reviews`):** thêm lọc `payment_status` ở danh sách đơn, sửa lỗi `<dl>` lồng đúp và `text-red-700` ở `orders/show`, gắn `<x-ui.dialog>` cho `Báo giao thất bại`, `Hủy đơn hàng`, `Duyệt hoàn tiền` và `Khóa tài khoản`; bổ sung tìm kiếm + lọc số sao (`rating`) và gửi `Lưu phản hồi` qua AJAX tại chỗ kèm toast trên `reviews/index`.
- **Nhóm 4 — Form tạo/sửa (`categories`, `classifications`, `suppliers`, `coupons`, `banners`, `articles`):** áp dụng xem trước slug thu gọn + nút `Tùy chỉnh slug` (tự mở khi có lỗi, khóa khi sửa bản ghi cũ) cho Danh mục/Phân loại; tự sinh mã nội bộ duy nhất (`SUP-XXXX`) khi tạo mới Nhà cung cấp nếu bỏ trống mã trong vùng tùy chỉnh và giữ nguyên mã đã lưu khi đổi tên; chuẩn hóa chữ in hoa `code` cho Coupon (`CouponRequest`) không sinh mã ngẫu nhiên; sửa token màu lỗi & kiểm tra dung lượng ảnh client-side trên Banner; bỏ emoji `🔗` trên form Bài viết.
- **Kiểm tra & Xác nhận:** `npm run build` PASS; `vendor/bin/pint --dirty` PASS; PHPUnit 16 file kiểm thử quản trị (bao gồm `AdminCrudStandardizationTest` mới trên `cena_store_test`): **137/137 tests, 1037 assertions PASS**; kiểm thử trình duyệt Edge headless CDP trên bản cuối xác nhận hoạt động mượt trên Desktop (Light/Dark Mode) và Mobile `390×844` không tràn ngang.

## Đồng bộ focus ô tìm kiếm, tìm kiếm bài viết quản trị, tạo Staff/Admin và khảo sát Tổng quan & Báo cáo (15/11/2026)

- **Focus ô tìm kiếm danh sách quản trị (`resources/views/components/admin/search-form.blade.php`):** bỏ khung `focus-within:ring-2` bọc ngoài `<input>` (nguyên nhân gây khung chữ nhật thừa bên trong ô bo tròn khi focus bàn phím do kết hợp rule `focus-visible:ring-2 focus-visible:ring-offset-2` trong `app.css`), đưa `rounded-xl border focus:ring-2 focus:ring-accent focus:border-accent focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-0` trực tiếp lên `<input>` kèm icon kính lúp `pointer-events-none` đồng nhất với `admin/products/index.blade.php`.
- **Tìm kiếm bài viết quản trị (`ArticleController::index()`, `resources/views/admin/articles/index.blade.php`):** bổ sung lọc `search` theo `title` (escape wildcard `LIKE ... ESCAPE '!'`), kết hợp đồng thời `status`, `sort`, chip từ khóa trong `#admin-filter-chips`, nút xóa từ khóa `×`, trạng thái rỗng theo ngữ cảnh và giữ AJAX/URL/Back-Forward/`withQueryString()`. Không sửa tìm kiếm bài viết phía khách hoặc bộ lọc sản phẩm đã duyệt.
- **Tạo tài khoản Staff / Admin (`routes/admin.php`, `CreatePrivilegedUserRequest`, `UserAccessService::createPrivilegedUser()`, `UserController::create/store`, `admin/users/create.blade.php`, `admin/users/index.blade.php`):** chỉ Admin mới truy cập và thực hiện được (chặn Staff/Customer ở cả UI và middleware/Request), tái sử dụng rule họ tên/SĐT/mật khẩu hiện có, chuẩn hóa email chữ thường, báo trùng email, transaction tạo `User` + `UserRole` nguyên tử không tạo dữ liệu dở dang, chống gửi lặp ở form và tuyệt đối không đổi phiên đăng nhập hay tự đăng nhập bằng tài khoản mới.
- **Khảo sát Tổng quan & Báo cáo (`.ai/OVERVIEW-REPORTS-UX-REVIEW.md`, chưa sửa code):** tổng hợp phân tích độc lập từ 2 subagent research (`0568564c...`, `a79c84cd...`) cùng đối chiếu trình duyệt, phân định rõ Tổng quan (Actionable Triage hiện tại) vs Báo cáo (phân tích theo kỳ), chỉ ra lỗi nhãn trùng `OrderStatus::Confirmed` / `Packing` (`"Đang đóng gói"`), reset âm thầm `>= 366` ngày, query `ProductVariant` trong Blade view và các đề xuất trình bày chờ người dùng duyệt.
- **Kiểm tra & Xác nhận:** `npm run build` PASS; `vendor/bin/pint --dirty --format agent` PASS; PHPUnit (`AdminSearchAndUserCreationTest` + `AdminCrudStandardizationTest` trên `cena_store_test`): **9/9 tests, 80 assertions PASS**; kiểm thử trình duyệt Edge headless CDP trên bản cuối xác nhận focus ô tìm kiếm 1 viền bo tròn sạch, tìm kiếm bài viết AJAX + trạng thái rỗng và tạo Staff giữ nguyên phiên Admin.

## Thiết kế lại Tổng quan & Báo cáo, rà soát Form quản trị và Sidebar thu gọn theo nhóm cha (15/11/2026)

- **Nhãn trạng thái đơn hàng (`app/Enums/OrderStatus.php`):** đổi `OrderStatus::Confirmed->label()` thành `"Đã xác nhận"` tách biệt với `Packing` (`"Đang đóng gói"`), bảo toàn enum key và backing value `CONFIRMED`/`PACKING`.
- **Thiết kế lại Tổng quan (`DashboardController`, `resources/views/admin/dashboard.blade.php`, `routes/admin.php`):** thay thế 3 card tĩnh và 12 thẻ mô-đun lặp sidebar bằng bàn làm việc tác nghiệp (*Operational Triage Hub*) với 6 bộ đếm có điều kiện truy vấn khớp 100% với danh sách đích (`orders?status=PENDING|CONFIRMED|PACKING`, `stock-imports?status=DRAFT`, `products?stock=out_of_stock`, `orders?payment_status=REFUND_PENDING` cho Admin hoặc `reviews` cho Staff), hàng đợi đơn ưu tiên, phiếu nhập nháp, biến thể cần nhập kho kèm nút `+ Nhập kho` (`?variant_id=`) và đánh giá chưa phản hồi; ghi rõ *"Số liệu tại thời điểm tải trang"*, không dùng từ *"Realtime"*.
- **Thiết kế lại Báo cáo (`ReportController`, `ReportService`, `resources/views/admin/reports/index.blade.php`, `resources/js/components/report-charts.js`):** ưu tiên KPI ở màn hình đầu kèm thanh điều khiển kỳ báo cáo và điều hướng nhảy nhanh (`#vung-tai-chinh`, `#vung-van-hanh`, `#vung-ton-kho`, `#vung-bai-viet`); tách bạch khối *Nhịp vận hành theo kỳ đã chọn* và *Tồn kho hiện tại (tại thời điểm tải trang)*; bổ sung `expense` trực tiếp từ `ReportService::daily()` vào chuỗi biểu đồ và cột `Chi phí khác` trong bảng đối chiếu `<details>`; đưa `variant_id` vào `ReportService::inventory()` (bỏ query Eloquent khỏi Blade); báo lỗi validation tiếng Việt và giữ `old('from')`/`old('to')` khi khoảng ngày không hợp lệ hoặc vượt 366 ngày (không âm thầm reset về 30 ngày); kiểm tra phát sinh thực tế (`$hasFinanceActivity`) khi doanh thu = 0 nhưng có chi phí/lỗ; thêm chọn ô ngày cảm ứng/bàn phím cho heatmap, lắng nghe `prefers-color-scheme: dark` cho chế độ `system` và giữ `charts` trong biến closure tránh lỗi Alpine Proxy khi đổi theme.
- **Rà soát & tinh chỉnh các Form quản trị (`users/create`, `users/show`, `categories/form`, `classifications/form`, `suppliers/form`, `coupons/form`, `banners/form`, `inventory/imports/form`):** rút gọn các câu giải thích cơ chế kỹ thuật nội bộ không phục vụ nhập liệu, diễn đạt tự nhiên và chính xác giới hạn mật khẩu 72 byte khi mã hóa, cân đối lại lưới và tỷ lệ cột theo luồng nhập thực tế, bảo toàn các trường tự sinh thu gọn, combobox tìm kiếm và chống gửi lặp.
- **Sidebar quản trị thu gọn theo nhóm cha (`resources/views/layouts/admin.blade.php`, `resources/js/app.js`):** chuyển tiêu đề 4 nhóm thành `<button type="button">` hỗ trợ đóng/mở bằng chuột và bàn phím (`aria-expanded`, `aria-controls`), lưu trạng thái vào `localStorage['cena-admin-sidebar-groups']` và luôn ép mở nhóm chứa trang đang truy cập (kể cả trang tạo/sửa/chi tiết); đồng bộ nhãn vai trò `Admin` / `Staff` trên thanh mobile (`md:hidden`).
- **Kiểm tra & Xác nhận:** `npm run build` PASS; `vendor/bin/pint --dirty --format agent` PASS; PHPUnit 17 file kiểm thử liên quan (gồm `AdminOverviewReportsSidebarRedesignTest` mới trên `cena_store_test`): **110/110 tests, 909 assertions PASS**; kiểm thử trình duyệt Edge headless CDP trên `cena_store_test` (Desktop 1440×900 Light/Dark/System & Mobile 390×844, Admin & Staff, `consoleErrors: []`) và đã dọn sạch dữ liệu/profile kiểm thử tạm.

## Tinh gọn Tổng quan, Báo cáo theo Tab & Tooltip dùng chung (15/11/2026)

- **Thành phần Tooltip dùng chung (`resources/views/components/admin/tooltip.blade.php`, `resources/js/app.js`):** bổ sung `<x-admin.tooltip>` với Alpine `adminTooltip` hỗ trợ hover/focus trên desktop, chạm mở/đóng và đóng khi bấm ngoài/Escape trên mobile, tự đo `getBoundingClientRect()` để giữ tooltip nằm gọn trong viewport không tràn màn hình hoặc che nút thao tác.
- **Tổng quan (`DashboardController`, `resources/views/admin/dashboard.blade.php`):** coi phản hồi đánh giá là tùy chọn — bỏ thẻ đếm *Đánh giá chưa phản hồi* của Staff, bỏ `unrepliedReviewsCount` khỏi `$totalActionableCount` và bỏ khối hàng đợi *Đánh giá mới chưa phản hồi* (không thêm nội dung lấp chỗ trống; giữ lưới 6 thẻ cho Admin và 5 thẻ `xl:grid-cols-5` cho Staff); bảo toàn quyền Staff vào Báo cáo & Nhập kho; rút gọn tối đa câu chữ lặp và đưa giải thích phụ chọn lọc vào `<x-admin.tooltip>`.
- **Báo cáo (`resources/views/admin/reports/index.blade.php`, `resources/js/components/report-charts.js`):** chỉ giữ ngoài tab thanh lọc ngày, lỗi khoảng ngày, dải kỳ đang xem và thanh tab (`Tài chính`, `Vận hành & Đơn hàng`, `Tồn kho hiện tại`, `Đánh giá bài viết`); đưa toàn bộ KPI và nội dung vào đúng tab (Admin mặc định `Tài chính`, Staff mặc định `Vận hành & Đơn hàng` + `Tồn kho hiện tại`); bỏ emoji `👍`/`👎` và chuyển giải thích công thức/chú giải biểu đồ sang `<x-admin.tooltip>`; giữ hash tab, phím mũi tên và `chart.resize()` + `chart.update('none')` khi chuyển tab.
- **Kiểm tra & Xác nhận:** `npm run build` PASS; `vendor/bin/pint --dirty --format agent` PASS; PHPUnit trực tiếp liên quan (`AdminOverviewReportsSidebarRedesignTest`, `ReportDashboardTest` trên `cena_store_test`): **12/12 tests, 156 assertions PASS**; Edge headless CDP trên `cena_store_test` xác nhận phiên Admin & Staff thật, tab mặc định theo quyền, chiều cao trang sau tab (`1450px` Tài chính / `1390px` Vận hành / `949px` Tồn kho), tooltip mobile không tràn viewport, `consoleErrors: []`, `pageErrors: []`, và đã dọn sạch dữ liệu/profile/script tạm.


## Sửa tooltip bảng & biểu đồ Chart.js, kỳ báo cáo theo lịch, tab gắn nội dung & footer quản trị (15/11/2026)

- **Tooltip bảng dùng chung (`resources/views/components/admin/tooltip.blade.php`, `resources/js/app.js`):** teleport panel sang `body` (`x-teleport="body"`) với `fixed z-[60]` và tính tọa độ từ `trigger.getBoundingClientRect()` (kẹp biên `[12px, innerWidth - width - 12px]`, tự lật trên/dưới, cập nhật khi scroll/resize, hỗ trợ hover vào panel, focus, Escape và chạm mobile), khắc phục triệt để việc bị container `overflow-hidden`/`overflow-x-auto` của bảng đơn hàng và Tổng quan cắt mép.
- **Tooltip biểu đồ Chart.js (`resources/js/components/report-charts.js`):** loại bỏ `filter: (item) => item.parsed.y !== 0` trên `financeChart` (nguyên nhân làm mất toàn bộ dòng số liệu trên ngày không phát sinh hoặc khi tương tác/render lại), thêm kiểm tra an toàn `items?.length`/`parsed.y` ở mọi callback tooltip của `financeChart`/`marginChart`/`feedbackChart`, reset active tooltip sạch khi resize/destroy/rebuild, thêm cờ `animate` (`renderCharts(false)` khi đổi theme) và đồng bộ hash tab qua `history.replaceState`.
- **Kỳ báo cáo theo lịch & Tab gắn nội dung (`app/Http/Controllers/Admin/ReportController.php`, `resources/views/admin/reports/index.blade.php`):** thay preset ngày cố định bằng kỳ lịch chuẩn múi giờ nghiệp vụ (`Tuần` Thứ 2–CN, `Tháng`, `Quý`, `Năm`), mặc định `Tháng` hiện tại; điều hướng `‹ Trước` / `Sau ›` dịch 1 kỳ (khóa `Sau ›` ở kỳ hiện tại, không sang tương lai), giữ `Từ ngày / Đến ngày` cho khoảng tùy chọn (`custom`), phân biệt rõ huy hiệu *Kỳ lịch* vs *Khoảng tùy chọn* kèm khoảng kỳ trước cùng độ dài; gắn `role="tablist"` kiểu folder-tab trực tiếp vào mép trên `role="tabpanel"` để tách biệt thị giác với thanh chọn kỳ; giữ nguyên `tab` qua URL/Back-Forward và `Tồn kho hiện tại`.
- **Footer quản trị (`resources/views/layouts/admin.blade.php`):** đổi footer layout quản trị thành `© {năm hiện tại} CENA — Quản trị cửa hàng.` (không sửa footer storefront).
- **Kiểm tra & Xác nhận:** `npm run build` PASS; `vendor/bin/pint --dirty --format agent` PASS; PHPUnit (`AdminOverviewReportsSidebarRedesignTest`, `ReportDashboardTest`, `AdminCrudStandardizationTest` trên `cena_store_test`): **18/18 tests, 231 assertions PASS**; Edge headless CDP trên `cena_store_test` xác nhận tooltip bảng không bị cắt trên `/admin/orders`, tooltip Chart.js giữ đủ 3 dòng + footer qua hover lặp lại (ngày có số liệu → ngày 0 → ra ngoài → quay lại → chuyển tab → đổi theme), kỳ lịch + quyền Admin/Staff, footer quản trị, Mobile `390×844` không tràn ngang, `consoleErrors: []`, `pageErrors: []`, và đã dọn sạch dữ liệu/profile/script tạm.
- **Tinh chỉnh sau nghiệm thu (15/11/2026):**
  - **Mốc "đến thời điểm xem" (`ReportService::now()`, `ReportController::index()`):** khi xem kỳ lịch hiện tại (`period !== 'custom' && $to === $today`), chặn `$end` tại thời điểm xem (`$now->addSecond()`) thay vì cuối ngày hôm nay; hiển thị rõ `(đến HH:mm)` trên tóm tắt kỳ và bổ sung assertion PHPUnit chứng minh bản ghi sau giờ xem bị loại trừ.
  - **Thanh tab báo cáo (`resources/views/admin/reports/index.blade.php`):** đổi từ kiểu folder sang thanh tab gạch chân gọn gàng (`border-b-2`, `overflow-y-hidden`) loại bỏ hoàn toàn thanh cuộn dọc thừa trên desktop/mobile nhưng vẫn tách biệt với bộ chọn kỳ dạng pill.
  - **Nhãn trục % (`resources/js/components/report-charts.js`):** định dạng nhãn trục Y của `marginChart` qua `percent(Number(value))` (`46,4%`, `46,6%`, `46,8%`, `47%`), không làm nhiễu dấu phẩy động và giữ nguyên giá trị tính toán.
  - **Kiểm tra lại:** `npm run build` PASS; `vendor/bin/pint --dirty --format agent` PASS; PHPUnit **18/18 tests, 234 assertions PASS**; Edge headless CDP xác nhận Dark Mode thực qua nút chuyển theme trên header (`data-theme="dark"`), tooltip Chart.js trên nền tối, `tablistScrollHeight === tablistClientHeight`, nhãn trục % gọn và Mobile `390×844` không tràn ngang.

## Nhóm 3 — Đợt 01 F1 Thẻ kho (10/11/2026, Codex)

- Trạng thái: IN_PROGRESS; nhánh `feat/nhom3-dot01-f1-f4-implementation`, base thực tế `70e5e96f7f6833f15e647f8da14b53f9a7a9bd2d`. Giữ nguyên untracked `.g2.2-npm-cache/`; chưa commit/push/merge.
- Mở tìm SKU từ danh sách sản phẩm, thêm lọc trạng thái gồm inactive và soft-deleted, trang chi tiết chỉ đọc hiển thị màu/SKU, tồn kho, đang giữ, khả dụng (`stock_qty - reserved_qty`), cách ly và giá vốn trong đúng phạm vi F1 Admin/Staff. Route vẫn nằm trong nhóm middleware `auth` + `role:admin,staff`; SKU không thuộc sản phẩm trả 404.
- Thẻ kho lọc SKU/ngày nghiệp vụ/loại sự kiện/chứng từ, mới nhất trước, 20 dòng/trang; hiển thị movement ID, thời điểm, action, reference, actor snapshot, before/delta/after cả ba bucket và cost basis/average cost dưới dạng decimal string. Không có write route hoặc gọi InventoryService từ trang đọc.
- Đối chiếu chỉ báo `HISTORY_INCOMPLETE` khi không có history (kể cả ba bucket đang zero), snapshot đầu không ở zero hoặc chuỗi snapshot sau→trước bị đứt. `OBSERVED_BALANCE_DIFF` chỉ được đánh dấu khi có snapshot movement cuối và bộ ba after khác bucket hiện tại; không có history thì không tự tạo baseline/đánh sai số. Hai cảnh báo được tính độc lập và có thể cùng hiện.
- Đã thêm 9 feature test cho quyền Admin/Staff/Customer/Guest, SKU scope, product/SKU soft-delete, tìm SKU trong danh sách, lọc thời gian/action/reference và biên cuối ngày microsecond, sort/pagination20, bốn tình huống reconciliation và GET không tạo movement. Chưa chạy PHPUnit: Docker `cena-g22c-test` không khả dụng (Docker named pipe không tồn tại); PHP host 8.3.30 không đạt composer platform requirement 8.4.1 nên Artisan không boot. Không dùng SQLite/Laragon/development DB.
- Kiểm tra hiện có: `php -l` PASS trên `InventoryHistoryQueryService.php`, `ProductController.php`, `routes/admin.php`, `InventoryHistoryTest.php` bằng PHP8.3 chỉ để kiểm tra cú pháp; `php vendor/bin/pint --dirty --format agent` PASS; `git diff --check` PASS. Blade runtime/browser/build và DB feature tests chưa chạy. Không có skill Impeccable trong phiên này.
- Bước tiếp theo: Lead review diff; cần môi trường PHP8.4.1+ và Docker test trước khi gọi F1 đã kiểm chứng.

## Nhóm 3 — Đợt 01 F2 Nhận hàng hoàn (10/11/2026, Codex)

- Trạng thái: phần tiếp nhận/kiểm tra thuộc F2 đã triển khai trên `feat/nhom3-dot01-f1-f4-implementation`, xếp sau commit F1. Không push/merge.
- Từ chi tiết đơn FAILED mở màn hình kiện hoàn; Staff đối chiếu mã đơn/vận đơn, SKU, số đôi, người nhận và thời điểm, tiếp nhận từng unit vào quarantine. Chỉ cập nhật Shipment sang `RETURN_RECEIVED` sau khi mọi unit thuộc shipment đã nhận; nhận thiếu không cộng tồn và không tự ghi LOST. Nhãn đôi # được ghi rõ là số thứ tự dòng, không phải serial vật lý.
- Mỗi unit có unique return item và operation key do server dựng; request nhận lại cùng unit trả bản ghi cũ, còn InventoryService từ chối nhận một source lần nữa dù dùng operation key khác. Restock dùng COGS đã chốt lúc xuất; write-off cần Staff đề xuất và Admin khác quyền phê duyệt, ghi companion `INVENTORY_LOSS` cùng transaction. Không đảo doanh thu. Không sửa `InventoryService`/`OrderWorkflowService` hay bucket trực tiếp.
- Bổ sung shipment/unit audit events cho thiếu đôi, SKU sai, hư hỏng và thiếu chứng cứ; event ghi Staff và thời điểm, request lặp theo cùng unit/loại incident không nhân đôi và không đổi kho. Hàng lỗi/thiếu chứng cứ vẫn ở quarantine khi đã nhận.
- Test bổ sung: 7 feature cases nhận đủ/thiếu, nhầm order, lặp cùng unit và key khác, nhiều đôi cùng SKU, quarantine, WAC đổi trước restock, write-off phân quyền/ledger/idempotency, rollback khi audit event thất bại, incidents và quyền truy cập; thêm test concurrency với hai child worker/DB connection cho cùng unit. Worker chỉ nhận credential qua process environment và cleanup theo fixture ID.
- PHPUnit chưa chạy: `php artisan test --compact tests/Feature/FailedDeliveryReturnTest.php` dừng trước bootstrap do PHP host `8.3.30` thấp hơn composer requirement `>=8.4.1`; Docker Desktop test socket cũng không khả dụng. Không dùng SQLite, DB development hoặc Laragon. Không khẳng định migration/Blade/MySQL/runtime đã được chạy.
- Kiểm tra cục bộ: `php -l` PASS trên PHP files của F2 bằng PHP8.3 để xác nhận cú pháp; `php vendor/bin/pint --dirty --format agent` PASS; `git diff --check` PASS. PHPUnit/DB, Blade render, browser QA và build chưa chạy.
- Independent Reviewer: PASS sau một repair cycle. Điểm còn chờ `INTEGRATION_PENDING`: xử lý LOST theo DEC-19 và refund/return completion; không tự thêm hoặc đổi quy trình liên nhóm. F2 không kích hoạt những workflows khác.

## Nhóm 3 — Đợt 01 F3 Thông báo trong web (10/11/2026, Codex)

- Trạng thái: triển khai và independent review PASS; commit cục bộ trên `feat/nhom3-dot01-f1-f4-implementation`, xếp sau F2. Không push/merge.
- Hook `OrderStatusHistory::created` đồng bộ trong transaction gốc; chỉ ghi `PACKING`, `ON_DELIVERY`, `DELIVERED`, `CANCELLED`, `FAILED`. `PENDING`/`CONFIRMED` không thông báo; giữ nguyên OrderWorkflow. Unique source-history/key và kiểm tra bản ghi hiện có chặn nhân đôi; rollback status history rollback notification cùng.
- Không bỏ mất event khi chủ đơn inactive hoặc soft-deleted. Trang account, unread-count API, mark-read API và web mark-read chỉ dành cho customer route; mọi truy vấn/read đều scope theo owner, ID của người khác trả 404, mark-read lặp idempotent. Resource chỉ trả mã đơn, trạng thái public, thời gian và URL đúng trang đơn; không có polling/email/broadcast hoặc thông báo cho toàn bộ Staff.
- Thêm chuông + unread badge server-render và trang danh sách dùng layout Cena hiện hữu; thêm model/resource/service/controller/migration/factory và cập nhật schema/model/resource contracts. Bảy feature test bao status selection, retry/idempotency, transaction rollback, owner inactive/soft-delete, read APIs/page/guard, payload an toàn và factory graph.
- PHPUnit bị chặn trước bootstrap: PHP host `8.3.30`, composer yêu cầu `>=8.4.1`; Docker Desktop test socket vẫn không khả dụng. Không dùng SQLite/Laragon/development DB. Không khẳng định test MySQL, migration thực thi, Blade render, build hoặc browser QA đã chạy.
- Kiểm tra cục bộ: `php -l` PASS cho các PHP file F3; ba contract JSON parse PASS; `php vendor/bin/pint --dirty --format agent` PASS; `git diff --check` PASS. Independent Reviewer: PASS sau một repair cycle, confidence `0.90`.

## Nhóm 3 — Đợt 01 F4 Work Center nền tảng (10/11/2026, Codex)

- Trạng thái: nền tảng Work Center đã triển khai và independent review PASS trên `feat/nhom3-dot01-f1-f4-implementation`; commit local, không push/merge. Phần này chưa phải nghiệm thu end-to-end F4.
- Thêm danh sách/chọn tab đang xử lý và đã hoàn thành, tìm/lọc, phân trang 20, chi tiết snapshot nguồn/thời điểm/lịch sử, cập nhật có kiểm soát phiên bản. Route chỉ cho Admin/Staff; HTTP chỉ nhận PENDING/IN_PROGRESS. Không có route tạo/xóa hoặc hoàn thành qua HTTP.
- Service chỉ tạo từ typed server definition trong transaction của nguồn; khóa unique `(source_type, source_event_key)` xử lý replay đồng thời, replay giống trả bản ghi cũ và replay khác định nghĩa báo `IDEMPOTENCY_MISMATCH`. Thời điểm được chuẩn hóa UTC trước khi lưu `DATETIME(6)`; event key dùng `utf8mb4_bin`. Lịch sử append-only lưu actor/role snapshot. Hoàn thành chỉ qua service với typed source outcome; chưa có verifier/producer nguồn nào được kích hoạt.
- Không thêm trigger/listener/job/scheduler, Staff broadcast/notification hay thay đổi workflow nguồn. Tích hợp tự động và xác minh kết quả nguồn vẫn chờ nguồn được chủ dự án duyệt; không tự coi những tích hợp này đã hoàn tất.
- Bổ sung feature tests về idempotency/mismatch, khác biệt hoa thường, timezone/replay, chuyển trạng thái/version/history, quyền Admin/Staff, lọc danh sách và cấm HTTP hoàn thành; concurrency test dùng hai worker riêng và guarded MySQL test DB, credential qua process environment, cleanup theo namespace fixture. Các tests chưa thực thi được.
- `php -l` PASS cho PHP files F4; `php vendor/bin/pint --dirty --format agent` PASS; ba JSON contract parse PASS. `php artisan test --compact tests/Feature/OperationsWorkCenterTest.php` dừng trước bootstrap do PHP host `8.3.30`, composer yêu cầu `>=8.4.1`; Docker test socket không khả dụng. Không dùng SQLite/Laragon/development DB. Chưa xác nhận migration/MySQL, Blade runtime, build hoặc browser QA.
- Independent Reviewer: PASS sau hai sửa chữa giới hạn (UTC `DATETIME(6)`, atomic replay/collation/theme), xác nhận cuối confidence cao. `.g2.2-npm-cache/` được giữ nguyên, không stage.
