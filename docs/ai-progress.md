# Nhật ký triển khai AI — Cena Store

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

- Nhánh feat/x1-categories-suppliers, worktree cena-codex-x1, base f137ff39ee6f07038b78a3a4dbcdc24dbefda34c; đã fetch/merge origin/main trước kiểm tra. Chỉ phạm vi Codex và hai ngoại lệ guard/test được người dùng cho phép; không thay source cũ, layouts/navigation, models/enums/migrations/Resources/helper tiền-kho hoặc lockfiles.
- Danh mục: CRUD tạo/sửa/ẩn, search/phân trang, slug unique kể cả bản soft-delete, cây tối đa2cấp cùng gender, chặn self/cycle/cấp3/đổi nhóm root còn child. Hide giữ nguyên nhánh/product để catalog kiểm ancestor visibility; không có route hard-delete.
- NCC: tạo/sửa/search/ngừng hợp tác, code unique, validate phone/email; giữ chứng từ và snapshot đã dùng. Admin/Staff dùng chung auth+role middleware và service kiểm current actor/quyền trước ghi; guest/customer/locked/revoked bị chặn.
- Dùng EXT-021/022 và layout chung. Bỏ input contact_name vì schema không có cột này; không thêm cột. ProductDemoTaxonomySeeder chỉ local/testing, cần Admin chủ động tạo trước;4root+3NCC theo slug/code, replay giữID và không hồi sinh bản đã ngừng, không tạo tồn/ledger.
- Nền ngoại lệ đã merge riêng trong PR-X0 (#10): TestDatabaseGuard chỉ thêm cặp chính xác cena_store_test_codex/cena_test_codex, chặn cross-pair/cache/root/SQLite. User Codex chỉ có quyền trên schema Codex. .env riêng và PHPUnit config riêng ngoài Git vượt force-name của phpunit.xml chung; không sửa phpunit.xml/credential/schema của Claude.
- Kiểm tra hẹp:25tests/93assertions PASS, gồm12 test nghiệp vụ/quyền và13 test guard. Build npm PASS; full Pint và dirty Pint PASS; desktop1440x900/mobile360x800 đã kiểm trực tiếp danh mục/NCC, thao tác và form giữ style Cena. Lần đầu thiếu build manifest và expected locked403 không còn đúng sau C1 (trả401): đã build asset và sửa expected theo middleware hiện hành, không nới quyền.
- Fixture browser chỉ trong schema Codex, dừng server riêng rồi reset đúng schema Codex có guard, không reset schema Claude/dev. CI toàn suite phải xanh trước squash merge; không tự sửa các phần Claude đang làm.
## Đợt 7 chức năng — PR-C3 sổ địa chỉ (02/11/2026, Claude)

Theo WF-04 / P02.05 ở mức tối thiểu.

- `AddressService`: thêm, sửa, xóa mềm, đặt mặc định. Mọi thao tác khóa dòng user trước, nên hai yêu cầu đặt mặc định cùng lúc chạy tuần tự; DB còn chặn thêm bằng cột `default_owner_id` unique. Địa chỉ đầu tiên tự thành mặc định; bỏ tick mặc định của địa chỉ mặc định duy nhất bị bỏ qua; xóa địa chỉ mặc định thì bỏ cờ trước rồi chuyển mặc định cho địa chỉ còn lại có id nhỏ nhất.
- Tỉnh/phường phải thuộc snapshot hiện hành và phường đúng tỉnh; lưu `province_id/ward_id` + snapshot mã, tên, `dataset_version`. Địa chỉ của snapshot cũ hiện cảnh báo cần chọn lại.
- Chủ sở hữu luôn lấy từ phiên; địa chỉ người khác hoặc đã xóa trả 404 cho xem/sửa/xóa/đặt mặc định.
- `App\Support\PhoneNumber` chuẩn hóa SĐT (bỏ dấu cách/chấm/gạch, `+84` → `0`), dùng chung cho đăng ký và địa chỉ.
- Giao diện `/account/addresses` (danh sách, thêm, sửa) trong layout cửa hàng; ô chọn tỉnh → phường của EXT-019 được nối với API `locations.wards` qua prop `wardsUrl`, giữ lựa chọn cũ khi lỗi nhập; ô "Đặt làm mặc định" dùng `choice-group`. Panel tài khoản tự hiện "Sổ địa chỉ".
- Test `AddressBookTest` 9 ca: khách bị chuyển đăng nhập, địa chỉ đầu tiên mặc định + snapshot, phường sai tỉnh/snapshot cũ/sai định dạng bị chặn, đổi mặc định chỉ còn 1, sửa chụp lại snapshot, xóa mặc định chuyển sang id nhỏ nhất, địa chỉ người khác 404, DB chặn 2 mặc định, trang escape dữ liệu và hiện cảnh báo snapshot cũ.
- Trình duyệt (DB dev, dữ liệu thật): chưa đăng nhập bị chuyển login rồi quay về sổ địa chỉ; chọn TP.HCM tải 168 phường; thêm địa chỉ thành công, tự mặc định; trang sửa chọn sẵn đúng tỉnh/phường.
- Giới hạn: chưa có test hai tiến trình MySQL cho đặt mặc định đồng thời — dựa vào khóa dòng user và ràng buộc unique của DB (có test).

## Đợt 7 chức năng — PR-C4 giỏ hàng (02/11/2026, Claude)

Theo WF-09 / P05.02–P05.04 ở mức tối thiểu (chưa có checkout).

- `CartService`: giỏ khách tìm bằng hash của token ngẫu nhiên lưu trong session (DB chỉ giữ hash, hết hạn 30 ngày); giỏ tài khoản theo `user_id`. Không nhận owner/giá/cart_id từ client. Thêm cộng dồn tối đa min(còn bán = stock − reserved, 10) mỗi size, tối đa 50 dòng; sửa là số tuyệt đối 1–10 (giảm luôn được, tăng phải còn hàng); xóa dòng và xóa hết là thao tác riêng. Mỗi thay đổi khóa giỏ, kiểm `version` (sai → 409 `CART_STALE` kèm giỏ hiện tại) rồi tăng version. **Không giữ hàng** khi thêm giỏ.
- Giá hiệu lực = giá riêng của size → giá khuyến mãi → giá gốc (VND nguyên). Món ngừng bán/hết hàng/vượt tồn vẫn hiện trong giỏ kèm cảnh báo và không tính vào tạm tính.
- Gộp giỏ khi đăng nhập/đăng ký (sau khi đổi session, token được giữ): khóa hai giỏ theo id tăng, cộng theo variant rồi cắt theo tồn/10, bỏ món không còn bán, báo lại các món bị giảm/bỏ; xóa giỏ khách trong cùng transaction (lỗi → giữ nguyên cả hai); gọi lại lần hai không làm gì. Đăng xuất → khách mới có giỏ trống.
- API JSON `cart.show`, `cart.items.store|update|destroy`, `cart.clear` (60 request/phút); item của giỏ khác → 404.
- Giao diện: `resources/js/components/cart.js` (Alpine store `cart`): chỉ đổi số sau khi server trả lời, mỗi lúc chỉ một thao tác, xử lý 401/419/409/422/429/404/mất mạng; khung giỏ trong `panels` (ảnh, màu, size, giá, +/−, xóa, tạm tính, ghi chú "kiểm tra lại khi đặt hàng"), số đôi trên icon giỏ, meta CSRF. Nút nào có `data-add-to-cart data-variant-id` (trang chi tiết của PR-X4) sẽ tự thêm vào giỏ và mở khung giỏ.
- Test: `CartTest` 10 ca, `CartMergeTest` 4 ca, `tests/JavaScript/cart.test.mjs` 7 ca. Toàn bộ suite local **940 tests PASS**; Node 28/28.
- Giới hạn: chưa kiểm luồng thêm giỏ trên trình duyệt với sản phẩm thật vì DB dev chưa có sản phẩm (đợi PR-X2/X3/X4); pane trình duyệt bị ẩn nên không xem được hiệu ứng mở khung giỏ, dữ liệu khung giỏ đã kiểm bằng JS. Nút "Đặt hàng" để trạng thái "sắp có".

## Đợt 7 chức năng — PR-X2 sản phẩm/màu/size/ảnh (02/11/2026, Codex)

- Worktree cena-codex-x2, nhánh feat/x2-products từ main sau X1; đã fetch/merge main mới nhất a519898221a3d22acba695239dd7e3416acf6f84, nhận C3/C4 mà không sửa file Claude. Không đổi models/enums/migrations/Resources/InventoryService/Money/Cost/Clock hoặc dependency/lockfiles.
- ProductManagementService lưu nguyên graph trong transaction: thông tin sản phẩm, colors, variants và album; stage file ngoài closure retry SQL, lỗi rollback graph và dọn đúng tệp mới. Giữ tệp cũ khi bỏ ảnh khỏi album để snapshot chứng từ không vỡ; cleanup orphan do process crash cần quy trình bảo trì sau đợt demo, không xóa hàng loạt file lịch sử.
- Dùng ShoeSize/ShoeSizeSequence, giữ chuỗi40.5 và chặn trùng sau canonical40.0→40; dải size chỉ dùng riêng, SKU/style-color unique, scoped child IDs trả404. SKU/size/style-color đã có chứng từ không đổi; bỏ màu/size chỉ deactivate; ngừng bán product giữ graph và chứng từ.
- Chặn stock/reserved/quarantine/cost/size_numeric trong payload; variant mới dùng số dư0 từ schema, không tạo movement/ledger. Giá VND dùng Money, base/sale/override dương và sale<=base; override cao hơn base vẫn được giữ.
- Ảnh dùng đúng1source upload JPEG/PNG/WebP kiểm MIME server hoặc URL HTTPS không chứa user/password; không fetch URL từ server. Mỗi màu đúng1thumbnail. Media path/hash do server tạo; không nhận storage_path của client.
- Form EXT-023/024/026 được nối native multipart, thêm SKU/dải size/album/thumbnail/sắp ảnh; không đổi layout/navigation/app.js. Root/child flags, input name và old input được wire; giữ style Cena và label/keyboard cơ bản.
-33test X2/185assertions, cộng12test hồi quy X1 thành45tests/268assertions PASS trên cena_store_test_codex. Bao graph2màu×3size/album, MIME giả, hai nguồn, file upload thật, lỗi SKU cuối rollback file+DB, canonical duplicate, cost payload, giá, ownership404, chứng từ immutable, roles guest/customer/staff/admin, đổi và giữ thumbnail.
- Lỗi tìm qua test/QA: Blade namespace escape sai đã sửa; validated multipart dựng lại thứ tự numeric index nên chuẩn hóa theo index trước cả hai lượt validate (không đổi InputBag/file objects); thumbnail bulk clear làm model stale nên sync original trước ghi lại. Test upload dùng HTTP multipart thật, không postJson giả file. Thêm assert giữ thumbnail khi save lại cùng graph.
- Browser desktop1440x900/mobile360x800: ảnh public load thật, sửa tên+giá riêng size40.5 rồi reload còn đúng, thumbnail qua hai lần save còn chọn; sửa fieldset min-width/file-input để body350<=viewport360. Không lỗi JavaScript trong phiên QA. Build và Pint PASS; ảnh QA ngoài Git, không báo cáo riêng.
- MySQL local từng dừng kết nối trong QA; restart đúng runtime Cena/my.ini hiện hữu, không initialize/provision lại hoặc reset credential. Fixture UI chỉ trong Codex schema, đóng tab/server và guard schema trước reset/rerun; không thay dữ liệu dev hoặc schema Claude.

- CI đầu PR-X2 #13: https://github.com/httrung0308/cena-store-laravel/actions/runs/41386672622 có971test PASS và2test SharedFoundationTest fail vì còn assert menu Sản phẩm disabled. X2 đã bật route/menu đúng contract. Chưa merge; đang xin ngoại lệ cập nhật đúng assertion trong test chung, không đổi layout/navigation hoặc nới kiểm quyền.

- Người dùng đã cho phép ngoại lệ SharedFoundationTest.php: chỉ thay assertion placeholder Sản phẩm disabled bằng kiểm tra href admin.products.index cho cả Admin/Staff; không đổi layout/navigation hoặc kiểm quyền khác. Chạy lại regression và CI trước merge.

## Đợt 7 chức năng — PR-X3 phiếu nhập kho (02/11/2026, Codex)

- Worktree cena-codex-x3, nhánh feat/x3-stock-imports từ main 532e77d (X2 đã merge); chỉ sửa khu vực Codex và ngoại lệ test hạ tầng người dùng duyệt. Giữ nguyên source Cena cũ, giao diện/layout/navigation và toàn bộ mã tài khoản/địa chỉ/giỏ Claude; không dependency/lockfile/model/enum/migration mới.
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
