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
 * Local/testing only: nine shop articles (best sellers, trends, size guides, care) written through ArticleService,
 * so slugs, covers and publish dates follow the same rules as the admin form. Publish dates are spread over the
 * last two months; the last article stays a draft. Articles whose slug already exists are skipped.
 */
class NewsDemoSeeder extends Seeder
{
    /**
     * Days ago (null = draft), cover image in public/legacy/img, title, summary, body paragraphs.
     *
     * @var list<array{0: ?int, 1: string, 2: string, 3: string, 4: list<string>}>
     */
    private const ARTICLES = [
        [58, 'unisex', "Air Force 1 '07: vì sao 40 năm vẫn bán chạy", 'Đôi giày bóng rổ năm 1982 trở thành mẫu sneaker dễ phối nhất tủ đồ, và vẫn đứng đầu doanh số của cửa hàng.', [
            'Air Force 1 ra đời năm 1982 như một đôi giày bóng rổ, là mẫu đầu tiên của Nike có đệm Air giấu trong đế. Bốn mươi năm sau, nó gần như không còn xuất hiện trên sân đấu nhưng lại có mặt ở khắp nơi ngoài đường phố.',
            'Lý do đầu tiên là form giày. Mũi tròn, thân da trơn và đế dày khiến AF1 hợp với quần jeans, kaki lẫn quần short. Bản Triple White gần như không thể phối sai.',
            'Lý do thứ hai là độ bền. Da lớp trên dày, đế cupsole khâu chắc, đi hàng ngày hai đến ba năm vẫn giữ dáng nếu được vệ sinh đúng cách.',
            'Tại cửa hàng, Air Force 1 là mẫu có nhiều đôi giao thành công nhất trong quý. Size 40 đến 42 bán nhanh nhất, nên nếu bạn đi các size này hãy đặt sớm khi hàng về.',
            'Lưu ý về size: AF1 có form hơi rộng. Phần lớn khách nên chọn đúng size thường đi hoặc giảm nửa size nếu chân thon.',
        ]],
        [51, 'main', 'Cách chọn size giày Nike chuẩn khi mua online', 'Đo chân đúng cách và biết form từng dòng giày giúp bạn khỏi phải đổi size.', [
            'Đo chân vào buổi chiều, lúc bàn chân nở to nhất trong ngày. Đặt chân lên tờ giấy, đánh dấu gót và đầu ngón dài nhất rồi đo khoảng cách bằng thước.',
            'Cộng thêm khoảng 0,5 cm để có chỗ cho ngón chân, sau đó đối chiếu với bảng size của Nike. Nếu hai bàn chân lệch nhau, hãy chọn theo chân dài hơn.',
            'Mỗi dòng giày có form khác nhau. Air Force 1 và Air Max 90 hơi rộng, Dunk Low vừa chân, còn giày chạy bộ như Pegasus 41 nên chọn tăng nửa size vì chân sẽ nở khi chạy đường dài.',
            'Nếu chân bè hoặc mu bàn chân cao, ưu tiên các mẫu có thân lưới mềm như Revolution 7 hoặc Downshifter 13.',
            'Cửa hàng hỗ trợ đổi size trong 7 ngày cho giày chưa qua sử dụng, còn nguyên tem mác, nên bạn có thể yên tâm đặt online.',
        ]],
        [44, 'men', 'Pegasus 41 hay Invincible 3: chọn giày chạy bộ nào?', 'Hai mẫu giày chạy bộ bán chạy nhất cửa hàng phục vụ hai nhu cầu khác nhau.', [
            'Pegasus 41 là đôi giày chạy hằng ngày: đệm ReactX phản hồi tốt, trọng lượng vừa phải, dùng được cho cả chạy nhẹ lẫn tập tốc độ. Đây là lựa chọn an toàn cho người mới bắt đầu.',
            'Invincible 3 thuộc nhóm giày đệm tối đa. Lớp ZoomX dày giúp giảm chấn rất êm, phù hợp chạy dài, chạy phục hồi hoặc người có cân nặng lớn cần bảo vệ khớp gối.',
            'Nếu bạn chạy dưới 30 km mỗi tuần và muốn một đôi làm được nhiều việc, hãy chọn Pegasus 41. Nếu bạn chạy nhiều, thích cảm giác êm và ít quan tâm tốc độ, Invincible 3 đáng đầu tư hơn.',
            'Cả hai mẫu đều đang có giá khuyến mãi tại cửa hàng. Nhớ chọn tăng nửa size so với giày đi thường.',
        ]],
        [37, 'unisex', 'Dunk Low Panda: hiện tượng chưa hạ nhiệt', 'Phối màu trắng đen đơn giản nhưng luôn cháy hàng, và cách giữ đôi Dunk trắng luôn sạch.', [
            'Dunk Low Retro phối màu Panda trắng đen là một trong những mẫu được hỏi nhiều nhất tại cửa hàng. Sự đơn giản giúp nó hợp với gần như mọi trang phục.',
            'So với Air Force 1, Dunk Low có đế mỏng hơn, dáng thon gọn hơn và mũi giày thấp hơn. Nhiều khách đi thử cả hai đôi và chọn Dunk vì nhìn chân gọn hơn.',
            'Do nhu cầu cao, lô hàng bổ sung giữa kỳ của Dunk Low chưa về kịp, nên một số size đang còn rất ít. Hãy theo dõi trang sản phẩm để đặt khi hàng về.',
            'Mẹo giữ phần da trắng: lau bằng khăn ẩm ngay sau khi đi mưa, tránh để giày dưới nắng gắt vì da dễ ngả vàng.',
        ]],
        [30, 'women', 'Xu hướng giày nữ mùa cuối năm', 'Màu pastel, đế dày vừa phải và giày chạy bộ đi phố là ba xu hướng nổi bật.', [
            'Giày thể thao màu pastel tiếp tục được yêu thích. Air Max SC phối Pink Rise và Downshifter 13 màu Daybreak Purple là hai mẫu nữ bán tốt nhất tại cửa hàng tháng qua.',
            'Đế dày vừa phải, khoảng 3 đến 4 cm, giúp tăng chiều cao mà vẫn đi lại thoải mái cả ngày. Court Vision Low là ví dụ điển hình: dáng cổ điển, dễ phối với váy và quần ống rộng.',
            'Giày chạy bộ đi phố cũng đang lên ngôi. Nhiều khách nữ chọn một đôi giày chạy nhẹ để vừa tập thể dục buổi sáng vừa đi làm.',
            'Mẹo phối đồ: giày trắng hợp với mọi màu, giày pastel nên đi cùng trang phục trung tính như be, xám, trắng để không bị rối mắt.',
        ]],
        [23, 'main', 'Cách vệ sinh và bảo quản giày sneaker luôn như mới', 'Ba bước vệ sinh cơ bản và những lỗi thường gặp khiến giày nhanh hỏng.', [
            'Bước một: tháo dây và lót giày, dùng bàn chải mềm chải sạch bụi khô trên thân và đế.',
            'Bước hai: pha nước ấm với một chút dung dịch vệ sinh giày hoặc xà phòng trung tính, dùng bàn chải nhúng nước chà nhẹ theo vòng tròn. Với da lộn, chỉ dùng bàn chải khô và gôm chuyên dụng.',
            'Bước ba: lau lại bằng khăn ẩm sạch, nhét giấy báo vào trong giày để hút ẩm và giữ form, rồi phơi nơi thoáng gió, tránh nắng trực tiếp.',
            'Những lỗi nên tránh: giặt giày bằng máy giặt, sấy bằng máy sấy tóc ở nhiệt độ cao, hay để giày ướt trong hộp kín. Các cách này làm keo đế bong và da nứt.',
            'Bảo quản: để giày nơi khô ráo, có thể bỏ thêm gói hút ẩm vào hộp. Giày được bảo hành keo đế 6 tháng tại cửa hàng nếu có lỗi từ nhà sản xuất.',
        ]],
        [16, 'kid', 'Chọn giày cho bé đi học: êm, bền và dễ mang', 'Những điều cha mẹ nên kiểm tra khi mua giày thể thao cho trẻ.', [
            'Chân trẻ lớn nhanh, trung bình tăng nửa đến một size mỗi sáu tháng. Khi mua, hãy để dư khoảng 1 cm ở mũi giày, tương đương bề ngang ngón tay cái của người lớn.',
            'Ưu tiên giày có đế mềm, gập được ở phần mũi, giúp bé chạy nhảy tự nhiên. Star Runner 4 là mẫu nhẹ, đệm êm, rất hợp cho học sinh tiểu học.',
            'Quai dán hoặc dây chun giúp bé tự mang giày mà không cần buộc dây. Team Hustle D 11 có thiết kế này và đế cao su bám tốt trên sân trường.',
            'Kiểm tra giày của bé mỗi tháng. Nếu ngón chân chạm mũi giày hoặc gót bị đỏ, đã đến lúc lên size.',
        ]],
        [8, 'men', 'Air Max 90: biểu tượng đệm khí trở lại', 'Ô kính hiển thị đệm Air ở gót và lớp đế waffle làm nên chất riêng của Air Max 90.', [
            'Ra mắt năm 1990, Air Max 90 nổi bật với ô đệm khí nhìn thấy được ở gót và phần thân nhiều lớp da, lưới, da lộn đan xen.',
            'Đệm Air ở gót giúp giảm chấn khi đi bộ nhiều, còn đế waffle cho độ bám tốt. Đây là đôi giày hợp với người đi lại nhiều trong ngày.',
            'Phối màu White/Wolf Grey dễ phối đồ, trong khi Smoke Grey hợp với phong cách đường phố tối màu.',
            'Air Max 90 có form hơi rộng ở phần mũi. Nếu chân thon, bạn có thể giảm nửa size.',
        ]],
        [null, 'unisex', 'Lịch hàng về tháng tới (bản nháp)', 'Danh sách mẫu và size dự kiến về kho, đang chờ xác nhận từ nhà cung cấp.', [
            'Dunk Low Retro và Air Jordan 1 Low: chờ lô bổ sung từ nhà cung cấp, chưa có ngày chính xác.',
            'Các mẫu chạy bộ Pegasus 41 và Revolution 7 sẽ bổ sung size 40 đến 43.',
        ]],
    ];

    public function run(ArticleService $articles, BusinessClock $clock): void
    {
        if (! app()->environment(['local', 'testing']) || config('store.demo.enabled') !== true) {
            throw new LogicException('Explicit local/testing demo opt-in required.');
        }
        $admin = User::where('is_active', true)->whereHas('roles', fn ($query) => $query->where('slug', RoleSlug::Admin->value))->orderBy('id')->first()
            ?? throw new LogicException('Seed an active demo administrator first.');

        $timezone = (string) config('store.business_timezone', 'Asia/Ho_Chi_Minh');
        $today = $clock->now()->setTimezone($timezone)->startOfDay();
        $created = 0;
        try {
            foreach (self::ARTICLES as $index => [$daysAgo, $image, $title, $summary, $paragraphs]) {
                if (Article::query()->where('slug', Str::slug($title))->exists()) {
                    continue;
                }
                $clock->freeze($today->subDays($daysAgo ?? 0)->setTime(8 + $index % 10, 15 * ($index % 4))->utc());
                $path = public_path('legacy/img/'.$image.'.jpg');
                $articles->save($admin, null, [
                    'title' => $title,
                    'summary' => $summary,
                    'body' => implode("\n\n", $paragraphs),
                    'status' => $daysAgo === null ? ArticleStatus::Draft->value : ArticleStatus::Published->value,
                ], new UploadedFile($path, basename($path), 'image/jpeg', null, true));
                $created++;
            }
        } finally {
            $clock->unfreeze();
        }
        $this->command?->info("Đã tạo {$created} bài viết demo.");
    }
}
