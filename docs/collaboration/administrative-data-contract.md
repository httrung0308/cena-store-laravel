# Hợp đồng tích hợp EXT-001/002 với P01.04

Ngày chốt yêu cầu tích hợp:31/10/2026. Owner đang làm core roles/demo/config/factories trên nhánh `codex/p01-04-foundation-core`; các đường dẫn của EXT-001/002/003/004 và các EXT khác giữ nguyên cho bên cộng tác. Đọc lại `upstream/main` và PR core trước khi bắt đầu EXT-002.

## Phân công giữ nguyên

- EXT-001: nghiên cứu nguồn, tạo proposal JSON/manifest trong `database/data/proposals`, ghi nguồn trong `docs/collaboration/data-source-proposal.md`.
- EXT-002: `app/Support/AdministrativeDatasetValidator.php` và unit tests; validator thuần, không SQL/HTTP/seed.
- EXT-003: bản đồ giao diện/assets, không phụ thuộc dataset. EXT-004: component form, có thể làm độc lập.
- Owner: review proposal, adapter đọc/kiểm checksum, importer transaction, file canonical `database/data/administrative_units.json`/`manifest.json`, `FoundationSeeder` hoàn chỉnh và nối cấu hình địa giới/shipping. Owner không tự nghiên cứu lại hoặc ghi vào allowlist của các EXT đã giao.

## Dữ liệu chuẩn tại boundary importer

Đây là yêu cầu của dữ liệu sau chuẩn hóa, không bắt buộc đổi wire format của proposal nếu bên ngoài đã tạo format khác có thể ánh xạ rõ ràng. PR phải ghi mapping và schema cụ thể; không tự đổi schema DB.

| Trường | Kiểu và điều kiện |
|---|---|
| `code` | String1..20 ký tự; giữ số0 đầu; duy nhất trong cùng dataset version, kể cả khác level |
| `name` | String UTF8 không rỗng, tối đa150 ký tự; giữ tên chính thức của nguồn |
| `level` | String canonical `PROVINCE` hoặc `WARD`; không thêm huyện vào luồng bắt buộc |
| `parent_code` | Province:null; ward:string trỏ đến province có thật, trong cùng version; không trỏ đến ward hoặc chính nó |
| `dataset_version` | String1..50 ký tự, phải khớp manifest; được kế thừa từ metadata chung nếu proposal không lặp ở mỗi row |
| `valid_from` | Ngày lịch `YYYY-MM-DD` hợp lệ, lấy theo ngày hiệu lực nguồn; khớp metadata đã kiểm |
| `valid_to` | Null hoặc ngày hợp lệ không trước valid_from; không đoán ngày hết hiệu lực của mã |

Không cần và không được đưa ID tự tăng/parent_id của máy tác giả vào dataset. Importer sẽ resolve parent_id bằng `(dataset_version, code)` sau khi lưu province. Không tự bịa mã/đơn vị để đạt một số lượng tỉnh/ward mong muốn; số lượng phải lấy từ nguồn và đối chiếu manifest.

## Manifest và bytes của file

Manifest cần version, effective date, ngày truy cập, source URL(s), nguồn/giấy phép, SHA256 của file JSON và counts province/ward/total thực. URL nguồn cần truy cập được và hỗ trợ nội dung proposal; không chỉ dẫn trang tìm kiếm hoặc URL không đọc được rồi tuyên bố đã xác minh.

Đề nghị JSON UTF8 **không BOM, newline LF** và cách serialize ổn định. Tính SHA256 trên **bytes của file sẽ được commit**, không hash array sau decode/re-encode và không lấy hash của archive khác. Kiểm lại Git blob trong index/checkout trước khi giao PR để phát hiện CRLF/LF làm đổi checksum. Manifest không tự hash chính nó.

Proposal cần chỉ rõ `hcm_province_code` cùng dataset version, chứng minh code đó là province thuộc bộ dữ liệu được chọn. Owner chỉ đặt `STORE_HCM_PROVINCE_CODE`/`STORE_LOCATION_DATASET_VERSION` sau review; không dùng tên gần giống, không đoán ID hoặc hardcode một code từ dataset cũ. Phí30.000/40.000 và ngưỡng miễn phí2.000.000 VND đã có trong `config/store.php`; code địa giới vẫn đang để trống.

## Boundary validator EXT-002

Trước khi code, ghi rõ public API trong PR hoặc đề xuất schema EXT-001 để owner xác nhận. Interface đề nghị: nhận normalized units và manifest, trả `valid:bool` cùng `errors:list<{path,code,message}>`; checksum nhận bytes file và expected SHA256 ở boundary riêng. Nếu đã có API tương đương, mô tả mapping để owner viết adapter; không cần sửa lớp chỉ để đổi tên method.

Validator phải phát hiện duplicate, sai level, province có parent, ward mồ côi/parent là ward/cross-version, code bị ép number, metadata thiếu/sai, ngày không hợp lệ và counts/checksum không khớp. Chạy được offline với fixture **synthetic ghi nhãn rõ**; fixture kiểm thử không phải dataset địa giới đã xác minh. Không cần gọi database hoặc import trong validator.

## Điều kiện hoàn tất P01.04

Hiện `CoreFoundationSeeder` chỉ seed roles và demo identity opt-in; không seed/import địa giới, không tạo catalog/stock/ledger. `DatabaseSeeder` chưa được đổi sang FoundationSeeder để tránh báo thành công giả khi dataset chưa sẵn sàng.

Gate chỉ PASS sau khi EXT-001/002 được review, dữ liệu chính thức được nhập bằng importer của owner, tỉnh/ward/parent/version/checksum đúng, cấu hình shipping resolve đúng code và seed hoàn chỉnh chạy lại hai lần không nhân bản. Phải giữ dữ liệu lịch sử/snapshot và kiểm rollback khi importer lỗi. Không làm P01.05 trước gate này.
