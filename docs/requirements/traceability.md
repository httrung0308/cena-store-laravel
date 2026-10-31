# Truy vết yêu cầu môn học và kế hoạch

Đối chiếu ngày 31/10/2026 từ rubric gốc **đã đọc**, không còn đánh dấu rubric chưa nhận. Bản trích text: `rubric-extract.txt`; SHA-256/source file: `source-manifest.json`. Đây là đối chiếu yêu cầu, không phải biên bản giảng viên duyệt đề tài hay cam kết điểm.

## Yêu cầu bắt buộc

| ID | Yêu cầu trong rubric | Cách đáp ứng / phase | Trạng thái tại P00 |
|---|---|---|---|
| R01 | Nhóm 3–5 sinh viên; đồ án 50% điểm kết thúc | Phân công tên thật, nhật ký đóng góp trong P11 | Chưa có danh sách nhóm; không tạo thành viên giả |
| R02 | Laravel mới nhất Composer hỗ trợ ở thời điểm làm | Laravel 13; composer.lock và runtime-versions.json | Đã cài/lock framework thực tế; major đúng kế hoạch |
| R03 | MySQL, Eloquent ORM, migrations, seeder/factory | P00 MySQL thật; P01 schema/models/factories/seed | P01.01 schema và P01.02 models/casts/factory cấu trúc đã có; seed nền/dataset/demo còn P01.04 |
| R04 | Blade; Bootstrap hoặc Tailwind | Blade + Tailwind3; port giao diện gốc | Đã có homepage/shell; UI nghiệp vụ còn các phase sau |
| R05 | Tối thiểu 2 roles, Laravel Auth + middleware | 3 roles canonical, pivot; P02 auth/active/role/Policy | Contract chốt; chưa có đăng nhập/quyền thật |
| R06 | CRUD đầy đủ ít nhất 2 bảng có liên kết | Category/Product/Color/Variant/Supplier tại P03 | Chưa triển khai; không tính các bảng queue là CRUD nghiệp vụ |
| R07 | Đăng ký/đăng nhập/đăng xuất/phân quyền | P02.01–P02.03; tests owner/admin/staff/customer | Đã định nghĩa; chưa nghiệm thu chức năng |
| R08 | Search và phân trang giữ từ khóa | P03 catalog query + P05 storefront; query string tests | Chưa triển khai |
| R09 | Validation FormRequest hoặc controller | FormRequest/DTO cho từng mutation; P02 trở đi | P01.03 có envelope/error contract 422/409/401/419/429/500 và field errors; FormRequest/mutation nghiệp vụ còn các phase sau |
| R10 | Flash message sau hành động | Blade flash/toast + Alpine trong P03/P05 | P01.03 có error bag và flash error/error_code cho Blade; toast/màn hình nghiệp vụ được nối theo UI gốc ở phase tương ứng |
| R11 | Phân tích bài toán, đối tượng, phạm vi | Specification §§1–2; decisions; P11 báo cáo | Đặc tả có; báo cáo học phần chưa xuất |
| R12 | ERD và Use Case | ERD/dictionary trong spec; cập nhật schema P01; P11 đóng gói | ERD dự kiến có, chưa coi là ERD as-built |
| R13 | Ảnh các chức năng, phân chia công việc | Evidence từng task; P11 đóng gói theo thành viên thật | Có ảnh UI nền khi kiểm tra; chưa có ảnh chức năng chưa làm |
| R14 | Report Times New Roman13, giãn dòng1.5, A4 | P11 DOCX/PDF, ít nhất10 trang nội dung, loại bìa/mục lục/tài liệu tham khảo | Chưa đến phase báo cáo |
| R15 | Hình/bảng có caption, số trang, tài liệu tham khảo | Kiểm render báo cáo tại P11 | Chưa thực hiện |
| R16 | Nộp code zip/GitHub, SQL export hoặc migrations+seed | P11; code+README+lockfiles+migrations+seed | Repo local đã tạo; chưa publish/commit/đóng gói bản cuối |
| R17 | README hướng dẫn cài/chạy; slides nếu bảo vệ | README P00; P11 hướng dẫn demo và slides theo yêu cầu thực tế | README có; slides chưa làm |
| R18 | Trung thực, ghi nguồn, hiểu mã và đóng góp | Attribution/UI license; nhật ký/task; P11 phân công thật | Giữ nguồn giao diện, không bịa kết quả/đóng góp |

## Phần mở rộng trong rubric

| Yêu cầu nâng cao | Liên kết kế hoạch | Trạng thái |
|---|---|---|
| Upload bằng Storage | P00 disks; P02 avatar; P03 product; P09 RMA private | Cấu hình nền; chưa có upload nghiệp vụ |
| AJAX/Fetch | P05 cart/drawer, P06 quote | Chưa có API cart/quote |
| API ngoài | P07 VNPay Sandbox, VietQR theo đặc tả | Chưa kết nối; không đưa key thật vào Git |
| Responsive | Giữ breakpoint source gốc, kiểm từng màn hình | Kiểm homepage P00; các màn hình sau kiểm theo task |
| Soft delete/phân quyền tùy chỉnh | P01 models/schema, P02 policies, P03/P08 nghiệp vụ | SoftDeletes/history/Resource guards đã có tại P01.02; middleware/Policy/workflow còn P02 trở đi |

## Trọng số rubric (không phải điểm đạt được)

| Mục | Trọng số | CLO | Bằng chứng sẽ cần |
|---|---:|---|---|
| Hình thức báo cáo | 10% | CLO1 | DOCX/PDF đúng quy cách, render kiểm |
| Nội dung báo cáo | 10% | CLO5 | Phân tích, thiết kế, ERD/Use Case, đánh giá |
| Giao diện | 30% | CLO2 | UI gốc được port đúng, responsive, ảnh/demo |
| Chức năng | 40% | CLO4 | Luồng thật, validation/quyền, test, dữ liệu demo |
| Làm việc nhóm | 10% | CLO3 | Phân công/đóng góp và khả năng giải thích thực tế |

## Phạm vi còn phụ thuộc giảng viên/người dùng

- Rubric quy định nộp sản phẩm/báo cáo **sau khi kết thúc học phần một tuần**. Ngày cụ thể, mốc đăng ký/duyệt đề tài và lịch bảo vệ vẫn là chỗ giảng viên điền: cần xác nhận khi chuẩn bị nộp; không ngăn việc triển khai P00.
- Tên nhóm, mã sinh viên, phân công chưa được cung cấp. Không giả định tác giả nguồn cũ là thành viên nhóm hiện tại.
- Yêu cầu ngày 31/10/2026: giữ giao diện source gốc. Ưu tiên yêu cầu này khi port P02/P03/P05 và các màn hình tiếp theo; không tự thay bằng template/thương hiệu UI khác.
- Giao diện tiếng Anh của nguồn gốc được giữ trong home; locale PHP `vi` phục vụ thông báo nghiệp vụ sau này. Không tự dịch hoặc thay slogan khi chưa được yêu cầu.

## Gate theo phase

| Phase | Kết quả cần chứng minh | P00 có quyền báo hoàn tất? |
|---|---|---|
| P00 | Contract, runtime, project riêng, lock/build, MySQL test an toàn, CI/clean install | Chỉ sau khi evidence từng task đầy đủ |
| P01 | 34 bảng miền, models/enums/Money/clock, dữ liệu nền, transaction/outbox | P01.01 schema, P01.02 models/Resources và P01.03 Money/clock/exception contract đã có; seed/dataset và transaction-outbox còn các task tiếp theo |
| P02 | Auth/roles/OTP/profile/địa chỉ thật | Chưa |
| P03 | UI gốc và CRUD catalog quản trị | Chưa |
| P04 | Nhập kho/giá vốn/ledger và tranh chấp MySQL | Chưa |
| P05 | Catalog và cart AJAX/merge thật trên UI gốc | Chưa |
| P06 | Pricing/coupon/checkout/reserve/cancel/expiry | Chưa |
| P07 | Payment/refund/IPN/replay/concurrency | Chưa |
| P08 | Order/shipment/tracking/chứng từ | Chưa |
| P09 | Review/RMA theo từng đôi, quyền và hạn mức | Chưa |
| P10 | Báo cáo/outbox/worker/scheduler/demo xuyên suốt | Chưa |
| P11 | Hồi quy, bảo mật, tài liệu/demo/bàn giao | Chưa |
