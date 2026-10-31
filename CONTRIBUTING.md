# Cộng tác Cena Store

Repo chuẩn: https://github.com/httrung0308/cena-store-laravel — main là nhánh tích hợp do chủ repo review/merge. Bên ngoài fork và gửi PR, không có quyền push trực tiếp vào main.

Đọc AGENTS.md, docs/specification.md, docs/architecture/decisions.md, docs/ai-progress.md và docs/collaboration/EXTERNAL_TASKS_AND_PROMPTS.txt trước khi viết code. Baseline hoàn tất P00/P01.01–P01.03; task canonical kế tiếp P01.04. Các dependency trong specification vẫn bắt buộc.

Một PR = một EXT task. Ghi BASE_SHA thật, phạm vi file, thay đổi hành vi, contract và lệnh đã chạy trong PR body. Không sửa nhật ký chung hoặc đánh dấu phase hoàn thành từ một PR UI chuẩn bị; chủ repo cập nhật khi merge/integrate. Không tạo báo cáo nghiệm thu riêng cho từng PR.

Các task PREP cho phép chuẩn bị component/module độc lập trước khi backend tồn tại; chúng không được nối vào routes/web.php, app.js hoặc UI đang chạy cho tới gate tương ứng. Task WAIT_CONTRACT chỉ code sau khi service/DTO/API upstream đã merge; đọc lại main trước khi bắt đầu, không đoán interface.

User yêu cầu giữ nguyên UI Cena gốc. docs/reference/legacy-ui chứa snapshot TSX/CSS để đối chiếu, không phải runtime hoặc hợp đồng bảo mật. Không sửa reference, public/legacy, migrations, lockfiles, Money/Clock/ErrorCode/Resource guards hay nền transaction/stock/payment/roles ngoài allowlist. Không port Next/Recoil/JWT/localStorage/Stripe/API cũ vào Laravel. Tái sử dụng Blade/Tailwind3/Alpine3 và helpers đã có.

Test MySQL8.4 thật, database cena_store_test qua user cena_test/cena_ci đã guard; không SQLite, không user root/app cho test. Không chạy các test process cùng schema đồng thời. Mỗi người/cloud task phải có instance/schema test riêng theo cấu hình chuẩn, không trỏ về máy tác giả. scripts/configure-env.php --ci chỉ dùng CI=true trên server MySQL disposable/trắng, không server dùng chung. Đọc README và workflow CI để cài môi trường; file env thật phải giữ ngoài Git.

Chạy narrow tests phù hợp, php vendor/bin/pint --dirty --format agent (full Pint nếu có file mới), npm ci và npm run build nếu đổi UI. Không thêm test chỉ sao chép HTML; kiểm hành vi/escaping/privacy và xem UI bằng mắt khi cần. Chủ repo chạy suite tích hợp trước merge. PR đầu từ fork có thể cần chủ repo duyệt workflow; không dùng pull_request_target hoặc cấp secret để vượt gate.

Nếu ChatGPT web không có terminal hoặc quyền GitHub write, xuất patch/tệp đầy đủ và PR description cho người có quyền áp dụng; ghi NOT RUN, không bịa commit SHA/URL/test pass. Có thể dùng môi trường Codex Cloud kết nối fork để thực sự chạy lệnh và mở PR: https://learn.chatgpt.com/docs/cloud . Khả năng và quyền của từng tài khoản cần kiểm tra trực tiếp.
