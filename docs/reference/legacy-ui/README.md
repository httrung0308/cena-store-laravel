# Tham chiếu giao diện gốc

Snapshot chỉ đọc các component TSX/CSS từ frontend source cũ do người dùng cung cấp; manifest.json ghi SHA256 từng tệp. Nguồn/giấy phép: ../../../LICENSE-legacy-ui và ../../../LICENSE-react-icons tại root repo (đọc trực tiếp root khi đường dẫn trình xem khác).

Đây là tài liệu tham chiếu cách hiển thị, không được build/import như runtime Next/React. Không coi logic auth/JWT/cart/API/Stripe hoặc thông tin legacy trong các component là hợp đồng Laravel. docs/specification.md và các ADR quyết định nghiệp vụ, bảo mật, schema và DTO. Các import không được copy đầy đủ vì không cần chạy source tham chiếu.

UI mới dùng Blade/Tailwind3/Alpine3, giữ Cena/Montserrat/đen-trắng/bố cục/nội dung/ảnh gốc. Không sửa bản tham chiếu để làm khớp một thiết kế mới; submit component mới trong đường dẫn task được giao. Dùng assets local trong public/legacy khi có, không tải video/ảnh từ URL tùy ý hoặc bịa asset chưa được cấp.
