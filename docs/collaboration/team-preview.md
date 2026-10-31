# EXT-048 — Demo team & smoke matrix (PREP, trung thực)

**Mốc quan sát chính:** upstream `main` SHA `d2771892dddc6aa20f8fce29dc11df59b3f566a7` (31/10/2026). **Head PREP:** `codex/ext-007-048-prep`, chưa tự tích hợp các component vào app đang chạy. Tham chiếu `docs/ai-progress.md`, `docs/specification.md` P00/P01/P02–P11, `docs/architecture/decisions.md` và `docs/reference/legacy-ui` (read-only).

## Lời dẫn demo 5–10 phút (không diễn tính năng chưa có)

**00:00–01:00 — Mở đầu.** Đây là demo *nền tảng* Cena Store, không phải web thương mại điện tử đủ workflow. Source Laravel13/PHP8.4/MySQL8.4 riêng, giữ logo Cena/font Montserrat/ảnh hero gốc. Công bố rõ trạng thái PREP so với backend thật.

**01:00–03:00 — Homepage.** Khi đã chạy server local đúng dependency và cấu hình, mở `/` (route `home` hiện có trong `routes/web.php`), quan sát hero “Sport, the smart choice.”, navbar/menu, collections/footers. Menu/cart là shell thông báo sắp mở; không nói có sản phẩm, giỏ/cart thật hoặc checkout. Browser trong phiên bàn giao này **NOT RUN**; người demo chỉ nhấp đường dẫn khi xác minh lại trên máy đang chạy.

**03:00–04:00 — Health và route.** `/up` là endpoint health framework đã cấu hình trong baseline; phải kiểm runtime `HTTP 200` trước demo. `/shoes`, `/login` và action auth/cart thật **chưa khai báo** trong `routes/web.php` tại BASE_SHA, không bấm rồi giả success. Bản xác minh tại đây chỉ đọc route source, chưa `route:list`/HTTP thật.

**04:00–06:00 — Cấu trúc domain.** Cho xem migration/schema tài liệu: nền P01.01 từng chứng minh 41 bảng gồm `migrations`; models/Resources P01.02 có Customer/Admin whitelist; P01.03 có Money/Cost/BusinessClock/API envelope. **Đó là kết quả baseline được ghi trong tài liệu**, không phải kiểm lại MySQL trên nhánh PREP; không gọi là báo cáo doanh thu, thanh toán hay RMA thực.

**06:00–07:30 — Test có bằng chứng.** Khi máy CI/local đầy đủ, dùng `php artisan test --compact` và URL workflow GitHub thực tế (nếu status PASS). Trong phiên EXT-007–048, chỉ Node tests riêng và lint được chạy trong workspace tài liệu; PHP feature tests/MySQL/browser **NOT RUN**. Không đọc lại con số cũ như thể test mới vừa PASS.

**07:30–09:00 — Hướng tích hợp.** Demo code PREP trên nhánh riêng qua review GitHub, không chạy như route. `EXT-001` chỉ nghiên cứu, dataset/manifest NOT CREATED; `EXT-002` validator NOT CREATED. Các task cart/checkout/refund/RMA/report/notifications yêu cầu backend/DTO owner, trạng thái `CONTRACT_PENDING`. Owner quyết định tích hợp và gate canonical.

## Route và bằng chứng

| URL | Source tại BASE_SHA | Runtime check trong phiên | Có thể nhắc trong demo? |
| --- | --- | --- | --- |
| `/` | `routes/web.php` khai báo `Route::view('/', 'storefront.home')->name('home')` | NOT RUN | Có, sau khi local đã khởi động |
| `/up` | Laravel health hook trong bootstrap nền (`README.md` nêu route) | NOT RUN | Có, chỉ khi HTTP 200 thật |
| `/shoes` | Chưa có route ở `routes/web.php` | NOT RUN | Không tuyên bố hoạt động |
| `/login` | Chưa có route ở `routes/web.php` | NOT RUN | Không tuyên bố hoạt động |

## Smoke matrix

`manual-smoke-matrix.csv` gồm **96 dòng**: 24 workflows × Guest/Customer/Staff/Admin; `Implementation_status_on_main_2026-10-31`, `Execution_status`, `Result` là các cột riêng, mặc định `NOT_IMPLEMENTED / NOT_RUN / NOT_RUN`. Không có PASS giả. Owner phân quyền, cập nhật kết quả từng dòng khi backend đã merge và test trên DB/cấu hình phù hợp; `NOT_IMPLEMENTED` ở đây nghĩa là workflow end-to-end chưa được tích hợp trên main baseline, không phủ nhận đã có schema/helper hoặc shell.

**Lưu ý đặc biệt:** số liệu 673 test/6848 assertions và 41 bảng là tài liệu baseline lịch sử; không được khẳng định vừa chạy lại trên PR tích lũy. Không có doanh thu, sản phẩm bán, khách hàng thật, chi phí hay giao dịch giả trong demo. Chưa hoàn thành phase P10/P11 chỉ vì có kịch bản và matrix.
