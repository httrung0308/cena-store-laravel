# Các quyết định nền tảng — P00

Ngày rà soát: 31/10/2026. Trạng thái: baseline cho P01 trở đi; kết quả nghiệm thu P00 nằm trong `../evidence/p00-verification.md`.

## ADR-001 — Nguồn chuẩn, phạm vi và thứ tự ưu tiên

- Yêu cầu trực tiếp của người dùng: triển khai P00, tạo project mới, giữ nguyên source cũ, kiểm tra từng task. Yêu cầu tiếp theo: **giữ giao diện source gốc**, không tự thiết kế giao diện thay thế.
- `../specification.md` là snapshot của kế hoạch hợp nhất, đối chiếu SHA-256 trong `../requirements/source-manifest.json`. Không sửa bản gốc ở Downloads.
- Rubric gốc đã tìm thấy và đọc: `YeuCau_DoAnCuoiMon_LTMNM_Laravel.docx`. Do đó trạng thái “chưa có rubric” ở tài liệu cũ được thay bằng “đã đối chiếu”, xem traceability. Hạn nộp được quy định **sau khi kết thúc học phần một tuần**; ngày cụ thể và ngày duyệt đề tài chưa được giảng viên điền, không tự suy đoán.
- Một cửa hàng/một kho, một shipment/đơn. RMA tiếp nhận yêu cầu online, giao nhận giày tại cửa hàng. Không bổ sung vận chuyển RMA, nhiều kho, chia shipment, payment production hay tự hoàn VNPay ngoài đặc tả.
- P00 cung cấp hạ tầng có thể chạy và kiểm thử. Không tuyên bố đã có auth, catalog thật, checkout, tiền/kho/ledger hoặc toàn bộ ứng dụng.

## ADR-002 — Nền tảng mới và mã cũ

- Mới: Laravel 13, PHP 8.4, MySQL 8.4/InnoDB/utf8mb4, Eloquent, Blade, Vite, Tailwind **3.4**, Alpine **3**, Chart.js **4**, DomPDF. Phiên bản thực cài: `../runtime-versions.json`; dependency chính xác: hai lockfile.
- Nguồn cũ được đọc để đối chiếu: `frontend/package.json`, `frontend/pages`, `frontend/modules`, `frontend/common`, `frontend/public`; Java services và Flyway SQL trong `backend/src/main`, `backend/pom.xml`, SQL Server compose.
- Viết lại auth/session/CSRF/Policy, validation, service nghiệp vụ, transactions, schema MySQL, payment signatures/idempotency, ledgers và outbox. Không chuyển nguyên JWT/localStorage/role string của Java thành contract mới; không port stored procedures, SQL Server triggers, Next/Recoil/Apollo/Strapi/Stripe runtime vào project Laravel.
- Tái sử dụng **thiết kế và nội dung hiển thị** của nguồn gốc: Montserrat, nền trắng/chữ đen, chữ Cena, “Sport, the smart choice.”, hero `main.jpg`, Men/Women/Kid/Unisex, ảnh đúng bản gốc, navbar, footer/attribution. JSX được chuyển thành Blade, state hiển thị sang Alpine. `public/legacy` có asset SHA-256 giống nguồn; giấy phép giữ tại `LICENSE-legacy-ui`.
- P00 đã chuyển homepage và shell menu/cart; không nói toàn bộ các màn hình cũ đã chuyển. Account/catalog/checkout và admin sẽ port theo phase tương ứng, dùng template gốc làm tham chiếu, không đổi thiết kế khi chưa được yêu cầu.
- Khi module chưa được triển khai: nút Explore/Account hiển thị thông báo sắp mở, menu trỏ tới collection trên home; drawer là bản xem trước trạng thái trống, không ghi giỏ/database. P05 sẽ nối vào route canonical `catalog.index` (`/shoes`) và CartService. Không tạo endpoint nghiệp vụ giả để cho UI có vẻ hoàn tất.
- Animation Framer Motion chuyển sang CSS, giữ cấu trúc/khoảng thời gian và trạng thái cuối; cubic-bezier có x âm ở bản gốc được đưa về miền CSS hợp lệ. Các icon user/cart/menu/close dùng đúng SVG path từ react-icons4.3.1 trong lockfile gốc, kiểm integrity của tarball, giữ `LICENSE-react-icons`; không thêm React runtime. Không cam kết tương đương từng pixel khi chưa có so sánh trực tiếp bản Next đang chạy. Hỗ trợ reduced-motion và focus cho dialog là cải tiến khả năng truy cập.
- Quy tắc này áp dụng cả P02/P03/P05/P08/P09/P10. Không giữ giao diện marketing “Từng bước khác biệt” tạo ban đầu; giao diện đó đã được thay bằng bản port từ source gốc.

## ADR-003 — 34 bảng miền + 6 bảng hạ tầng

Tên canonical (trích nguyên danh mục §6.2):

| STT | Bảng | Tham chiếu trực tiếp |
|---:|---|---|
| 1 | `users` | — |
| 2 | `roles` | — |
| 3 | `user_roles` | `users`, `roles` |
| 4 | `password_reset_challenges` | `users` |
| 5 | `administrative_units` | `administrative_units` |
| 6 | `addresses` | `users`, `administrative_units` |
| 7 | `categories` | `categories` |
| 8 | `products` | `categories` |
| 9 | `product_colors` | `products` |
| 10 | `product_images` | `product_colors` |
| 11 | `product_variants` | `product_colors` |
| 12 | `suppliers` | — |
| 13 | `stock_imports` | `suppliers`, `users` |
| 14 | `stock_import_items` | `stock_imports`, `product_variants` |
| 15 | `inventory_movements` | `product_variants`, `users` |
| 16 | `carts` | `users` |
| 17 | `cart_items` | `carts`, `product_variants` |
| 18 | `coupons` | — |
| 19 | `coupon_redemptions` | `coupons`, `users`, `orders` |
| 20 | `orders` | `users`, `coupons` |
| 21 | `order_items` | `orders`, `product_variants` |
| 22 | `order_item_units` | `order_items`, `product_variants` |
| 23 | `order_status_histories` | `orders`, `users` |
| 24 | `payments` | `orders`, `users`, `payments` |
| 25 | `payment_events` | `payments` |
| 26 | `refunds` | `orders`, `payments`, `after_sales_requests`, `order_item_units`, `users` |
| 27 | `shipments` | `orders`, `users` |
| 28 | `shipment_return_items` | `shipments`, `order_item_units`, `product_variants`, `users` |
| 29 | `reviews` | `order_item_units`, `users` |
| 30 | `after_sales_requests` | `order_item_units`, `product_variants`, `users` |
| 31 | `after_sales_histories` | `after_sales_requests`, `users` |
| 32 | `after_sales_attachments` | `after_sales_requests`, `users` |
| 33 | `sales_ledger_entries` | `orders`, `order_item_units`, `after_sales_requests`, `refunds`, `inventory_movements` |
| 34 | `outbox_events` | — |

Hạ tầng: `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`. Tổng baseline hoàn chỉnh: **40 bảng ứng dụng/hạ tầng**, cộng `migrations` nội bộ là **41 bảng thực tế**. Không thêm `password_reset_tokens` vì đặc tả dùng `password_reset_challenges`.

P00 tạo **6 bảng hạ tầng + migrations = 7 bảng thực tế** để trang/session/cache/queue chạy. Chưa tạo bảng `users` tối giản của skeleton, tránh tạo schema tạm sai canonical. P01.01 bổ sung 34 bảng miền theo dictionary/FK/index/constraints; giữ ba migration hạ tầng đã chạy, không sửa hồi tố hay tạo trùng bảng. `sessions.user_id` hiện nullable/index theo framework, không khóa ngoại tới bảng chưa tồn tại. `User`/factory mặc định là scaffold sẽ được thay đầy đủ tại P01.02; DatabaseSeeder hiện không sinh user demo sai schema.

Cập nhật P01.01 (31/10/2026):34 bảng miền canonical đã được tạo, cộng6 hạ tầng và migrations thành41 bảng thực tế. Schema miền có483 cột gồm4 generated helpers,70 FK,79 index/unique khai báo và110 CHECK enforce. Ba migration P00 giữ nguyên; sáu migration mới được thêm theo §6.5. Xem `../database/schema.md` và nhật ký. Model/factory scaffold chưa thay vì thuộc các task sau, không dùng để tạo user trước P01.02/P01.04. Tài liệu P00 ở evidence là lịch sử kết quả thời điểm P00, không đại diện số bảng hiện tại.

Cập nhật P01.02: scaffold User/Factory đã được thay theo full_name và schema canonical. Có34 model miền,31 string-backed enums và34 factory cấu trúc phục vụ kiểm thử; không seed vào development. Timestamps miền giữ microseconds và cast immutable UTC; tiền integer, cost decimal string, Refund.destination_ciphertext encrypted:array. Resource Customer/Admin tách biệt theo whitelist; các guard serialization không thay thế middleware/Policy/service ở P02 trở đi. Factory không thực hiện workflow kho/thanh toán/RMA thật; seed nền và state demo xuyên luồng vẫn thuộc P01.04/P10.

## ADR-004 — Roles, màu và danh tính từng đôi

- Role là `roles.slug` + pivot `user_roles`; giá trị `admin`, `staff`, `customer`. Hiệu lực khóa user/đổi quyền từ request tiếp theo; không ngầm cam kết WebSocket.
- Product → `product_colors` → variants theo size; `product_images` thuộc color. Ảnh/sizes không treo trực tiếp sai parent vào product. Không hard-delete đối tượng đã phát sinh.
- Order → items → `order_item_units`. Review/RMA/refund từng đôi phải scope về đúng unit/order/user. Không dùng dòng đơn nhiều đôi thay cho định danh từng đôi.
- Route và field chuẩn xem nguyên §6, §12 trong specification; không tự đổi `style_color_code`, `order_code`, `unit_no`, `operation_key` hoặc route prefix. Chi tiết sản phẩm `/products/{product:slug}`, catalog `/shoes`, quản trị `/admin`, tài khoản `/account`.

## ADR-005 — Tiền, kho, thời gian và bất biến

- Đơn vị tiền là **VND**, chỉ số nguyên. `1000000` nghĩa một triệu đồng, không nhân 100 trong database hoặc browser.
- Giá vốn bình quân, giá vốn tham chiếu kho: `DECIMAL(20,4)`, tính bằng BCMath; không ép về float PHP/JS. Tiền bán, discount, shipping, thu, hoàn và COGS phân bổ vào đơn: `BIGINT` nguyên VND.
- Giới hạn giá/đơn trong FormRequest/config để phép nhân không tràn integer64; baseline đơn ≤1.000.000.000 VND, quantity1..10 mỗi variant, tối đa50 dòng giỏ. Không dựa HTML `max` để chặn.
- Quy tắc làm tròn: half-up đến1 VND ở bước tổng cuối được chỉ rõ; phân bổ dùng largest remainder theo `order_item_units.unit_no` ổn định để tổng không lệch1đ.
- `stock_qty`: số đôi **bán được đang nằm tại cửa hàng**, gồm cả đôi đã giữ cho đơn. `reserved_qty`: một phần của stock đang giữ. `quarantine_qty`: đôi cửa hàng sở hữu nhưng chưa/không được bán do đang kiểm tra; tách khỏi stock.
- `available_qty = stock_qty - reserved_qty`; số đôi cửa hàng đang cầm và sở hữu = `stock_qty + quarantine_qty`. Giày khách gửi bảo hành vẫn thuộc khách, không cộng vào hai số này.

UTC ở PHP/database, chuyển Asia/Ho_Chi_Minh khi hiển thị nghiệp vụ. Thời hạn so sánh theo clock và snapshot trong đặc tả, không lấy timezone máy người dùng. Lưu giữ đơn và chứng từ đã ghi sổ; sửa bằng event/bút toán bù.

1. **INV-01:** `0 <= reserved_qty <= stock_qty`, `quarantine_qty >= 0`; mọi biến động có `inventory_movements.operation_key` duy nhất.
2. **INV-02:** Không giữ hàng khi chỉ thêm giỏ. Chỉ checkout hoặc đổi size được chấp thuận mới tạo reserve; luôn kiểm available dưới khóa.
3. **INV-03:** Đơn `PENDING/CONFIRMED/PACKING` giữ hàng đúng quantity của các unit chưa xuất. `ON_DELIVERY/DELIVERED/FAILED` không giữ hàng đơn bán nữa vì đã xuất.
4. **INV-04:** Hủy trước xuất chỉ release reserve, không cộng stock. Giao thất bại không tự cộng stock; hàng thực nhận về trước tiên vào quarantine.
5. **INV-05:** `order.total_amount = subtotal_amount - merchandise_discount_amount + shipping_fee`; shipping fee đã trừ shipping coupon, không trừ lại ở tổng.
6. **INV-06:** Tổng `unit.net_amount` bằng tổng tiền hàng sau giảm; `SUM(unit.original_cogs_amount)` bằng `order_item.cogs_amount` sau phân bổ lúc xuất. `current_cogs_amount` có thể đổi sau đổi size, không ghi đè giá vốn gốc của dòng đơn.
7. **INV-07:** Thu áp dụng cho đơn không vượt tổng phải thu. Tiền đến muộn/dư phải được giữ vết và hoàn/xử lý riêng, không áp dụng hai lần.
8. **INV-08:** Tổng hoàn SUCCEEDED cộng phần hoàn đang được giữ hạn mức không vượt khoản thu có thể hoàn. Một unit trả lỗi không nhận hoàn tiền hàng hai lần.
9. **INV-09:** Mỗi unit chỉ có tối đa một RMA đang mở; được đổi size thành công tối đa một lần. Sửa bảo hành có thể nhiều lần trong hạn, không làm mới thời hạn gốc.
10. **INV-10:** RMA của unit luôn thuộc đúng order và user; target variant chỉ khác size, cùng product_color, active, không cùng variant hiện tại.
11. **INV-11:** Chỉ order đã delivered và thu đủ mới ghi nhận bán hàng; payment và refund không tự đồng nghĩa doanh thu.
12. **INV-12:** Snapshot đơn và ledger đã ghi sổ không được sửa theo giá/tên/cost sản phẩm hiện tại; sửa sai bằng event/bút toán bù.
13. **INV-13:** Bỏ quyền/khóa user có hiệu lực ở request tiếp theo; không có thao tác đẩy WebSocket được ngầm giả định.
14. **INV-14:** Actor Customer luôn được scope bằng user đăng nhập; biết order code, unit ID, file path hoặc cart ID không cấp quyền.
15. **INV-15:** Không hard-delete chứng từ/variant/color đã phát sinh. Soft delete hoặc ngừng kinh doanh không làm mất khả năng đọc đơn và thao tác nhận hàng trả.

Các bất biến này **được đóng băng để triển khai/test ở phase sau**, chưa được tuyên bố kiểm chứng hành vi trong P00. P04/P06/P07/P08/P09 phải có test transaction/concurrency trên MySQL thật; test HTTP tuần tự không phải bằng chứng race-safe.

## ADR-006 — Enum contract

Tên enum và giá trị lưu DB/JSON giữ đúng bảng sau. PHP case có thể TitleCase theo quy tắc code nhưng backing value không đổi.

| Enum PHP | Giá trị canonical |
|---|---|
| `RoleSlug` | `admin`, `staff`, `customer` |
| `OrderStatus` | `PENDING`, `CONFIRMED`, `PACKING`, `ON_DELIVERY`, `DELIVERED`, `CANCELLED`, `FAILED` |
| `PaymentMethod` | `COD`, `VIETQR`, `VNPAY` |
| `OrderPaymentStatus` | `UNPAID`, `PAID`, `REFUND_PENDING`, `PARTIALLY_REFUNDED`, `REFUNDED` |
| `PaymentAttemptStatus` | `PENDING`, `SUCCEEDED`, `FAILED`, `EXPIRED` |
| `PaymentDisposition` | `NONE`, `APPLIED`, `UNAPPLIED_LATE`, `UNAPPLIED_DUPLICATE`, `UNAPPLIED_MISMATCH` |
| `RefundStatus` | `REQUESTED`, `APPROVED`, `PROCESSING`, `SUCCEEDED`, `FAILED`, `CANCELLED` |
| `CouponType` | `PERCENTAGE`, `FIXED`, `FREE_SHIPPING` |
| `CouponRedemptionStatus` | `RESERVED`, `CONSUMED`, `RELEASED` |
| `StockImportStatus` | `DRAFT`, `POSTED`, `CANCELLED` |
| `ShipmentStatus` | `READY`, `IN_TRANSIT`, `DELIVERED`, `RETURNING`, `RETURN_RECEIVED`, `LOST` |
| `OrderUnitState` | `ALLOCATED`, `OUTBOUND`, `OWNED_BY_CUSTOMER`, `RETURNED_TO_STORE`, `LOST`, `RETURNED_REFUNDED`, `CANCELLED` |
| `AfterSalesType` | `SIZE_EXCHANGE`, `WARRANTY_REPAIR`, `DEFECT_RETURN` |
| `AfterSalesStatus` | `NEW`, `APPROVED`, `RECEIVED`, `PROCESSING`, `AWAITING_REFUND`, `READY_FOR_PICKUP`, `COMPLETED`, `REJECTED`, `CANCELLED` |
| `ReturnDisposition` | `PENDING`, `RESTOCK`, `WRITE_OFF` |
| `InventoryAction` | `IMPORT_POSTED`, `ORDER_RESERVED`, `ORDER_RELEASED`, `ORDER_DISPATCHED`, `SHIPMENT_RETURN_RECEIVED`, `RETURN_RESTOCKED`, `RETURN_WRITTEN_OFF`, `EXCHANGE_RESERVED`, `EXCHANGE_RELEASED`, `EXCHANGE_OLD_RECEIVED`, `EXCHANGE_COMPLETED_IN`, `EXCHANGE_COMPLETED_OUT`, `DEFECT_RECEIVED`, `MANUAL_ADJUST_IN`, `MANUAL_ADJUST_OUT`, `QUARANTINE_WRITEOFF` |
| `SalesLedgerType` | `SALE_RECOGNIZED`, `SHIPPING_RECOGNIZED`, `SALE_REVERSED`, `SHIPPING_REVERSED`, `EXCHANGE_COGS_ADJUSTMENT`, `RMA_EXPENSE`, `INVENTORY_LOSS` |
| `OutboxStatus` | `PENDING`, `PROCESSING`, `SENT`, `FAILED` |

## ADR-007 — Môi trường và dữ liệu riêng

- Source mới ở thư mục sibling `cena-store-laravel`; Git riêng, nhánh `codex/p00-foundation`. Không sửa/xóa/di chuyển source cũ, không dùng DB SQL Server cũ, không commit thay người dùng.
- Local MySQL riêng trên `127.0.0.1:3307`, datadir `%LOCALAPPDATA%/CenaStore/services/mysql84/data`. `cena_app` chỉ có quyền trên `cena_store`, `cena_test` chỉ có quyền trên `cena_store_test`. Không dùng root cho ứng dụng/test. Root chỉ bootstrap instance riêng.
- Test harness force MySQL/tên DB test/DB_URL rỗng; guard kiểm tra cấu hình thực sau URL resolution, chặn user app/root và config cache trước các trait reset DB. Quyền MySQL độc lập là lớp chặn thứ hai. Không dùng SQLite thay MySQL.
- CI tạo hai DB và hai user trên service MySQL disposable, tự tạo APP_KEY/password, không đọc secret của tác giả. `cena_ci` chỉ dùng schema test.
- Local session/cache/queue dùng database. Trong PHPUnit: session/cache array, queue sync, mail array để cách ly side effects; kết nối database vẫn MySQL thật. Hạ tầng mail thật kiểm tra riêng bằng Mailpit local, không gửi Internet.
- Public disk chỉ symlink `public/storage → storage/app/public`; private/local disk ở `storage/app/private`, không tự serve và không symlink. Controller file private + Policy sẽ triển khai đúng phase nghiệp vụ.
- Mailpit SMTP 1025, web UI 8025, đều loopback. Không chứa credential/payment key thật trong source hoặc báo cáo. `.env`, `.env.testing`, uploads, runtime/cache/log/vendor/node_modules/build bị ignore; chỉ env example có placeholder được đưa vào Git.

## ADR-008 — Quy trình thay đổi và bằng chứng

Mỗi task cập nhật `../ai-progress.md`; ghi lệnh, kết quả thật, lỗi đã khắc phục, giới hạn và task tiếp theo. CI chỉ được gọi “đã chạy trên GitHub” khi có run URL thành công; kiểm tra clean-copy local phải ghi rõ hệ điều hành và khác biệt với runner Linux. Không tự hứa điểm số hoặc bịa đóng góp nhóm.

## ADR-009 — Helpers và HTTP contract P01.03

Không thay schema, dependencies, routes nghiệp vụ hoặc UI.31 enum miền P01.02 giữ nguyên case/backing values và có label(); ErrorCode thêm29 mã với message()/httpStatus()/label(). Nhãn hiển thị tách khỏi giá trị trạng thái canonical.

### Tiền, giá vốn và size

| API | Quy tắc cho phase sau |
|---|---|
| Money::integer/add/multiply/subtract | VND không nhân100; nhận int hoặc chuỗi chữ số không âm, trả int; từ chối float/bool/null/dấu/exponent. Intermediate dùng BCMath, final không vượt PHP_INT_MAX. subtract không cho tiền âm; ledger delta âm phải được service tạo có chủ đích từ amount đã kiểm. |
| Money::roundHalfUp | Nhận decimal string/int không âm, làm tròn half-up đến1 VND; không cắt phần lẻ qua float. |
| Money::percentage | subtotal × percent /100 bằng BCMath, half-up một lần, min với subtotal/cap. null cap là không giới hạn, cap0 cho discount0; helper toán học cho0..100, Coupon validator phải yêu cầu1..100. |
| Money::orderTotal | Chặn mức mặc định1.000.000.000 VND; service truyền config('store.limits.order_total') làm maximum. Đây không phải sự cho phép client gửi tổng tiền. |
| Money::allocate | Input list {id, unit_no, weight}; weight là gross nguyên VND. Floor từng share rồi largest remainder; phần dư giảm dần, tie unit_no tăng dần, sau đó numeric ID tăng dần rồi binary string để toàn thứ tự ổn định. Identity phải duy nhất/bền vững, không dùng vị trí của list chưa sort. Output map id→integer có tổng đúng amount; không âm, không float. Zero amount/zero weights trả0; positive amount/zero total weight bị từ chối. |
| Cost::normalize/weightedAverage/cogs | DECIMAL(20,4) string, max9999999999999999.9999; số lượng kho0..UINT32_MAX, incoming>0, tổng không vượtUINT32_MAX. WAC=(Q×C+q×c)/(Q+q), half-up ở chữ số thứ4. Q là stock gồm reserved, không cộng quarantine. Gift cost0 được tính đúng nhưng quyền/ghi chú nhập giá0 thuộc InventoryService P04. COGS làm tròn1 VND sau khi nhân toàn line, rồi dùng allocate với trọng số bằng nhau để chia unit. |
| ShoeSize::normalize | Chuỗi half-size dương, ví dụ040.5000→40.5,40.0→40; từ chối float,40.1,exponent/âm/0/overflow DECIMAL(4,1). Đây là chuẩn hóa cấu trúc, tập sizeEU được phép vẫn do P03 FormRequest kiểm. Không nhận size_numeric độc lập từ client. |

Allocator dùng chung được cho discount/COGS. Khi discount, caller bắt buộc bound discount≤merchandise subtotal trước allocate; không cộng shipping vào gross/net của unit. Hàm không tự truy vấn DB, giữ hàng, giữ lượt coupon hoặc tạo ledger. store.limits còn qty10/variant,50 dòng giỏ và5 lần OTP sai; các hạn mức phải được thực thi ở FormRequest/service tương ứng, không coi config là đã có checkout/OTP.

### Thời gian và fingerprint

BusinessClock được bind singleton; now()/instant() trả CarbonImmutable UTC, freeze()/unfreeze() chỉ tác động instance đó. String instant không có offset được hiểu UTC; API nhận ngày cần đi qua validator, không suy đoán timezone browser. beforeDeadline dùng now<deadline; withinWindow start inclusive/end exclusive, null là không chặn, khoảng ngược bị từ chối. Cùng timestamp deadline đã hết hạn, kể cả độ chính xác microsecond.

exchangeDeadline cộng ngày theo snapshot; warrantyDeadline chuyển về business timezone rồi addMonthsNoOverflow, sau đó trả UTC để lưu. Nhờ vậy giao31/10/2026 00:30 VN +6 tháng là30/04/2027 00:30 VN, không thành01/05 do cộng tháng trên UTC. Khoảng lọc ngày VN inclusive đầu/cuối chuyển thành [đầu ngàyUTC, đầu ngày sau ngày cuốiUTC), không dùng23:59:59 làm end. OTP15 phút, cooldown60 giây, online payment30 phút, COD24 giờ, nhận RMA7 ngày lấy config; exchange/warranty dùng snapshot chứng từ.

CanonicalPayload::fingerprint nhận payload đã được service xác thực, trả SHA25664 ký tự. Map sort key đệ quy (binary string), list giữ thứ tự; int/string/bool/null phân biệt; enum dùng backing value, DateTime chuẩn UTC microsecond. Float, object tùy ý/model/resource, UTF8 lỗi hoặc sâu>32 bị từ chối. Không trim/uppercase coupon hoặc tự sort cart thay validator/PricingService; future QuoteDTO phải chuẩn hóa coupon/address, sort line bằng identity ổn định và đưa cart_version/address snapshot/method/coupon vào payload. Helper này chưa thay idempotency key/transaction/check quyền của P01.05/P06.

### JSON, Blade và request ID

ApiResponse::success và StoreResource/StoreResourceCollection trả success/message/data/meta.request_id. Root collection/pagination giữ links/meta pagination; resource lồng không tạo envelope riêng. status/order_status/payment_status/state có nhãn *_label tách riêng (order_status/state thêm status_label), backing value không dịch. Không tự gọi model.toArray để thêm cột; Customer/Admin whitelist và owner/admin guard giữ nguyên.

RequestId middleware tạo UUID server cho mỗi request, không tin header X-Request-ID từ client; trả header cùng mã trong meta, đưa vào log context để tra cứu. ExceptionRenderer nhận cả api/*, expectsJson và XMLHttpRequest. DomainException chỉ mang ErrorCode và field errors an toàn. Validation422; conflict/stale/idempotency/stock409; auth401; forbidden403; not found404; method405; CSRF419; throttle429; server500/503. Allow/Retry-After/WWW-Authenticate được giữ khi hợp lệ.500 không trả exception message/class/SQL/trace/path hoặc field errors dù APP_DEBUG=true. HttpResponseException đã có response tường minh được framework giữ lại.

Blade DomainException409/422 redirect back với error bag, flash error/error_code và old input đã bỏ password/OTP/token/bank account. ValidationException Blade dùng error bag/redirect chuẩn Laravel với dontFlash tương ứng;403/404/419/500 giữ status, không biến thành success. Browser auth redirect route login khi tồn tại; AJAX/API luôn401. Hiện chưa thêm màn hình/toast mới hoặc endpoint auth/cart/quote: các phase sau nối các slot flash/envelope này vào giao diện gốc.

Tham chiếu implementation: Laravel13 Exceptions/JsonResource trong vendor đã cài; [Laravel13 errors](https://laravel.com/docs/13.x/errors), [PHP BCMath](https://www.php.net/manual/en/book.bc.php). Không thêm package mới.

## ADR-010 — P01.04 core và phân chia công việc song song

Người dùng yêu cầu tiếp tục phần owner, chừa phạm vi đã giao bên khác. Dataset proposal/validator thuộc EXT-001/002; UI map/form thuộc EXT-003/004 và các allowlist48EXT được giữ nguyên. Hợp đồng boundary địa giới xem ../collaboration/administrative-data-contract.md. Không seed địa giới giả hoặc chuyển sang P01.05 chỉ vì core identity đã chạy.

CoreFoundationSeeder bao transaction roles/demo identity, không thay DatabaseSeeder hoặc công bố task hoàn thành. Roles upsert theo slug, giữ ID/timestamps để không đứt FK. DemoUsersSeeder opt-in qua config, chỉ local/testing; password private12..72 bytes và không default. Chỉ tạo user mới từ config .test đã kiểm; user hiện hữu chỉ được coi replay khi còn active/notdeleted, password match và đúng một role. Không sửa profile/password/quyền/active của user cũ, không tái cấp Admin đã revoke; conflict rollback cả batch. Cart trống thiếu có thể tạo lại cho demo account đã được kiểm, không sửa giỏ đã có. UserRole vẫn fully guarded, seed gán server attributes tường minh. Không gửi mail/đụng kho/tiền/ledger.

Palette nằm trong config vì canonical không có bảng màu riêng. Giữ đúng12 key và màu Tailwind gốc, gồm brown=amber900 và lime=lime400. Shipping config có ngưỡng2triệu/fee30k-40k, code/version chưa đặt trước review dataset. Factory wardOf là synthetic fixture, liên kết parent province/version/date, không dataset production.

## ADR-011 — Chuẩn bị ledger/outbox độc lập, không đóng gate trước dataset

Người dùng yêu cầu tiếp tục phần chưa giao trong lúc chờ EXT. Cho phép chuẩn bị/test module độc lập như các PREP component, vẫn giữ gate P01.04/P01.05 pending. Nhánh mới xếp sau PR core#1; source cũ/UI/các allowlist EXT không sửa. Không thay canonical specification hoặc schema.

### Ledger persistence

postUnique yêu cầu transaction của caller; nested savepoint giúp insert/replay tách biệt mà không commit nghiệp vụ sớm. Deltas chỉ bốn cột signed BIGINT VND, strict int/digit string, kể cả PHP_INT_MIN/MAX; không float/abs(min)/money cent. Sources chỉ năm FK nullable/int dương. Kiểm shape7 loại bút toán và quan hệ unit→order/RMA/refund; không hardcode ID hoặc lấy catalog price/WAC hiện tại. OccurredAt do caller truyền từ snapshot, chuẩn UTC microsecond.

Same operation_key+canonical intent trả row gốc, không update timestamps/amount/date. Khác type/source/delta/occurredAt/key case trả IDEMPOTENCY_MISMATCH. Unique index MySQL là lớp bảo vệ race cuối; duplicate insert rollback savepoint rồi locking-read row thắng để không đọc lại snapshot cũ của REPEATABLE READ. Caller phải để conflict/DB exception đi ra ngoài transaction để rollback toàn nghiệp vụ. Deadlock retry của caller chỉ bọc SQL, không I/O ngoài DB.

Service là persistence primitive, không xác nhận delivered/paid/refund limits hoặc thay Policy/locks workflow. Caller phải kiểm các điều kiện đó trước post. Append-only là API service (không có update/delete); không tuyên bố có DB trigger cấm mọi SQL edit. Các bút toán bù dùng key mới và delta signed, giữ lịch sử gốc.

### Outbox persistence và delivery

Enqueue chỉ trong transaction, aggregate alias đóng ORDER/RMA/REFUND/USER và ID có thật. Dedup identity gồm exact key/event/alias/ID/canonical payload; available_at ban đầu và trạng thái vận hành không tham gia replay identity, lần đầu wins, không dùng key cũ để reschedule/đổi payload. Payload không nhận object/float, giới hạn depth32/64KiB, UTF8/known secret keys được kiểm. Producer tương lai phải whitelist safe DTO; generic key validation không thay security review từng message.

Worker claim chỉ khi transactionLevel0, root transaction có retry SQL3lần và FOR UPDATE. PENDING/FAILED đã tới available_at hoặc PROCESSING hết lease mới claim; SENT không claim. available_at inclusive, lease_until exclusive. Lease token là tuple(id,attempt,lease_until), ACK/fail conditional theo tuple và lease còn hạn nên worker cũ không sửa lease mới. Attempts tối đa10, timeout job60<queue retry90<lease120 mặc định; reclaim crash hết10attempt thành terminal FAILED. Payload poison từ ngoài service được đánh dấu INVALID_PAYLOAD và không giao.

Transport nằm ngoài transaction; registry singleton mặc định không handler, chỉ server code được đăng ký OutboxDelivery. ProcessOutbox chỉ serialize event ID và dispatch after commit. Missing handler/transport failure lưu mã lỗi tĩnh, không exception message/SQL/OTP/path. Failure retry backoff30×2^(attempt−1) cap3600, terminal reason không lặp vô hạn. Chưa có scheduler scan/dispatch hoặc transport thực; P10 sẽ nối các phần đó sau prerequisite và EXT mail template contract.

Không hứa exactly-once SMTP: crash sau send trước ACK có thể lặp. Ledger/stock uniqueness độc lập việc gửi mail. Outbox handler chỉ giao thông báo, không chạy lại nghiệp vụ tiền/kho. Không đăng ký transport động từ class name/payload hoặc báo success khi chưa gửi.

## ADR-012 — InventoryService PREP, sở hữu bucket và lịch sử giá vốn

Theo yêu cầu mới, chuẩn bị InventoryService độc lập của P01.05 trên nhánh xếp sau ledger/outbox PR#2. Đây không đóng gate P01.04/P01.05, không tích hợp PR EXT hoặc bật giao diện/routes/workflows. Schema/model/enum gốc giữ nguyên.

InventoryChange là intent readonly do server dựng; service tự tính delta theo action đóng, không nhận arbitrary counter updates. Caller giữ transaction và khóa actor/role, roots/items/units theo canonical trước khi gọi; service khóa toàn bộ variant IDs tăng dần, locking-read source/history hiện hành và ghi movement+balances+companion loss trong savepoint. Nếu một intent lỗi thì toàn batch rollback, kể cả khi caller bắt DomainException. Root transaction retry SQL-only do caller; không retry riêng một nửa thao tác đổi size hoặc I/O. Wrapper chỉ nhận nhóm action phù hợp; applyBatch phục vụ đa SKU/đổi size.

Bucket INT UNSIGNED cho phép4294967295 nhưng delta INT signed giới hạn quantity2147483647 mỗi movement. Kiểm bounds trước SQL và reserved<=stock. Reserve/release không đổi stock, dispatch giảm cả stock/reserved. Chỉ lấy held reserve của đúng Order/RMA; cumulative dispatched và held không vượt source quantity. Quarantine thuộc receipt nguồn, mỗi shipment-return-item/RMA tối đa một đôi đã nhận, không dựa một số dư toàn cục để dispose. RMA WarrantyRepair không sở hữu hàng cửa hàng; không movement. Manual quarantine writeoff yêu cầu nguồn thực, current Admin/reason và RMA đã đóng; open RMA không bị rút hàng bằng adjustment tùy ý.

Import/WAC/restock dùng Cost BCMath4; stock bao gồm reserve, quarantine không tham gia WAC. Outgoing basis là WAC tại lúc xuất; restock/writeoff dùng basis của receipt cách ly đã ghi, không lấy lại giá catalog hoặc snapshot đã sửa. Stock0 giữ last WAC. MANUAL_ADJUST_OUT tạo INVENTORY_LOSS một lần với key dẫn xuất SHA256 của operation key, source movement, order null, signed expense VND làm tròn tổng một lần; lỗi ledger/overflow rollback cả số dư/movement. Các disposition hậu mãi/giao trả cần workflow quyết định ledger để không double expense.

Operation key UNIQUE theo schema. Replay so exact key/action/variant/ref/quantity/input basis/actor/reason chuẩn hóa, trả row gốc không đổi timestamp/actor snapshot/basis đã dẫn xuất. Replay được xét trước current status/actor/catalog và kiểm drift vì không viết mới; không phục hồi quyền hoặc tạo thêm tài sản. Different intent, kể cả key đổi case trên collation case-insensitive, là IDEMPOTENCY_MISMATCH. Duplicate insert rollback savepoint trước current-read/replay; deadlock đi ra outer SQL transaction để retry toàn nghiệp vụ.

Reconciliation trước mỗi write reconstruct ba bucket, ownership reserve/quarantine và cost chain từ0, so current variant; mismatch chặn STATE_CONFLICT, không sửa lịch sử hoặc tạo movement bù tự động. Đây là correctness-first implementation đọc toàn history dưới lock: chưa tối ưu volume lớn, chưa thay scheduled report P04. Không có database trigger cấm mọi SQL edit; append-only là hợp đồng service và quyền ứng dụng.

Tests dùng MySQL thật. Ngoài transactional fixtures, InventoryConcurrencyTest giữ variant locks ở parent và chờ cả hai guarded child worker tới locking-read rồi nhả; mỗi child tạo snapshot cũ trước service. Kiểm oversell/replay/khác intent/cùng key khác SKU/batch đảo thứ tự/release-vs-dispatch với connection IDs khác nhau. Join/stop child trước cleanup chính xác fixture IDs; credential chỉ process env. Không cho phép suite độc lập chạy song song cùng test schema.
