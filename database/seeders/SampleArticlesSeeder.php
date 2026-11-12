<?php

namespace Database\Seeders;

use App\Enums\ArticleStatus;
use App\Enums\RoleSlug;
use App\Models\Article;
use App\Models\User;
use App\Services\ArticleService;
use App\Support\BusinessClock;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use LogicException;

/**
 * Local/testing only: 13 sample articles (11 published across 2+ pages, 1 draft, 1 hidden)
 * covering size guides, styling, cleaning/care, and need-based shoe selection.
 * Idempotent by deterministic slug; never modifies existing articles or creates fake feedback.
 */
class SampleArticlesSeeder extends Seeder
{
    /**
     * @var list<array{status: string, days_ago: ?int, image: string, title: string, summary: string, body: list<string>}>
     */
    private const ARTICLES = [
        [
            'status' => 'PUBLISHED',
            'days_ago' => 30,
            'image' => 'main',
            'title' => 'Đo size giày chuẩn tại nhà trong 3 phút',
            'summary' => 'Hướng dẫn đo chiều dài bàn chân buổi chiều và cộng khoảng dư ngón chân để hạn chế đổi size khi mua online.',
            'body' => [
                '## Chuẩn bị trước khi đo',
                'Đặt một tờ giấy trắng sát mép tường, chuẩn bị bút và thước kẻ. Nên đo vào **cuối ngày** khi bàn chân giãn nở tối đa.',
                '- Mang đúng loại tất bạn thường đi cùng giày thể thao.',
                '- Đứng thẳng hai chân để trọng lượng dồn đều lên bàn chân.',
                '- Đo cả hai bên và chọn theo bàn chân dài hơn.',
                '> Cộng thêm 0,5 cm cho giày đi hàng ngày và 0,7–1 cm cho giày chạy bộ đường dài.',
            ],
        ],
        [
            'status' => 'PUBLISHED',
            'days_ago' => 27,
            'image' => 'unisex',
            'title' => 'Bí quyết chọn size cho người có bàn chân bè, mu bàn chân cao và hay bị kích mũi khi đi bộ đường dài',
            'summary' => 'Phân tích form giày mũi tròn, chất liệu lưới co giãn và cách đọc bảng size nửa (half-size) cho bàn chân bè ngang.',
            'body' => [
                '## Vì sao chân bè dễ bị đau ngón út?',
                'Phần lớn khách hàng chỉ quan tâm chiều dài (cm) mà bỏ qua **độ rộng phần ức chân** và độ ôm cổ giày.',
                '1. Ưu tiên thân giày bằng vải lưới *engineered mesh* hoặc da mềm thay vì mũi hẹp cứng.',
                '2. Với các dòng form thon, cân nhắc tăng **0.5 size** để giảm áp lực hai bên má chân.',
                '3. Nới lỏng hai hàng dây gần mũi trước khi thắt nút cổ chân.',
            ],
        ],
        [
            'status' => 'PUBLISHED',
            'days_ago' => 24,
            'image' => 'women',
            'title' => 'Phối đồ với sneaker trắng tối giản đi học và đi làm',
            'summary' => 'Năm công thức phối giày trắng với quần ống suông, jeans xanh và chân váy chữ A gọn gàng, lịch sự.',
            'body' => [
                '## Giữ tổng thể cân đối theo tỷ lệ ống quần',
                'Sneaker đế phẳng hợp với quần ống đứng chạm mắt cá, còn sneaker đế dày vừa phải tạo điểm nhấn tốt với quần ống rộng.',
                '- **Đi làm:** Áo sơ mi trơn, quần tây tối màu và sneaker da trắng sạch.',
                '- **Đi học:** Áo thun cotton, quần kaki be hoặc denim và tất cổ trung.',
                '> Tránh để gấu quần quá dài phủ kín cổ giày làm tổng thể trông nặng nề.',
            ],
        ],
        [
            'status' => 'PUBLISHED',
            'days_ago' => 21,
            'image' => 'men',
            'title' => 'Phối màu giày trung tính cho tủ đồ streetwear năng động cuối tuần',
            'summary' => 'Cách kết hợp giày tông xám, đen, trắng ngà với áo khoác nhẹ và quần cargo mà không bị rối mắt.',
            'body' => [
                '## Quy tắc 3 màu khi phối đồ',
                'Giữ trang phục trong tối đa **ba tông màu** giúp đôi giày trở thành điểm neo thị giác rõ ràng.',
                '- Giày xám khói đi cùng quần đen và áo hoodie sáng màu.',
                '- Giày trắng ngà hợp với tông xanh olive, nâu đất và denim wash.',
            ],
        ],
        [
            'status' => 'PUBLISHED',
            'days_ago' => 18,
            'image' => 'main',
            'title' => 'Vệ sinh giày vải lưới đúng cách để không bị xù sợi và ố vàng keo đế sau khi đi mưa',
            'summary' => 'Quy trình 4 bước làm sạch thân mesh, khử mùi lót giày và phơi đúng cách tránh bung keo.',
            'body' => [
                '## Không cho giày vào máy giặt',
                'Lực vắt mạnh làm biến dạng khung gót và rách lớp keo ép nhiệt quanh mũi giày.',
                '1. Tháo dây và lót rời, gõ nhẹ để rơi bớt đất khô.',
                '2. Pha nước ấm với dung dịch trung tính, dùng bàn chải lông mềm chải theo chiều sợi vải.',
                '3. Dùng khăn giấy trắng bọc kín bề mặt khi phơi nơi râm mát để hút vết ố vàng.',
            ],
        ],
        [
            'status' => 'PUBLISHED',
            'days_ago' => 15,
            'image' => 'unisex',
            'title' => 'Chăm sóc giày da trơn và da lộn vào mùa mưa ẩm',
            'summary' => 'Phân biệt dụng cụ vệ sinh cho da trơn và da lộn (suede), kèm mẹo dùng gói hút ẩm khi cất hộp.',
            'body' => [
                '## Da lộn kỵ nước trực tiếp',
                'Với *suede*, chỉ dùng bàn chải khô chuyên dụng và gôm tẩy vết bẩn; tuyệt đối không ngâm nước.',
                '- **Da trơn:** Lau ngay bằng khăn ẩm mềm sau khi dính bùn, sau đó lau khô lại.',
                '- **Bảo quản:** Nhét giấy không mực giữ form và đặt túi hút ẩm trong tủ giày.',
            ],
        ],
        [
            'status' => 'PUBLISHED',
            'days_ago' => 12,
            'image' => 'men',
            'title' => 'Chọn giày chạy bộ cho người mới bắt đầu 5km mỗi tuần',
            'summary' => 'Những tiêu chí quan trọng về độ êm gót, độ thoáng khí và độ bám đường nhựa khi mới làm quen chạy bộ.',
            'body' => [
                '## Ưu tiên đệm ổn định hơn trọng lượng siêu nhẹ',
                'Ở giai đoạn đầu, khớp cổ chân và đầu gối cần lớp đệm đàn hồi tốt để giảm chấn động lặp lại.',
                '- Chọn giày có phần gót ôm chắc, không trượt gót khi tiếp đất.',
                '- Đế cao su có rãnh thoát nước tốt nếu bạn thường chạy sáng sớm.',
            ],
        ],
        [
            'status' => 'PUBLISHED',
            'days_ago' => 10,
            'image' => 'women',
            'title' => 'Tiêu chí chọn giày đi bộ và đứng làm việc suốt 8 tiếng không mỏi gót',
            'summary' => 'So sánh độ cứng đế giữa, hỗ trợ vòm bàn chân và độ thông thoáng cho người di chuyển nhiều trong ngày.',
            'body' => [
                '## Đế quá mềm chưa chắc đã tốt khi đứng lâu',
                'Đế quá lún khiến bàn chân phải gồng liên tục để giữ thăng bằng. Hãy tìm độ đàn hồi **vừa phải** và nâng đỡ vòm chân.',
                '- Kiểm tra phần đế giữa không bị vặn xoắn quá dễ dàng.',
                '- Thử giày cùng loại tất mỏng bạn mang đi làm hàng ngày.',
            ],
        ],
        [
            'status' => 'PUBLISHED',
            'days_ago' => 8,
            'image' => 'kid',
            'title' => 'Chọn giày thể thao cho bé đi học: nhẹ, bám sân và dễ tự mang',
            'summary' => 'Gợi ý kiểu quai dán, mũi giày gập linh hoạt và cách kiểm tra độ chật định kỳ mỗi 3 tháng cho trẻ nhỏ.',
            'body' => [
                '## Chân trẻ phát triển rất nhanh',
                'Cha mẹ nên kiểm tra bằng cách ấn nhẹ đầu mũi giày khi bé đứng thẳng; khoảng trống lý tưởng khoảng **1 cm**.',
                '- Quai dán hoặc dây chun giúp bé tự mang nhanh trong giờ thể dục.',
                '- Đế cao su có độ bám tốt giảm trơn trượt trên sân trường.',
            ],
        ],
        [
            'status' => 'PUBLISHED',
            'days_ago' => 5,
            'image' => 'unisex',
            'title' => 'Khi nào nên thay mới đôi giày chạy bộ của bạn?',
            'summary' => 'Nhận biết 4 dấu hiệu đế giữa mất đàn hồi và mặt đế mòn lệch để bảo vệ cổ chân kịp thời.',
            'body' => [
                '## Đừng chỉ nhìn vẻ ngoài thân giày',
                'Thân vải có thể vẫn rất mới nhưng lớp bọt đệm bên trong thường giảm hiệu quả sau **500–700 km** sử dụng.',
                '- Xuất hiện nhiều nếp nhăn sâu dọc thành đế giữa.',
                '- Cảm giác đau nhức lòng bàn chân hoặc ống đồng sau quãng chạy quen thuộc.',
            ],
        ],
        [
            'status' => 'PUBLISHED',
            'days_ago' => 2,
            'image' => 'main',
            'title' => 'Mẹo xử lý giày mới hơi kích gót trong những ngày đầu mang',
            'summary' => 'Cách điều chỉnh dây buộc khóa gót (heel lock) và chọn tất dày vừa để hạn chế phồng rộp gót chân.',
            'body' => [
                '## Thử buộc kiểu heel lock trước khi đổi size',
                'Nếu chiều dài mũi giày đã vừa nhưng gót hơi xê dịch gây cọ xát, kỹ thuật **runner knot** giúp cố định cổ chân rất hiệu quả.',
                '- Đi trong nhà 15–20 phút trước khi mang ra đường.',
                '- Nếu vẫn cấn đau rõ rệt, liên hệ đổi size trong thời hạn hỗ trợ 7 ngày.',
            ],
        ],
        [
            'status' => 'DRAFT',
            'days_ago' => null,
            'image' => 'men',
            'title' => 'So sánh các dòng đệm bọt phổ biến cho giày tập gym (bản nháp)',
            'summary' => 'Bản nháp nội bộ tổng hợp độ ổn định đế phẳng khi tập tạ và bài tập bật nhảy.',
            'body' => [
                '## Nội dung đang biên tập',
                'Đang bổ sung bảng đối chiếu độ cứng đế giữa và độ bám sàn phòng tập.',
            ],
        ],
        [
            'status' => 'HIDDEN',
            'days_ago' => 14,
            'image' => 'women',
            'title' => 'Thông báo chương trình ưu đãi bảo dưỡng giày cũ (đã ẩn)',
            'summary' => 'Bài viết mẫu ở trạng thái đã ẩn để kiểm tra phân quyền hiển thị giữa trang công khai và trang quản trị.',
            'body' => [
                '## Bài viết đã tạm ẩn',
                'Nội dung này chỉ hiển thị trong trang quản trị và trả về 404 ở storefront công khai.',
            ],
        ],
    ];

    public function run(ArticleService $articles, BusinessClock $clock): void
    {
        if (! app()->environment(['local', 'testing']) || config('store.demo.enabled') !== true) {
            throw new LogicException('Explicit local/testing demo opt-in required.');
        }

        $admin = User::where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('slug', RoleSlug::Admin->value))
            ->orderBy('id')
            ->first()
            ?? throw new LogicException('Seed an active demo administrator first.');

        $timezone = (string) config('store.business_timezone', 'Asia/Ho_Chi_Minh');
        $today = $clock->now()->setTimezone($timezone)->startOfDay();
        $created = 0;

        try {
            foreach (self::ARTICLES as $index => $entry) {
                $slug = Str::slug($entry['title']);
                if (Article::query()->where('slug', $slug)->exists()) {
                    continue;
                }

                $daysAgo = $entry['days_ago'] ?? 0;
                $clock->freeze($today->subDays($daysAgo)->setTime(8 + ($index % 10), 15 * ($index % 4))->utc());

                $path = public_path('legacy/img/'.$entry['image'].'.jpg');
                $targetStatus = $entry['status'] === 'DRAFT'
                    ? ArticleStatus::Draft->value
                    : ArticleStatus::Published->value;

                $saved = $articles->save(
                    $admin,
                    null,
                    [
                        'title' => $entry['title'],
                        'summary' => $entry['summary'],
                        'body' => implode("\n\n", $entry['body']),
                        'status' => $targetStatus,
                    ],
                    new UploadedFile($path, basename($path), 'image/jpeg', null, true),
                );

                if ($entry['status'] === 'HIDDEN') {
                    $articles->hide($saved);
                }

                $created++;
            }
        } finally {
            $clock->unfreeze();
        }

        $this->command?->info("Đã tạo {$created} bài viết mẫu.");
    }
}
