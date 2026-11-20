<?php

namespace Database\Seeders;

use App\Enums\ClassificationGroup;
use App\Enums\ProductImageSourceType;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductManagementService;
use App\Services\StockImportService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use LogicException;

class ProductClassificationDemoSeeder extends Seeder
{
    private const CLASSIFICATIONS = [
        // SILHOUETTE (Kiểu dáng)
        [
            'name' => 'Sneaker',
            'slug' => 'sneaker',
            'classification_group' => ClassificationGroup::Silhouette,
            'sort_order' => 1,
            'is_active' => true,
            'description' => 'Giày thể thao đế bằng, kiểu dáng kinh điển và linh hoạt cho mọi hoạt động.',
        ],
        [
            'name' => 'Boots',
            'slug' => 'boots',
            'classification_group' => ClassificationGroup::Silhouette,
            'sort_order' => 2,
            'is_active' => true,
            'description' => 'Giày phom cao, cổ cứng cáp, bảo vệ chân tối ưu trong thời tiết khắc nghiệt.',
        ],
        [
            'name' => 'Sandals',
            'slug' => 'sandals',
            'classification_group' => ClassificationGroup::Silhouette,
            'sort_order' => 3,
            'is_active' => true,
            'description' => 'Sandal quai hậu năng động, thông thoáng tuyệt đối cho mùa hè.',
        ],
        [
            'name' => 'Loafers',
            'slug' => 'loafers',
            'classification_group' => ClassificationGroup::Silhouette,
            'sort_order' => 4,
            'is_active' => true,
            'description' => 'Giày lười xỏ chân êm ái, thanh lịch khi đi làm và tiện dụng hàng ngày.',
        ],
        [
            'name' => 'Giày Cổ Cao',
            'slug' => 'high-top',
            'classification_group' => ClassificationGroup::Silhouette,
            'sort_order' => 5,
            'is_active' => true,
            'description' => 'Giày ôm trọn cổ chân, đậm chất bóng rổ đường phố và cá tính.',
        ],
        [
            'name' => 'Giày Đi Tuyết',
            'slug' => 'snow-boots',
            'classification_group' => ClassificationGroup::Silhouette,
            'sort_order' => 99,
            'is_active' => false, // Nhãn inactive để kiểm thử
            'description' => 'Nhãn tạm ngưng phục vụ kiểm thử tính năng lọc và bảo vệ dữ liệu.',
        ],

        // ACTIVITY (Mục đích sử dụng)
        [
            'name' => 'Chạy bộ',
            'slug' => 'running',
            'classification_group' => ClassificationGroup::Activity,
            'sort_order' => 1,
            'is_active' => true,
            'description' => 'Thiết kế trợ lực đệm êm, hấp thụ chấn động và tối ưu cho từng sải chân.',
        ],
        [
            'name' => 'Thời trang / Dạo phố',
            'slug' => 'lifestyle',
            'classification_group' => ClassificationGroup::Activity,
            'sort_order' => 2,
            'is_active' => true,
            'description' => 'Dễ dàng phối đồ cùng nhiều phong cách, thoải mái bước đi suốt ngày dài.',
        ],
        [
            'name' => 'Tập luyện & Gym',
            'slug' => 'training',
            'classification_group' => ClassificationGroup::Activity,
            'sort_order' => 3,
            'is_active' => true,
            'description' => 'Đế bám vững chắc, giữ thăng bằng tối đa khi nâng tạ và vận động cường độ cao.',
        ],
        [
            'name' => 'Đi làm',
            'slug' => 'work',
            'classification_group' => ClassificationGroup::Activity,
            'sort_order' => 4,
            'is_active' => true,
            'description' => 'Kiểu dáng nhã nhặn, tôn phom đứng đắn và mang lại sự êm dịu nơi công sở.',
        ],
        [
            'name' => 'Bóng rổ',
            'slug' => 'basketball',
            'classification_group' => ClassificationGroup::Activity,
            'sort_order' => 5,
            'is_active' => true,
            'description' => 'Hỗ trợ bật nhảy, bám sân nhanh và chống lật cổ chân khi đổi hướng đột ngột.',
        ],
    ];

    /**
     * Danh mục sản phẩm demo mở rộng (28 mẫu trải đều 4 nhóm đối tượng).
     *
     * @var list<array{name: string, slug: string, group: string, base_price: int, promotion_price: ?int, is_featured: int, in_stock: bool, color: string, tags: list<string>}>
     */
    private const DEMO_CATALOG = [
        // NAM (MEN) - 7 mẫu
        [
            'name' => 'Nike Pegasus 41 Men Road Running',
            'slug' => 'demo-pegasus-41-men',
            'group' => 'men',
            'base_price' => 3_590_000,
            'promotion_price' => 2_990_000,
            'is_featured' => 1,
            'in_stock' => true,
            'color' => 'blue',
            'tags' => ['sneaker', 'running'],
        ],
        [
            'name' => 'Nike Invincible 3 Men Cushion Max',
            'slug' => 'demo-invincible-3-men',
            'group' => 'men',
            'base_price' => 4_890_000,
            'promotion_price' => null,
            'is_featured' => 1,
            'in_stock' => true,
            'color' => 'white',
            'tags' => ['sneaker', 'running'],
        ],
        [
            'name' => 'Nike Metcon 9 Men Training & Gym',
            'slug' => 'demo-metcon-9-men',
            'group' => 'men',
            'base_price' => 3_890_000,
            'promotion_price' => 3_190_000,
            'is_featured' => 0,
            'in_stock' => true,
            'color' => 'black',
            'tags' => ['sneaker', 'training'],
        ],
        [
            'name' => 'Nike ACG Mountain Fly 2 Low GORE-TEX',
            'slug' => 'demo-acg-mountain-fly-men',
            'group' => 'men',
            'base_price' => 4_490_000,
            'promotion_price' => null,
            'is_featured' => 0,
            'in_stock' => true,
            'color' => 'brown',
            'tags' => ['boots', 'lifestyle'],
        ],
        [
            'name' => 'Nike Giannis Immortality 3 Basketball',
            'slug' => 'demo-giannis-immortality-men',
            'group' => 'men',
            'base_price' => 2_390_000,
            'promotion_price' => 1_890_000,
            'is_featured' => 0,
            'in_stock' => true,
            'color' => 'green',
            'tags' => ['high-top', 'basketball'],
        ],
        [
            'name' => 'Giày Chạy Bộ Nam Nike Air Zoom Alphafly NEXT% 3 Ekiden Special Edition Phiên Bản Giới Hạn',
            'slug' => 'demo-alphafly-3-ekiden-long-name',
            'group' => 'men',
            'base_price' => 8_190_000,
            'promotion_price' => null,
            'is_featured' => 1,
            'in_stock' => true,
            'color' => 'yellow',
            'tags' => ['sneaker', 'running', 'lifestyle'],
        ],
        [
            'name' => 'Nike Calm Mule Men Slip-On',
            'slug' => 'demo-calm-mule-men',
            'group' => 'men',
            'base_price' => 1_790_000,
            'promotion_price' => null,
            'is_featured' => 0,
            'in_stock' => false, // Hết hàng phục vụ test filter in_stock
            'color' => 'black',
            'tags' => ['loafers', 'work'],
        ],

        // NỮ (WOMEN) - 7 mẫu
        [
            'name' => 'Nike Air Max SC Women Pastel Pink',
            'slug' => 'demo-air-max-sc-women',
            'group' => 'women',
            'base_price' => 2_390_000,
            'promotion_price' => 1_890_000,
            'is_featured' => 1,
            'in_stock' => true,
            'color' => 'pink',
            'tags' => ['sneaker', 'lifestyle'],
        ],
        [
            'name' => 'Nike Downshifter 13 Women Daybreak',
            'slug' => 'demo-downshifter-13-women',
            'group' => 'women',
            'base_price' => 1_990_000,
            'promotion_price' => null,
            'is_featured' => 1,
            'in_stock' => true,
            'color' => 'purple',
            'tags' => ['sneaker', 'running'],
        ],
        [
            'name' => 'Nike Court Vision Low Nature Women',
            'slug' => 'demo-court-vision-women',
            'group' => 'women',
            'base_price' => 2_090_000,
            'promotion_price' => 1_690_000,
            'is_featured' => 0,
            'in_stock' => true,
            'color' => 'white',
            'tags' => ['sneaker', 'work', 'lifestyle'],
        ],
        [
            'name' => 'Nike Icon Classic Platform Sandals Women',
            'slug' => 'demo-icon-classic-sandals-women',
            'group' => 'women',
            'base_price' => 1_890_000,
            'promotion_price' => null,
            'is_featured' => 0,
            'in_stock' => true,
            'color' => 'brown',
            'tags' => ['sandals', 'lifestyle'],
        ],
        [
            'name' => 'Nike Free Metcon 5 Women Fitness',
            'slug' => 'demo-free-metcon-5-women',
            'group' => 'women',
            'base_price' => 3_590_000,
            'promotion_price' => 2_890_000,
            'is_featured' => 0,
            'in_stock' => true,
            'color' => 'gray',
            'tags' => ['sneaker', 'training'],
        ],
        [
            'name' => 'Nike Tanjun Ease Loafers Women',
            'slug' => 'demo-tanjun-ease-women',
            'group' => 'women',
            'base_price' => 1_690_000,
            'promotion_price' => null,
            'is_featured' => 0,
            'in_stock' => true,
            'color' => 'black',
            'tags' => ['loafers', 'work'],
        ],
        [
            'name' => 'Nike Structure 25 Women Classic Unclassified',
            'slug' => 'demo-structure-25-women-unclassified',
            'group' => 'women',
            'base_price' => 3_290_000,
            'promotion_price' => null,
            'is_featured' => 0,
            'in_stock' => true,
            'color' => 'white',
            'tags' => [], // Không gắn nhãn phục vụ test tương thích ngược
        ],

        // TRẺ EM (KIDS) - 7 mẫu
        [
            'name' => 'Nike Flex Runner 3 Kids Slip-On',
            'slug' => 'demo-flex-runner-3-kids',
            'group' => 'kids',
            'base_price' => 1_290_000,
            'promotion_price' => 990_000,
            'is_featured' => 1,
            'in_stock' => true,
            'color' => 'blue',
            'tags' => ['sneaker', 'running'],
        ],
        [
            'name' => 'Nike Star Runner 4 Kids Sport',
            'slug' => 'demo-star-runner-4-kids',
            'group' => 'kids',
            'base_price' => 1_390_000,
            'promotion_price' => null,
            'is_featured' => 0,
            'in_stock' => true,
            'color' => 'red',
            'tags' => ['sneaker', 'running'],
        ],
        [
            'name' => 'Nike Sunray Protect 3 Kids Water Sandals',
            'slug' => 'demo-sunray-protect-kids',
            'group' => 'kids',
            'base_price' => 1_090_000,
            'promotion_price' => 890_000,
            'is_featured' => 0,
            'in_stock' => true,
            'color' => 'black',
            'tags' => ['sandals', 'lifestyle'],
        ],
        [
            'name' => 'Nike Court Borough Low Recraft Kids',
            'slug' => 'demo-court-borough-kids',
            'group' => 'kids',
            'base_price' => 1_490_000,
            'promotion_price' => null,
            'is_featured' => 0,
            'in_stock' => true,
            'color' => 'white',
            'tags' => ['sneaker', 'lifestyle'],
        ],
        [
            'name' => 'Nike Team Hustle D 11 Basketball Kids',
            'slug' => 'demo-team-hustle-kids',
            'group' => 'kids',
            'base_price' => 1_590_000,
            'promotion_price' => null,
            'is_featured' => 0,
            'in_stock' => true,
            'color' => 'black',
            'tags' => ['high-top', 'basketball'],
        ],
        [
            'name' => 'Nike Kawa Slide Dép Trẻ Em',
            'slug' => 'demo-kawa-slide-kids',
            'group' => 'kids',
            'base_price' => 690_000,
            'promotion_price' => 490_000,
            'is_featured' => 0,
            'in_stock' => true,
            'color' => 'blue',
            'tags' => ['sandals', 'lifestyle'],
        ],
        [
            'name' => 'Nike Revolution 7 Kids Unclassified',
            'slug' => 'demo-revolution-7-kids-unclassified',
            'group' => 'kids',
            'base_price' => 1_190_000,
            'promotion_price' => null,
            'is_featured' => 0,
            'in_stock' => true,
            'color' => 'gray',
            'tags' => [], // Không gắn nhãn
        ],

        // UNISEX - 7 mẫu
        [
            'name' => "Nike Air Force 1 '07 Triple White",
            'slug' => 'demo-air-force-1-unisex',
            'group' => 'unisex',
            'base_price' => 2_929_000,
            'promotion_price' => 2_490_000,
            'is_featured' => 1,
            'in_stock' => true,
            'color' => 'white',
            'tags' => ['sneaker', 'lifestyle', 'work'],
        ],
        [
            'name' => 'Nike Dunk Low Retro Panda White Black',
            'slug' => 'demo-dunk-low-panda-unisex',
            'group' => 'unisex',
            'base_price' => 2_890_000,
            'promotion_price' => null,
            'is_featured' => 1,
            'in_stock' => true,
            'color' => 'black',
            'tags' => ['sneaker', 'lifestyle'],
        ],
        [
            'name' => "Nike Blazer Mid '77 Vintage High-Top",
            'slug' => 'demo-blazer-mid-77-unisex',
            'group' => 'unisex',
            'base_price' => 2_790_000,
            'promotion_price' => 2_190_000,
            'is_featured' => 1,
            'in_stock' => true,
            'color' => 'white',
            'tags' => ['high-top', 'lifestyle', 'basketball'],
        ],
        [
            'name' => 'Nike Calm Slide Dép Đúc Nguyên Khối',
            'slug' => 'demo-calm-slide-unisex',
            'group' => 'unisex',
            'base_price' => 1_490_000,
            'promotion_price' => null,
            'is_featured' => 0,
            'in_stock' => true,
            'color' => 'brown',
            'tags' => ['sandals', 'lifestyle'],
        ],
        [
            'name' => 'Nike Manoa Leather Boots Unisex',
            'slug' => 'demo-manoa-leather-boots-unisex',
            'group' => 'unisex',
            'base_price' => 2_990_000,
            'promotion_price' => null,
            'is_featured' => 0,
            'in_stock' => true,
            'color' => 'brown',
            'tags' => ['boots', 'lifestyle'],
        ],
        [
            'name' => 'Nike Air Monarch IV Chunky Sneaker',
            'slug' => 'demo-air-monarch-iv-unisex',
            'group' => 'unisex',
            'base_price' => 1_990_000,
            'promotion_price' => null,
            'is_featured' => 0,
            'in_stock' => false, // Hết hàng phục vụ test filter
            'color' => 'white',
            'tags' => ['sneaker', 'training'],
        ],
        [
            'name' => 'Nike Air Presto Unisex Unclassified',
            'slug' => 'demo-air-presto-unisex-unclassified',
            'group' => 'unisex',
            'base_price' => 3_590_000,
            'promotion_price' => null,
            'is_featured' => 0,
            'in_stock' => true,
            'color' => 'black',
            'tags' => [], // Không gắn nhãn
        ],
    ];

    /**
     * Bản đồ hình ảnh riêng biệt, chân thực và cố định cho từng mẫu giày demo.
     *
     * @var array<string, string>
     */
    private const DEMO_IMAGES = [
        'demo-pegasus-41-men' => 'https://images.unsplash.com/photo-1584735935682-2f2b69dff9d2?w=600&auto=format&fit=crop&q=80',
        'demo-invincible-3-men' => 'https://images.unsplash.com/photo-1600185365926-3a2ce3cdb9eb?w=600&auto=format&fit=crop&q=80',
        'demo-metcon-9-men' => 'https://images.unsplash.com/photo-1587563871167-1ee9c731aefb?w=600&auto=format&fit=crop&q=80',
        'demo-acg-mountain-fly-men' => 'https://images.unsplash.com/photo-1539185441755-769473a23570?w=600&auto=format&fit=crop&q=80',
        'demo-giannis-immortality-men' => 'https://images.unsplash.com/photo-1579338559194-a162d19bf842?w=600&auto=format&fit=crop&q=80',
        'demo-alphafly-3-ekiden-long-name' => 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=600&auto=format&fit=crop&q=80',
        'demo-calm-mule-men' => 'https://images.unsplash.com/photo-1603808033192-082d6919d3e1?w=600&auto=format&fit=crop&q=80',
        'demo-air-max-sc-women' => 'https://images.unsplash.com/photo-1588117305388-c2631a279f82?w=600&auto=format&fit=crop&q=80',
        'demo-downshifter-13-women' => 'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?w=600&auto=format&fit=crop&q=80',
        'demo-court-vision-women' => 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=600&auto=format&fit=crop&q=80',
        'demo-icon-classic-sandals-women' => 'https://images.unsplash.com/photo-1562273138-f46be4ebdf33?w=600&auto=format&fit=crop&q=80',
        'demo-free-metcon-5-women' => 'https://images.unsplash.com/photo-1582588678413-dbf45f4823e9?w=600&auto=format&fit=crop&q=80',
        'demo-tanjun-ease-women' => 'https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?w=600&auto=format&fit=crop&q=80',
        'demo-structure-25-women-unclassified' => 'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=600&auto=format&fit=crop&q=80',
        'demo-flex-runner-3-kids' => 'https://images.unsplash.com/photo-1508609349937-5ec4ae374ebf?w=600&auto=format&fit=crop&q=80',
        'demo-star-runner-4-kids' => 'https://images.unsplash.com/photo-1514989940723-e8e51635b782?w=600&auto=format&fit=crop&q=80',
        'demo-sunray-protect-kids' => 'https://images.unsplash.com/photo-1503449377594-32dd9ac4467c?w=600&auto=format&fit=crop&q=80',
        'demo-court-borough-kids' => 'https://images.unsplash.com/photo-1511556532299-8f662fc26c06?w=600&auto=format&fit=crop&q=80',
        'demo-team-hustle-kids' => 'https://images.unsplash.com/photo-1556906781-9a412961c28c?w=600&auto=format&fit=crop&q=80',
        'demo-kawa-slide-kids' => 'https://images.unsplash.com/photo-1533681476418-47b233652878?w=600&auto=format&fit=crop&q=80',
        'demo-revolution-7-kids-unclassified' => 'https://images.unsplash.com/photo-1575537302964-96cd47c06b1b?w=600&auto=format&fit=crop&q=80',
        'demo-air-force-1-unisex' => 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=600&auto=format&fit=crop&q=80',
        'demo-dunk-low-panda-unisex' => 'https://images.unsplash.com/photo-1597045566677-8cf032ed6634?w=600&auto=format&fit=crop&q=80',
        'demo-blazer-mid-77-unisex' => 'https://images.unsplash.com/photo-1607522370275-f14206abe5d3?w=600&auto=format&fit=crop&q=80',
        'demo-calm-slide-unisex' => 'https://images.unsplash.com/photo-1560769629-975ec94e6a86?w=600&auto=format&fit=crop&q=80',
        'demo-manoa-leather-boots-unisex' => 'https://images.unsplash.com/photo-1608256246200-53e635b5b65f?w=600&auto=format&fit=crop&q=80',
        'demo-air-monarch-iv-unisex' => 'https://images.unsplash.com/photo-1552346154-21d32810aba3?w=600&auto=format&fit=crop&q=80',
        'demo-air-presto-unisex-unclassified' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&auto=format&fit=crop&q=80',
    ];

    public function run(ProductManagementService $products, StockImportService $imports): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('ProductClassificationDemoSeeder chỉ chạy trong môi trường local hoặc testing.');
        }

        $this->command?->info('Bắt đầu khởi tạo dữ liệu nhãn phân loại và sản phẩm mẫu...');

        // 1. Khởi tạo nhãn phân loại (Classifications)
        $classificationMap = [];
        foreach (self::CLASSIFICATIONS as $item) {
            $record = Classification::query()->firstOrCreate(
                [
                    'classification_group' => $item['classification_group']->value,
                    'slug' => $item['slug'],
                ],
                [
                    'name' => $item['name'],
                    'sort_order' => $item['sort_order'],
                    'is_active' => $item['is_active'],
                    'description' => $item['description'],
                ]
            );
            $classificationMap[$item['slug']] = $record->id;
        }

        // 2. Cập nhật nhãn cho 4 sản phẩm demo cốt lõi hiện có (nếu có)
        $this->updateCoreDemoProducts($classificationMap);

        // 3. Khởi tạo các sản phẩm mẫu mở rộng
        $actor = User::query()->where('is_active', true)->whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->orderBy('id')->first();
        if (! $actor) {
            $actor = User::factory()->administrator()->create();
        }

        $supplier = Supplier::query()->where('code', 'DEMO-NCC-1')->first();
        if (! $supplier) {
            $supplier = Supplier::query()->create([
                'code' => 'DEMO-NCC-1',
                'name' => 'Nhà cung cấp mẫu DEMO-NCC-1',
                'phone' => '0900000000',
                'is_active' => 1,
            ]);
        }

        $categories = Category::query()->whereNull('parent_id')->get()->keyBy(fn ($c) => strtolower($c->gender_group->value));

        $created = 0;
        $updated = 0;

        foreach (self::DEMO_CATALOG as $entry) {
            $tagIds = array_values(array_filter(array_map(fn ($slug) => $classificationMap[$slug] ?? null, $entry['tags'])));
            $existing = Product::query()->where('slug', $entry['slug'])->first();

            if ($existing) {
                // Idempotent: Cập nhật nhãn và is_featured nếu đã tồn tại, không sinh SKU/tồn kho trùng lặp
                $existing->classifications()->syncWithoutDetaching($tagIds);
                if ($existing->is_featured !== (bool) $entry['is_featured']) {
                    $existing->forceFill(['is_featured' => (bool) $entry['is_featured']])->save();
                }

                // Cập nhật ảnh đại diện đa dạng theo từng mẫu mã (thay thế ảnh mặc định cũ)
                $targetImageUrl = self::DEMO_IMAGES[$entry['slug']] ?? null;
                if ($targetImageUrl) {
                    $color = $existing->colors()->first();
                    if ($color) {
                        $thumb = $color->thumbnail;
                        if ($thumb && $thumb->source_type === ProductImageSourceType::ExternalUrl) {
                            $thumb->forceFill(['external_url' => $targetImageUrl])->save();
                        }
                    }
                }

                $updated++;

                continue;
            }

            $category = $categories->get($entry['group']);
            if (! $category) {
                continue;
            }

            $code = 'STYLE-'.strtoupper(Str::random(8));
            $palette = config('store.palette.'.$entry['color']) ?? config('store.palette.black');
            $sizes = $this->sizesForGroup($entry['group']);

            $product = $products->save($actor, null, [
                'name' => $entry['name'],
                'slug' => $entry['slug'],
                'category_id' => $category->id,
                'brand_line' => 'Nike · Chính Hãng Phân Phối',
                'description' => 'Sản phẩm minh họa phục vụ kiểm thử và thiết kế giao diện Cena Store.',
                'base_price' => $entry['base_price'],
                'promotion_price' => $entry['promotion_price'],
                'warranty_months' => 6,
                'exchange_days' => 7,
                'is_active' => 1,
                'is_featured' => $entry['is_featured'],
                'classification_ids' => $tagIds,
                'colors' => [
                    [
                        'style_color_code' => $code,
                        'color_name' => $palette['label'] ?? 'Màu chuẩn',
                        'color_key' => $entry['color'],
                        'primary_hex' => $palette['hex'] ?? '#000000',
                        'is_active' => 1,
                        'variants' => array_map(fn (string $size) => [
                            'size_value' => $size,
                            'sku' => $code.'-'.$size,
                            'price_override' => null,
                            'is_active' => 1,
                        ], $sizes),
                        'images' => [
                            [
                                'external_url' => self::DEMO_IMAGES[$entry['slug']] ?? 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&auto=format&fit=crop&q=80',
                                'alt_text' => $entry['name'],
                                'is_thumbnail' => 1,
                            ],
                        ],
                    ],
                ],
            ]);

            // Nếu sản phẩm được định nghĩa là còn hàng -> Nhập kho ban đầu
            if ($entry['in_stock']) {
                $variants = ProductVariant::query()->whereHas('color', fn ($q) => $q->where('product_id', $product->id))->get();
                $import = $imports->saveDraft($actor, null, [
                    'command_key' => (string) Str::uuid(),
                    'supplier_id' => $supplier->id,
                    'note' => 'Nhập kho ban đầu demo cho '.$entry['name'],
                    'items' => $variants->map(fn (ProductVariant $v) => [
                        'variant_id' => $v->id,
                        'quantity' => 10,
                        'unit_cost_amount' => (int) ($entry['base_price'] * 0.6),
                    ])->all(),
                ]);
                $imports->post($actor, $import);
            }

            $created++;
        }

        $this->command?->info("Hoàn tất! Đã tạo {$created} sản phẩm mới, cập nhật {$updated} sản phẩm cũ. Tổng sản phẩm: ".Product::query()->count());
    }

    private function updateCoreDemoProducts(array $map): void
    {
        $assignments = [
            'cena-demo-men' => ['sneaker', 'running'],
            'cena-demo-women' => ['sneaker', 'lifestyle'],
            'cena-demo-kids' => ['sneaker', 'running'],
            'cena-demo-unisex' => ['high-top', 'lifestyle'],
        ];

        foreach ($assignments as $slug => $tags) {
            $product = Product::query()->where('slug', $slug)->first();
            if ($product) {
                $ids = array_values(array_filter(array_map(fn ($t) => $map[$t] ?? null, $tags)));
                $product->classifications()->syncWithoutDetaching($ids);
                $product->forceFill(['is_featured' => true])->save();
            }
        }
    }

    /** @return list<string> */
    private function sizesForGroup(string $group): array
    {
        return match ($group) {
            'men' => ['39', '40', '41', '42', '43'],
            'women' => ['36', '37', '38', '39'],
            'kids' => ['31', '32', '33', '34'],
            default => ['38', '39', '40', '41'],
        };
    }
}
