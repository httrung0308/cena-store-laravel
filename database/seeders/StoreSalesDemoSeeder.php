<?php

namespace Database\Seeders;

use App\Enums\AdministrativeUnitLevel;
use App\Enums\RoleSlug;
use App\Models\AdministrativeUnit;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AddressService;
use App\Services\CheckoutService;
use App\Services\OrderWorkflowService;
use App\Services\ProductManagementService;
use App\Services\StockImportService;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use LogicException;

/**
 * Local/testing only: a believable shoe shop for the dashboard. Twelve Nike models at Vietnamese retail prices,
 * bought at 62–72% of list price (authorised-reseller margins), two stock lots per variant, two dozen customers and
 * about three months of orders that go through checkout and the real order workflow, so every figure on the report
 * comes from the sales ledger and the stock ledger. Most orders are delivered, some are cancelled or fail delivery
 * and the latest ones are still in progress. Deterministic: the same date gives the same shop. Runs once.
 */
class StoreSalesDemoSeeder extends Seeder
{
    private const DAYS = 90;

    private const RESTOCK_DAY = 42;

    /** Best sellers whose mid-period lot never arrived, so their sizes run low. */
    private const BACKORDERED = ['Nike Dunk Low Retro', 'Air Jordan 1 Low'];

    private const RANDOM_SEED = 20261103;

    /** @var array<string, list<string>> */
    private const SIZES = [
        'men' => ['39', '40', '41', '42', '43', '44'],
        'women' => ['36', '37', '38', '39', '40'],
        'kids' => ['30', '31', '32', '33', '34', '35'],
        'unisex' => ['37', '38', '39', '40', '41', '42', '43'],
    ];

    /** Relative demand per size position: middle sizes sell most. */
    private const SIZE_WEIGHTS = [
        5 => [1, 3, 4, 3, 1],
        6 => [1, 3, 4, 4, 2, 1],
        7 => [1, 2, 3, 4, 3, 2, 1],
    ];

    /**
     * Name, group, list price, promotion price, cost as per-mille of list price, demand weight, image, colours [palette key, colour name, style code].
     *
     * @var list<array{0: string, 1: string, 2: int, 3: ?int, 4: int, 5: int, 6: string, 7: list<array{0: string, 1: string, 2: string}>}>
     */
    private const CATALOG = [
        ["Nike Air Force 1 '07", 'unisex', 2_929_000, null, 700, 10, 'unisex', [['white', 'Triple White', 'CW2288-111'], ['black', 'Triple Black', 'CW2288-001']]],
        ['Nike Dunk Low Retro', 'unisex', 2_929_000, null, 720, 9, 'unisex', [['black', 'White/Black Panda', 'DD1391-100'], ['gray', 'Grey Fog', 'DD1391-103']]],
        ['Air Jordan 1 Low', 'unisex', 3_239_000, null, 700, 6, 'unisex', [['red', 'Gym Red', '553558-161'], ['black', 'Black Toe', '553558-132']]],
        ['Nike Pegasus 41', 'men', 3_829_000, 3_249_000, 660, 7, 'men', [['black', 'Black/White', 'FD2722-002'], ['blue', 'Racer Blue', 'FD2722-400']]],
        ['Nike Air Max 90', 'men', 3_829_000, null, 690, 5, 'men', [['white', 'White/Wolf Grey', 'CN8490-100'], ['gray', 'Smoke Grey', 'CN8490-002']]],
        ['Nike Revolution 7', 'men', 1_709_000, 1_369_000, 620, 8, 'men', [['black', 'Black/White', 'FB2207-001'], ['white', 'White/Pure Platinum', 'FB2207-100']]],
        ['Nike Invincible 3', 'men', 4_999_000, 4_199_000, 650, 3, 'men', [['white', 'White/Photon Dust', 'DR2615-101'], ['orange', 'Total Orange', 'DR2615-800']]],
        ['Nike Air Max SC', 'women', 2_069_000, null, 680, 6, 'women', [['white', 'White/Pink Foam', 'CW4554-101'], ['pink', 'Pink Rise', 'CW4554-601']]],
        ['Nike Downshifter 13', 'women', 1_779_000, 1_419_000, 630, 5, 'women', [['black', 'Black/White', 'FD6476-001'], ['purple', 'Daybreak Purple', 'FD6476-500']]],
        ['Nike Court Vision Low', 'women', 1_909_000, null, 670, 4, 'women', [['white', 'White/Black', 'DH3158-100'], ['green', 'White/Malachite', 'DH3158-108']]],
        ['Nike Star Runner 4', 'kids', 1_119_000, null, 640, 3, 'kid', [['black', 'Black/White', 'DX7614-001'], ['blue', 'Game Royal', 'DX7614-400']]],
        ['Nike Team Hustle D 11', 'kids', 1_329_000, 1_059_000, 620, 2, 'kid', [['black', 'Black/Volt', 'DV8996-002'], ['red', 'University Red', 'DV8996-600']]],
    ];

    private const CUSTOMERS = [
        'Nguyễn Minh Anh', 'Trần Quốc Bảo', 'Lê Thu Hà', 'Phạm Gia Huy', 'Hoàng Ngọc Lan', 'Vũ Đức Long', 'Đặng Mỹ Linh', 'Bùi Thanh Tùng',
        'Đỗ Khánh Vy', 'Hồ Tuấn Kiệt', 'Ngô Phương Thảo', 'Dương Hải Nam', 'Lý Bảo Ngọc', 'Phan Đức Thịnh', 'Võ Hoài An', 'Trịnh Mai Chi',
        'Đinh Quang Vinh', 'Tạ Thu Trang', 'Lâm Nhật Minh', 'Mai Thanh Hương', 'Châu Gia Bảo', 'Tôn Nữ Diễm', 'Kiều Văn Phúc', 'Quách Yến Nhi',
    ];

    private const STREETS = ['12 Nguyễn Huệ', '45 Lê Lợi', '88 Trần Hưng Đạo', '210 Điện Biên Phủ', '7 Phan Xích Long', '156 Cách Mạng Tháng Tám', '33 Hai Bà Trưng', '19 Võ Văn Tần', '72 Lý Thường Kiệt', '5 Nguyễn Trãi'];

    private const DELIVERY_FAILURES = ['Khách không nghe máy sau 3 lần gọi', 'Khách từ chối nhận hàng', 'Sai số nhà, không liên lạc được'];

    private const CANCEL_REASONS = ['Khách đặt nhầm size', 'Khách đổi ý, muốn chọn màu khác', 'Khách đặt trùng đơn'];

    public function run(
        ProductManagementService $products,
        StockImportService $imports,
        AddressService $addresses,
        CheckoutService $checkout,
        OrderWorkflowService $workflow,
        BusinessClock $clock,
    ): void {
        if (! app()->environment(['local', 'testing']) || config('store.demo.enabled') !== true) {
            throw new LogicException('Explicit local/testing demo opt-in required.');
        }
        $password = config('store.demo.password');
        if (! is_string($password) || strlen($password) < 12 || strlen($password) > 72) {
            throw new LogicException('Configure a private demo password of 12 to 72 bytes before seeding demo customers.');
        }
        if (Product::withTrashed()->where('slug', $this->slug(self::CATALOG[0][0]))->exists()) {
            $this->command?->info('Cửa hàng demo đã có dữ liệu bán hàng, bỏ qua.');

            return;
        }
        $this->call(ProductDemoTaxonomySeeder::class);
        $admin = $this->userWithRole(RoleSlug::Admin) ?? throw new LogicException('Seed an active demo administrator first.');
        $staff = $this->userWithRole(RoleSlug::Staff) ?? $admin;

        mt_srand(self::RANDOM_SEED);
        $timezone = (string) config('store.business_timezone', 'Asia/Ho_Chi_Minh');
        $now = $clock->now();
        $firstDay = $now->setTimezone($timezone)->startOfDay()->subDays(self::DAYS - 1);

        try {
            $clock->freeze($firstDay->subDays(6)->setTime(9, 0)->utc());
            $catalog = $this->createCatalog($products, $admin);
            $customers = $this->createCustomers($addresses, $clock, (string) $password);
            $plan = $this->planOrders($catalog, $customers, $firstDay, $now);
            $this->importStock($imports, $admin, $catalog, $plan, 'Nhập hàng đầu mùa', 0);

            $events = $this->events($plan, $now);
            $restockAt = $firstDay->addDays(self::RESTOCK_DAY)->setTime(9, 0)->utc();
            $restocked = false;
            $orders = [];
            $placed = 0;
            foreach ($events as $event) {
                if (! $restocked && $event['at']->greaterThanOrEqualTo($restockAt)) {
                    $clock->freeze($restockAt);
                    $this->importStock($imports, $admin, $catalog, $plan, 'Nhập bổ sung giữa kỳ', 1);
                    $restocked = true;
                }
                $clock->freeze($event['at']);
                $key = $event['order'];
                if ($event['step'] === 'place') {
                    $order = $this->place($checkout, $plan[$key]);
                    if ($order !== null) {
                        $orders[$key] = $order;
                        $placed++;
                    }

                    continue;
                }
                if (! isset($orders[$key])) {
                    continue;
                }
                $orders[$key] = $this->advance($workflow, $orders[$key]->fresh(), $event['step'], $plan[$key], $staff);
            }
        } finally {
            $clock->unfreeze();
        }

        $this->command?->info("Đã tạo {$placed} đơn hàng demo trong ".self::DAYS.' ngày cho '.count($customers).' khách.');
    }

    /**
     * Creates the twelve models with every size, priced like the Nike Vietnam store.
     *
     * @return list<array{product: string, price: int, cost: int, weight: int, variants: list<array{id: int, share: float}>}>
     */
    private function createCatalog(ProductManagementService $products, User $admin): array
    {
        $catalog = [];
        foreach (self::CATALOG as [$name, $group, $basePrice, $promotionPrice, $costPerMille, $weight, $image, $colors]) {
            $sizes = self::SIZES[$group];
            $path = public_path('legacy/img/'.$image.'.jpg');
            $product = $products->save($admin, null, [
                'name' => $name,
                'slug' => $this->slug($name),
                'category_id' => Category::where('slug', $group)->firstOrFail()->id,
                'brand_line' => 'Nike',
                'description' => $name.' chính hãng, đủ size, bảo hành keo đế 6 tháng và đổi size trong 7 ngày.',
                'base_price' => $basePrice,
                'promotion_price' => $promotionPrice,
                'warranty_months' => 6,
                'exchange_days' => 7,
                'is_active' => 1,
                'colors' => array_map(fn (array $color): array => [
                    'style_color_code' => $color[2],
                    'color_name' => $color[1],
                    'color_key' => $color[0],
                    'primary_hex' => (string) config('store.palette.'.$color[0].'.hex'),
                    'is_active' => 1,
                    'variants' => array_map(fn (string $size): array => ['size_value' => $size, 'sku' => $color[2].'-'.$size, 'price_override' => null, 'is_active' => 1], $sizes),
                    'images' => [['file' => new UploadedFile($path, basename($path), 'image/jpeg', null, true), 'alt_text' => $name.' '.$color[1], 'is_thumbnail' => true]],
                ], $colors),
            ]);
            $sizeWeights = self::SIZE_WEIGHTS[count($sizes)];
            $variants = [];
            foreach ($colors as $colorIndex => $color) {
                $colorShare = $colorIndex === 0 ? 0.62 : 0.38;
                foreach ($sizes as $sizeIndex => $size) {
                    $variants[] = [
                        'id' => ProductVariant::where('sku', $color[2].'-'.$size)->value('id'),
                        'share' => $colorShare * $sizeWeights[$sizeIndex] / array_sum($sizeWeights),
                    ];
                }
            }
            $catalog[] = [
                'product' => $product->name,
                'price' => $promotionPrice ?? $basePrice,
                'cost' => intdiv($basePrice * $costPerMille, 1_000_000) * 1_000,
                'weight' => $weight,
                'variants' => $variants,
            ];
        }

        return $catalog;
    }

    /** @return list<array{user: User, address: int}> */
    private function createCustomers(AddressService $addresses, BusinessClock $clock, string $password): array
    {
        $role = Role::where('slug', RoleSlug::Customer->value)->firstOrFail();
        $version = $addresses->currentDatasetVersion();
        $provinces = AdministrativeUnit::where('dataset_version', $version)->where('level', AdministrativeUnitLevel::Province)->where('is_active', true)->orderBy('code')->get();
        if ($provinces->isEmpty()) {
            throw new LogicException('Seed the province/ward snapshot (AdministrativeUnitSeeder) first.');
        }
        $hcm = $provinces->firstWhere('code', (string) config('store.shipping.hcm_province_code')) ?? $provinces->first();

        $customers = [];
        foreach (self::CUSTOMERS as $index => $name) {
            $user = new User;
            $user->full_name = $name;
            $user->email = sprintf('khach%02d@demo.cena.test', $index + 1);
            $user->password = $password;
            $user->is_active = true;
            $user->auth_version = 1;
            $user->email_verified_at = null;
            $user->created_at = $clock->now();
            $user->updated_at = $clock->now();
            $user->save();

            $assignment = new UserRole;
            $assignment->user_id = $user->id;
            $assignment->role_id = $role->id;
            $assignment->assigned_by = null;
            $assignment->assigned_at = $clock->now();
            $assignment->save();

            // Roughly half of the customers live in Ho Chi Minh City, the rest across the country.
            $province = $index % 2 === 0 ? $hcm : $provinces[mt_rand(0, $provinces->count() - 1)];
            $wards = AdministrativeUnit::where('dataset_version', $version)->where('level', AdministrativeUnitLevel::Ward)->where('is_active', true)
                ->where('parent_id', $province->id)->orderBy('code')->get();
            $address = $addresses->create($user, [
                'recipient_name' => $name,
                'phone' => '09'.str_pad((string) (12_345_678 + $index * 1_097), 8, '0', STR_PAD_LEFT),
                'province_code' => $province->code,
                'ward_code' => $wards[mt_rand(0, $wards->count() - 1)]->code,
                'street_address' => self::STREETS[$index % count(self::STREETS)],
                'is_default' => true,
            ]);
            $customers[] = ['user' => $user, 'address' => $address->id];
        }

        return $customers;
    }

    /**
     * Decides every order up front: when it is placed, what is in it and how it ends. Busier at weekends and
     * around paydays, growing slowly over the period.
     *
     * @return list<array{customer: array{user: User, address: int}, lines: array<int, int>, placed: CarbonImmutable, outcome: string, reason: string}>
     */
    private function planOrders(array $catalog, array $customers, CarbonImmutable $firstDay, CarbonImmutable $now): array
    {
        $weights = array_column($catalog, 'weight');
        $plan = [];
        for ($offset = 0; $offset < self::DAYS; $offset++) {
            $day = $firstDay->addDays($offset);
            $expected = 3.5 + 2.5 * $offset / self::DAYS;
            if ($day->isWeekend()) {
                $expected *= 1.45;
            }
            if ($day->day <= 5 || $day->day >= 28) {
                $expected *= 1.2;
            }
            $count = (int) round($expected * (0.65 + mt_rand(0, 70) / 100));
            for ($n = 0; $n < $count; $n++) {
                $placed = $day->setTime($this->pick([0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 2, 3, 3, 2, 2, 2, 2, 2, 3, 4, 5, 6, 5, 3]), mt_rand(0, 59), mt_rand(0, 59))->utc();
                if ($placed->greaterThanOrEqualTo($now)) {
                    continue;
                }
                $lines = [];
                $pairs = mt_rand(1, 100) <= 18 ? 2 : 1;
                for ($line = 0; $line < $pairs; $line++) {
                    $shoe = $catalog[$this->pick($weights)];
                    $variant = $shoe['variants'][$this->pick(array_map(fn (array $v): int => (int) round($v['share'] * 1000), $shoe['variants']))];
                    $lines[$variant['id']] = ($lines[$variant['id']] ?? 0) + 1;
                }
                $roll = mt_rand(1, 100);
                $plan[] = [
                    'customer' => $customers[$this->pick(array_map(fn (int $i): int => $i < 6 ? 4 : 1, array_keys($customers)))],
                    'lines' => $lines,
                    'placed' => $placed,
                    'outcome' => match (true) {
                        $roll <= 4 => 'customer_cancel',
                        $roll <= 7 => 'staff_cancel',
                        $roll <= 10 => 'fail',
                        default => 'deliver',
                    },
                    'reason' => $roll <= 7 ? self::CANCEL_REASONS[$roll % 3] : self::DELIVERY_FAILURES[$roll % 3],
                ];
            }
        }

        return $plan;
    }

    /**
     * Expected pairs per variant decide each lot: every size opens with at least 8 pairs (about 70% of the period's
     * demand for busy sizes) and the mid-way lot covers the rest with a small buffer, so most sizes end comfortably
     * stocked. The two hottest models miss the mid-way lot (supplier backorder), which is what the low-stock warning
     * should catch. The second lot costs slightly more, which moves the weighted average cost.
     */
    private function importStock(StockImportService $imports, User $admin, array $catalog, array $plan, string $note, int $lot): void
    {
        $demand = [];
        foreach ($plan as $order) {
            foreach ($order['lines'] as $variantId => $quantity) {
                $demand[$variantId] = ($demand[$variantId] ?? 0) + $quantity;
            }
        }
        $suppliers = Supplier::whereIn('code', ['DEMO-NCC-1', 'DEMO-NCC-2', 'DEMO-NCC-3'])->orderBy('code')->pluck('id')->all();
        foreach ($catalog as $index => $shoe) {
            if ($lot === 1 && in_array($shoe['product'], self::BACKORDERED, true)) {
                continue;
            }
            $items = [];
            foreach ($shoe['variants'] as $variant) {
                $expected = $demand[$variant['id']] ?? 0;
                $quantity = $lot === 0 ? max(8, (int) ceil($expected * 0.7) + 4) : (int) ceil($expected * 0.55) + 2;
                if ($quantity > 0) {
                    $items[] = ['variant_id' => $variant['id'], 'quantity' => $quantity, 'unit_cost_amount' => $lot === 0 ? $shoe['cost'] : intdiv($shoe['cost'] * 103, 100_000) * 1_000];
                }
            }
            if ($items === []) {
                continue;
            }
            $import = $imports->saveDraft($admin, null, [
                'command_key' => (string) Str::uuid(),
                'supplier_id' => $suppliers[($index + $lot) % count($suppliers)],
                'note' => $note.' — '.$shoe['product'],
                'items' => $items,
            ]);
            $imports->post($admin, $import);
        }
    }

    /**
     * Each order's timeline: confirmed within hours, packed the same day, handed to the carrier the next morning and
     * delivered one to three days later. Steps that would happen after now are left for the shop to do.
     *
     * @return list<array{at: CarbonImmutable, order: int, step: string}>
     */
    private function events(array $plan, CarbonImmutable $now): array
    {
        $events = [];
        foreach ($plan as $key => $order) {
            $at = $order['placed'];
            $steps = [['place', $at]];
            if ($order['outcome'] === 'customer_cancel') {
                $steps[] = ['customer_cancel', $at->addMinutes(mt_rand(15, 180))];
            } else {
                $steps[] = ['confirm', $at = $at->addMinutes(mt_rand(30, 240))];
                if ($order['outcome'] === 'staff_cancel') {
                    $steps[] = ['staff_cancel', $at->addMinutes(mt_rand(20, 120))];
                } else {
                    $steps[] = ['pack', $at = $at->addMinutes(mt_rand(60, 300))];
                    $steps[] = ['ship', $at = $at->addDay()->setTimezone((string) config('store.business_timezone'))->setTime(mt_rand(9, 15), mt_rand(0, 59))->utc()];
                    $steps[] = [$order['outcome'], $at->addDays(mt_rand(1, 3))->setTimezone((string) config('store.business_timezone'))->setTime(mt_rand(9, 19), mt_rand(0, 59))->utc()];
                }
            }
            foreach ($steps as [$step, $when]) {
                if ($when->lessThan($now)) {
                    $events[] = ['at' => $when, 'order' => $key, 'step' => $step];
                }
            }
        }
        usort($events, fn (array $a, array $b): int => [$a['at'], $a['order']] <=> [$b['at'], $b['order']]);

        return $events;
    }

    /** Fills the customer's cart and checks out like the storefront does; skips the order when a size has sold out. */
    private function place(CheckoutService $checkout, array $order): ?Order
    {
        $user = $order['customer']['user'];
        $cart = Cart::query()->where('user_id', $user->id)->first() ?? new Cart;
        $cart->forceFill(['user_id' => $user->id, 'version' => ($cart->version ?? 0) + 1])->save();
        CartItem::query()->where('cart_id', $cart->id)->delete();
        foreach ($order['lines'] as $variantId => $quantity) {
            (new CartItem)->forceFill(['cart_id' => $cart->id, 'variant_id' => $variantId, 'quantity' => $quantity])->save();
        }
        $address = $user->addresses()->findOrFail($order['customer']['address']);
        $quote = $checkout->quote($user, $address);
        if ($quote['problems'] !== [] || $quote['fingerprint'] === null) {
            CartItem::query()->where('cart_id', $cart->id)->delete();

            return null;
        }

        return $checkout->place($user, $address->id, (string) Str::uuid(), $quote['fingerprint'], null);
    }

    private function advance(OrderWorkflowService $workflow, Order $order, string $step, array $plan, User $staff): Order
    {
        $expected = $order->order_status->value;

        return match ($step) {
            'confirm' => $workflow->confirm($order, $staff, $expected),
            'pack' => $workflow->pack($order, $staff, $expected),
            'ship' => $workflow->ship($order, $staff, $expected, ['GHN', 'GHTK', 'J&T Express'][$order->id % 3], 'VN'.str_pad((string) $order->id, 9, '0', STR_PAD_LEFT)),
            'deliver' => $workflow->deliver($order, $staff, $expected, $this->demoProof($order)),
            'fail' => $workflow->fail($order, $staff, $expected, $plan['reason']),
            'customer_cancel' => $workflow->cancel($order, $plan['customer']['user'], $expected, $plan['reason'], true),
            'staff_cancel' => $workflow->cancel($order, $staff, $expected, $plan['reason'], false),
        };
    }

    /** @param  list<int>  $weights  Index chosen with probability proportional to its weight. */
    private function pick(array $weights): int
    {
        $roll = mt_rand(1, max(1, array_sum($weights)));
        foreach ($weights as $index => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $index;
            }
        }

        return array_key_last($weights);
    }

    private function demoProof(Order $order): ?UploadedFile
    {
        if ($order->payment_method?->value !== 'COD') {
            return null;
        }

        $image = imagecreatetruecolor(240, 60);
        $intSeed = crc32($order->order_code);
        $bgColor = imagecolorallocate($image, 200 + abs($intSeed % 50), 200 + abs(($intSeed >> 3) % 50), 200 + abs(($intSeed >> 6) % 50));
        imagefill($image, 0, 0, $bgColor);
        $textColor = imagecolorallocate($image, 20, 20, 20);
        imagestring($image, 3, 10, 20, $order->order_code, $textColor);
        $temp = tmpfile();
        ob_start();
        imagejpeg($image);
        imagedestroy($image);
        fwrite($temp, ob_get_clean());

        return new \Illuminate\Http\Testing\File('proof-'.$order->order_code.'.jpg', $temp);
    }

    private function userWithRole(RoleSlug $role): ?User
    {
        return User::where('is_active', true)->whereHas('roles', fn ($query) => $query->where('slug', $role->value))->orderBy('id')->first();
    }

    private function slug(string $name): string
    {
        return Str::slug(str_replace("'", '', $name));
    }
}
