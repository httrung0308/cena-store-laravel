# Cộng tác Cena Store

Bắt đầu bằng [README](README.md): cài lần đầu bằng `setup-project.ps1`, mở lại bằng hai lệnh; không cần dựng lại project mỗi lần sửa code.

## Quy trình chung

1. Lấy main mới nhất, tạo nhánh `feat/ten-task` hoặc `fix/ten-loi`; một PR cho một task. Collaborator làm trực tiếp trên nhánh repo; người ngoài fork rồi PR về `httrung0308/cena-store-laravel:main`.
2. Đọc `AGENTS.md`, yêu cầu của task và phần đặc tả liên quan. Với đợt7 chức năng, dùng [plan và phân quyền file](docs/collaboration/PLAN_7_CHUC_NANG.md); với EXT, dùng đúng [task/allowlist](docs/collaboration/EXTERNAL_TASKS_AND_PROMPTS.txt). Không cần đọc cả roadmap cho một sửa đổi nhỏ.
3. Kiểm tra phần đã thay đổi: PHP chạy test liên quan và Pint; UI build, kiểm trên desktop/mobile. Ghi kết quả thật, chưa chạy thì ghi `NOT RUN`. Không cần tự lặp lại mọi bước CI trước mỗi push; CI vẫn chạy đủ suite tích hợp.
4. Commit đúng file, push nhánh, mở PR. Mô tả ngắn: thay đổi gì, kiểm tra gì, còn thiếu gì. EXT ghi thêm task ID và BASE_SHA; không cần báo cáo nghiệm thu riêng.
5. Chờ mọi CI bắt buộc **xanh trên commit cuối**, người có quyền review rồi squash merge. CI fail/pending thì chưa merge. Sau cập nhật main/xử lý xung đột phải chờ CI lại. Không push trực tiếp vào main hoặc bỏ qua check.

Nếu chỉ làm một task trên máy riêng, dùng một clone là đủ. Nếu nhiều người/agent dùng chung máy hoặc làm song song, mỗi người dùng worktree/thư mục riêng, không reset/stash/checkout thư mục của người khác. X4 đang được Claude phụ trách: task khác không sửa code X4; nhận thay đổi của nhau qua main sau merge.

## Những điều cần giữ

- Giữ UI Cena và source cũ; tái sử dụng Blade/Tailwind3/Alpine3/EXT. Không đưa Next/Recoil/JWT/localStorage/Stripe runtime cũ vào Laravel.
- Sửa đúng phạm vi được giao. Models, migrations, lockfiles, Money/Clock/Resources/guard hoặc nền tiền-kho-quyền ngoài task cần trao đổi trước. Không thêm dependency hoặc thay hợp đồng chỉ để vượt lỗi.
- Test dùng MySQL8.4 riêng; không dùng DB development/SQLite/root/app user. Mỗi instance/máy riêng dùng cấu hình test riêng. Trên máy chung, không chạy hai suite cùng schema; dùng cặp Codex đã duyệt và config PHPUnit riêng hoặc chạy luân phiên. Xem README trước khi chạy test.
- Không commit env, mật khẩu/token, uploads, vendor/node_modules/build hoặc archive lồng. Dùng `git status` và add đúng file.
- Không bỏ test, tắt guard, đổi workflow hoặc dùng `pull_request_target`/secret để làm CI xanh giả. PR đầu từ fork có thể cần chủ repo duyệt chạy Actions.
- Nhật ký `docs/ai-progress.md`: chủ task được giao quyền cập nhật chỉ thêm mục cuối; giữ mục của người khác khi xung đột. EXT UI PREP không tự đánh dấu hoàn thành phase hoặc bật route trước dependency.

## Nếu chưa có quyền/tool

- Không có quyền ghi: fork và PR, hoặc nhờ chủ repo cấp collaborator; không cần quyền push main.
- ChatGPT Web không có terminal/quyền GitHub write: xuất patch + mô tả và ghi rõ `NOT RUN`, để người có môi trường chạy/apply/PR. Không bịa SHA, URL hoặc kết quả test.
- GitHub CLI là tùy chọn; GitHub web có đủ nút tạo PR, xem Checks và Squash and merge. Xem [README](README.md) để làm theo từng bước trong mục “Gửi code cho team”.
