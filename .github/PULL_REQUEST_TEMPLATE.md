## Task và kết quả

- External task ID / phase canonical:
- BASE_SHA (upstream/main khi bắt đầu):
- Trigger trước thay đổi → hành vi sau thay đổi:
- Phụ thuộc đã merge / phần còn chờ hợp đồng:

## Phạm vi

- Tệp thay đổi và lý do:
- Contract/props/DTO sử dụng:
- Có sửa ngoài allowlist không? Nếu có, giải thích trước khi yêu cầu merge.

## Kiểm tra thực tế

- Lệnh và kết quả thực chạy (ghi NOT RUN nếu chưa chạy):
- PHP/Pint/build phù hợp thay đổi:
- MySQL8.4 cena_store_test / CI URL:
- Ảnh360/768/1440 và thao tác bàn phím nếu có UI:

## Tự kiểm trước review

- [ ] Chỉ một task, không gộp feature không liên quan.
- [ ] Không sửa schema/lockfiles/guard hoặc service tiền-kho-quyền ngoài task.
- [ ] Không làm giả API, seed stock/cost hoặc bỏ test để xanh.
- [ ] Giữ giao diện gốc, escape nội dung và không lộ cost/proof path/credential cho Customer.
- [ ] Không commit env/token/uploads/dependencies; không auto-merge.
- [ ] Các hạn chế và kiểm tra chưa chạy đã ghi rõ; task chuẩn chỉ hoàn tất sau tích hợp và review.
