<?php

namespace Database\Seeders;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleSlug;
use App\Models\Address;
use App\Models\AdministrativeUnit;
use App\Models\Banner;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\StockImport;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserRole;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\DemoPaymentService;
use App\Services\OrderWorkflowService;
use App\Services\ProductManagementService;
use App\Services\ReviewService;
use App\Services\ShipmentReturnService;
use App\Services\StockImportService;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;

/**
 * Local/testing only: seeds a complete, service-backed demo dataset covering
 * foundation roles/locations, catalog & low/out-of-stock variants, stock imports,
 * orders across all statuses, failed-delivery return flows, prepaid refunds,
 * reviews (public, hidden, unreviewed, grouped pairs), articles, banners, coupons,
 * and multi-period historical transactions for reports.
 */
class FullDemoSeeder extends Seeder
{
    private const DEFAULT_DEMO_PASSWORD = 'CenaDemo@2026';

    private const IMPORT_POSTED_COMMAND_KEY = '71000000-0000-4000-8000-000000000001';

    private const IMPORT_DRAFT_COMMAND_KEY = '71000000-0000-4000-8000-000000000002';

    public function run(
        BusinessClock $clock,
        ProductManagementService $products,
        StockImportService $imports,
        CartService $cartService,
        CheckoutService $checkoutService,
        OrderWorkflowService $workflow,
        ShipmentReturnService $returnService,
        DemoPaymentService $demoPayment,
        ReviewService $reviewService,
    ): void {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('FullDemoSeeder chỉ được chạy trên môi trường local hoặc testing.');
        }

        $previousEnabled = config('store.demo.enabled');
        $previousPassword = config('store.demo.password');
        $configuredPassword = $previousPassword;
        $demoPassword = is_string($configuredPassword)
            && strlen($configuredPassword) >= 12
            && strlen($configuredPassword) <= 72
            ? $configuredPassword
            : self::DEFAULT_DEMO_PASSWORD;

        config([
            'store.demo.enabled' => true,
            'store.demo.password' => $demoPassword,
        ]);

        $tempFiles = [];

        try {
            $this->call([
                CoreFoundationSeeder::class,
                AdministrativeUnitSeeder::class,
                ProductDemoTaxonomySeeder::class,
                StockImportDemoSeeder::class,
                ProductClassificationDemoSeeder::class,
                SampleArticlesSeeder::class,
                BannerDemoSeeder::class,
                ArticleFeedbackDemoSeeder::class,
            ]);

            $adminQa = $this->ensureUser('admin.qa@demo.cena.test', 'Quản Trị Viên QA', RoleSlug::Admin, $demoPassword, $clock);
            $staffQa = $this->ensureUser('staff.qa@demo.cena.test', 'Nhân Viên QA', RoleSlug::Staff, $demoPassword, $clock);
            $khach1 = $this->ensureUser('khach1.qa@demo.cena.test', 'Nguyễn Minh Anh', RoleSlug::Customer, $demoPassword, $clock);
            $khach2 = $this->ensureUser('khach2.qa@demo.cena.test', 'Trần Bảo Ngọc', RoleSlug::Customer, $demoPassword, $clock);

            $addr1 = $this->ensureAddress($khach1, '128 Đường Phan Đình Phùng, Khu QA 1', '0901234001', $clock);
            $addr2 = $this->ensureAddress($khach2, '45 Đường Hùng Vương, Khu QA 2', '0901234002', $clock);

            $this->ensureQaSuppliers();
            $this->ensureInactiveBanner();
            $this->ensureQaCoupons($clock->now());

            [$sp1, $sp2] = $this->ensureQaProducts($adminQa, $products);

            $v1Black40 = $this->findVariant($sp1, 'Đen Than', '40');
            $v1Black41 = $this->findVariant($sp1, 'Đen Than', '41');
            $v1Cream40 = $this->findVariant($sp1, 'Trắng Kem', '40');
            $v1Cream41 = $this->findVariant($sp1, 'Trắng Kem', '41');
            $v2Navy39 = $this->findVariant($sp2, 'Xanh Navy', '39');
            $v2Navy40 = $this->findVariant($sp2, 'Xanh Navy', '40');

            $realNow = CarbonImmutable::now('UTC');
            $importInstant = $realNow->subDays(380)->setTime(8, 0);

            $this->ensureQaStockImports(
                $adminQa,
                $imports,
                $clock,
                $importInstant,
                [
                    [$v1Black40->id, 25, 1_150_000],
                    [$v1Black41->id, 15, 1_150_000],
                    [$v1Cream40->id, 12, 1_150_000],
                    [$v1Cream41->id, 14, 1_150_000],
                    [$v2Navy39->id, 6, 820_000],
                    [$v2Navy40->id, 4, 820_000],
                ]
            );

            // Idempotency: do not duplicate or reset orders if QA orders already exist.
            if (Order::query()->whereIn('user_id', [$khach1->id, $khach2->id])->exists()) {
                return;
            }

            $session = app('session')->driver('array');

            $makeProof = function (int $seed) use (&$tempFiles): UploadedFile {
                $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cena_full_demo_proof_'.$seed.'_'.uniqid().'.png';
                $img = imagecreatetruecolor(24, 24);
                $bg = imagecolorallocate($img, 30 + ($seed * 23) % 180, 70 + ($seed * 31) % 160, 120 + ($seed * 19) % 110);
                imagefill($img, 0, 0, $bg);
                imagestring($img, 2, 2, 5, 'P'.$seed, imagecolorallocate($img, 255, 255, 255));
                imagepng($img, $path);
                imagedestroy($img);
                $tempFiles[] = $path;

                return new UploadedFile($path, "demo-proof-{$seed}.png", 'image/png', null, true);
            };

            $placeOrder = function (
                User $customer,
                Address $address,
                array $lines,
                string $note,
                PaymentMethod $method = PaymentMethod::Cod,
                ?string $couponCode = null
            ) use ($cartService, $checkoutService, $session): Order {
                $session->start();
                $existingCart = $cartService->find($customer, $session);
                if ($existingCart !== null) {
                    CartItem::query()->where('cart_id', $existingCart->id)->delete();
                    $cart = $existingCart->fresh();
                } else {
                    $cart = null;
                }

                $version = $cart?->version;
                foreach ($lines as [$variantId, $qty]) {
                    $cart = $cartService->add($customer, $session, $variantId, $qty, $version);
                    $version = $cart->version;
                }

                $quote = $checkoutService->quote($customer, $address, $method, $couponCode);
                if (! empty($quote['problems']) || empty($quote['fingerprint'])) {
                    throw new LogicException('FullDemoSeeder quote failed: '.json_encode($quote, JSON_UNESCAPED_UNICODE));
                }

                return $checkoutService->place(
                    $customer,
                    $address->id,
                    (string) Str::ulid(),
                    $quote['fingerprint'],
                    $note,
                    $method,
                    $couponCode
                );
            };

            $deliverHistorical = function (
                CarbonImmutable $placedAt,
                User $customer,
                Address $address,
                array $lines,
                string $note,
                string $carrier,
                string $tracking,
                int $seed,
                ?string $couponCode = null
            ) use ($clock, $placeOrder, $workflow, $staffQa, $makeProof): Order {
                $clock->freeze($placedAt);
                $order = $placeOrder($customer, $address, $lines, $note, PaymentMethod::Cod, $couponCode);

                $clock->freeze($placedAt->addHours(2));
                $order = $workflow->confirmAndPack($order, $staffQa, OrderStatus::Pending->value);

                $clock->freeze($placedAt->addHours(5));
                $order = $workflow->ship($order, $staffQa, OrderStatus::Packing->value, $carrier, $tracking);

                $clock->freeze($placedAt->addDay());
                $order = $workflow->deliver($order, $staffQa, OrderStatus::OnDelivery->value, $makeProof($seed));

                return $order;
            };

            // Historical delivered orders across Year / Quarter / Month / Week for reports
            $deliverHistorical($realNow->subYear()->setTime(10, 0), $khach2, $addr2, [[$v1Black41->id, 1]], '[QA] Đơn lịch sử năm trước', 'GHN', 'GHN-DEMO-Y1', 1);
            $deliverHistorical($realNow->subMonths(6)->setTime(10, 0), $khach1, $addr1, [[$v1Cream40->id, 1]], '[QA] Đơn lịch sử 6 tháng trước', 'GHTK', 'GHTK-DEMO-Q2', 2);
            $deliverHistorical($realNow->subMonths(3)->setTime(14, 30), $khach2, $addr2, [[$v1Cream41->id, 1]], '[QA] Đơn lịch sử quý trước', 'ViettelPost', 'VTP-DEMO-Q3', 3);
            $deliverHistorical($realNow->subMonth()->setTime(9, 15), $khach1, $addr1, [[$v1Black40->id, 2]], '[QA] Đơn lịch sử tháng trước', 'GHN', 'GHN-DEMO-M1', 4);

            $histHiddenReviewOrder = $deliverHistorical($realNow->subDays(8)->setTime(11, 0), $khach2, $addr2, [[$v1Black41->id, 1]], '[QA] Đơn tuần trước kèm đánh giá ẩn', 'GHTK', 'GHTK-DEMO-W1', 5);
            $histHiddenReviewOrder->load('items.units');
            $clock->freeze($realNow->subDays(6)->setTime(9, 0));
            $hiddenReview = $reviewService->submit(
                $khach2,
                $histHiddenReviewOrder->items->first()->units->first()->id,
                '2.0',
                'Đánh giá thử nghiệm ở trạng thái ẩn khỏi trang công khai.'
            );
            $reviewService->setVisible($hiddenReview, false);

            $deliverHistorical($realNow->subDays(2)->setTime(15, 0), $khach1, $addr1, [[$v2Navy40->id, 3]], '[QA] Đơn đầu tuần dùng mã CENAQA-FIXED', 'GHN', 'GHN-DEMO-W0', 6, 'CENAQA-FIXED');

            // Today's workflow & return scenarios (ordered so available stock never dips below 0)
            $clock->freeze($realNow->subMinutes(20));
            $oCancelRefund = $placeOrder($khach1, $addr1, [[$v2Navy40->id, 1]], '[QA] Đơn trả trước đã thanh toán rồi hủy — chờ Admin duyệt hoàn tiền', PaymentMethod::PrepaidDemo);
            $oCancelRefund = $demoPayment->complete($oCancelRefund, $khach1, true);
            $clock->freeze($realNow->subMinutes(19));
            $workflow->cancel($oCancelRefund, $khach1, OrderStatus::Pending->value, 'Khách đặt nhầm size, yêu cầu hủy và hoàn tiền.', true);

            $clock->freeze($realNow->subMinutes(15));
            $placeOrder($khach1, $addr1, [[$v1Black40->id, 1]], '[QA] Đơn mới chờ xác nhận');

            $oPacking = $placeOrder($khach1, $addr1, [[$v1Black41->id, 1]], '[QA] Đơn đang đóng gói');
            $workflow->confirmAndPack($oPacking, $staffQa, OrderStatus::Pending->value);

            $oOnDelivery = $placeOrder($khach2, $addr2, [[$v1Cream40->id, 1]], '[QA] Đơn đang giao hàng');
            $oOnDelivery = $workflow->confirmAndPack($oOnDelivery, $staffQa, OrderStatus::Pending->value);
            $workflow->ship($oOnDelivery, $staffQa, OrderStatus::Packing->value, 'GHN', 'GHN-QA-ONDELIV');

            $oDeliveredGrouped = $placeOrder($khach1, $addr1, [[$v1Black40->id, 2]], '[QA] Đã giao 2 đôi giống nhau — chưa đánh giá');
            $oDeliveredGrouped = $workflow->confirmAndPack($oDeliveredGrouped, $staffQa, OrderStatus::Pending->value);
            $oDeliveredGrouped = $workflow->ship($oDeliveredGrouped, $staffQa, OrderStatus::Packing->value, 'GHTK', 'GHTK-QA-DELIV-2P');
            $workflow->deliver($oDeliveredGrouped, $staffQa, OrderStatus::OnDelivery->value, $makeProof(7));

            $oDeliveredMulti = $placeOrder($khach1, $addr1, [[$v1Cream41->id, 1], [$v2Navy39->id, 1]], '[QA] Đã giao 2 sản phẩm khác nhau — chưa đánh giá');
            $oDeliveredMulti = $workflow->confirmAndPack($oDeliveredMulti, $staffQa, OrderStatus::Pending->value);
            $oDeliveredMulti = $workflow->ship($oDeliveredMulti, $staffQa, OrderStatus::Packing->value, 'GHN', 'GHN-QA-DELIV-MULTI');
            $workflow->deliver($oDeliveredMulti, $staffQa, OrderStatus::OnDelivery->value, $makeProof(8));

            $oDeliveredReviewed = $placeOrder($khach2, $addr2, [[$v1Black41->id, 1]], '[QA] Đã giao & đã đánh giá 4.5 sao');
            $oDeliveredReviewed = $workflow->confirmAndPack($oDeliveredReviewed, $staffQa, OrderStatus::Pending->value);
            $oDeliveredReviewed = $workflow->ship($oDeliveredReviewed, $staffQa, OrderStatus::Packing->value, 'ViettelPost', 'VTP-QA-REV');
            $oDeliveredReviewed = $workflow->deliver($oDeliveredReviewed, $staffQa, OrderStatus::OnDelivery->value, $makeProof(9));
            $oDeliveredReviewed->load('items.units');
            $pubReview = $reviewService->submit($khach2, $oDeliveredReviewed->items->first()->units->first()->id, '4.5', 'Giày đi rất êm chân, form chuẩn size 41, đóng gói kỹ.');
            $reviewService->reply($staffQa, $pubReview, 'Cảm ơn chị Bảo Ngọc đã tin tưởng ủng hộ Cena Store ạ!');

            // Failed-delivery return scenarios
            $oFailPending = $placeOrder($khach1, $addr1, [[$v1Black40->id, 2]], '[QA] Giao thất bại — Chờ nhận hàng hoàn');
            $oFailPending = $workflow->confirmAndPack($oFailPending, $staffQa, OrderStatus::Pending->value);
            $oFailPending = $workflow->ship($oFailPending, $staffQa, OrderStatus::Packing->value, 'GHN', 'GHN-QA-FAIL-01');
            $workflow->fail($oFailPending, $staffQa, OrderStatus::OnDelivery->value, 'Khách không nghe máy 3 lần liên tiếp, bưu tá hoàn hàng.');

            $oFailPartial = $placeOrder($khach2, $addr2, [[$v1Cream40->id, 1], [$v1Cream41->id, 1]], '[QA] Giao thất bại — Đã nhận một phần (Hàng chờ kiểm tra)');
            $oFailPartial = $workflow->confirmAndPack($oFailPartial, $staffQa, OrderStatus::Pending->value);
            $oFailPartial = $workflow->ship($oFailPartial, $staffQa, OrderStatus::Packing->value, 'GHTK', 'GHTK-QA-FAIL-02');
            $oFailPartial = $workflow->fail($oFailPartial, $staffQa, OrderStatus::OnDelivery->value, 'Sai địa chỉ giao hàng, đang hoàn về kho.');
            $oFailPartial->load('items.units');
            $shipPartial = Shipment::query()->where('order_id', $oFailPartial->id)->firstOrFail();
            $returnService->receiveUnit($shipPartial, $oFailPartial->items[0]->units->first()->id, $staffQa, 'Đã nhận 1 đôi về kho cách ly, chờ kiểm tra hộp.');

            $oFailDamaged = $placeOrder($khach1, $addr1, [[$v1Black40->id, 1], [$v2Navy39->id, 1]], '[QA] Giao thất bại — Hàng hỏng chờ Admin xử lý');
            $oFailDamaged = $workflow->confirmAndPack($oFailDamaged, $staffQa, OrderStatus::Pending->value);
            $oFailDamaged = $workflow->ship($oFailDamaged, $staffQa, OrderStatus::Packing->value, 'GHN', 'GHN-QA-FAIL-03');
            $oFailDamaged = $workflow->fail($oFailDamaged, $staffQa, OrderStatus::OnDelivery->value, 'Khách từ chối nhận do hộp móp khi vận chuyển.');
            $oFailDamaged->load('items.units');
            $shipDamaged = Shipment::query()->where('order_id', $oFailDamaged->id)->firstOrFail();
            $returnService->receiveAndRestock($shipDamaged, $oFailDamaged->items[0]->units->first()->id, $staffQa, 'Đôi 1 còn nguyên vẹn, nhập lại kho bán.');
            $returnService->receiveAndFlagDamaged($shipDamaged, $oFailDamaged->items[1]->units->first()->id, $staffQa, 'Đôi 2 bị rách mũi giày và thấm nước mưa khi hoàn hàng, chờ Admin xử lý.');

            $oFailDone = $placeOrder($khach2, $addr2, [[$v1Cream41->id, 1], [$v2Navy40->id, 1]], '[QA] Giao thất bại — Đã xử lý hoàn tất');
            $oFailDone = $workflow->confirmAndPack($oFailDone, $staffQa, OrderStatus::Pending->value);
            $oFailDone = $workflow->ship($oFailDone, $staffQa, OrderStatus::Packing->value, 'GHN', 'GHN-QA-FAIL-04');
            $oFailDone = $workflow->fail($oFailDone, $staffQa, OrderStatus::OnDelivery->value, 'Không liên hệ được người nhận, hoàn kho.');
            $oFailDone->load('items.units');
            $shipDone = Shipment::query()->where('order_id', $oFailDone->id)->firstOrFail();
            $returnService->receiveAndRestock($shipDone, $oFailDone->items[0]->units->first()->id, $staffQa, 'Đôi 1 nguyên hộp, nhập lại kho bán.');
            $returnService->receiveAndWriteOff($shipDone, $oFailDone->items[1]->units->first()->id, $adminQa, 'Đôi 2 biến dạng đế do ẩm ướt khi hoàn hàng, Admin xác nhận ghi hỏng tổn thất.');
        } finally {
            $clock->unfreeze();
            config([
                'store.demo.enabled' => $previousEnabled,
                'store.demo.password' => $previousPassword,
            ]);
            foreach ($tempFiles as $tmp) {
                @unlink($tmp);
            }
        }
    }

    private function ensureUser(string $email, string $fullName, RoleSlug $roleSlug, string $password, BusinessClock $clock): User
    {
        $existing = User::withTrashed()->where('email', $email)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($email, $fullName, $roleSlug, $password, $clock): User {
            $role = Role::query()->where('slug', $roleSlug->value)->firstOrFail();
            $now = $clock->now();

            $user = new User;
            $user->full_name = $fullName;
            $user->email = $email;
            $user->password = Hash::make($password);
            $user->is_active = true;
            $user->auth_version = 1;
            $user->created_at = $now;
            $user->updated_at = $now;
            $user->save();

            $assignment = new UserRole;
            $assignment->user_id = $user->id;
            $assignment->role_id = $role->id;
            $assignment->assigned_at = $now;
            $assignment->save();

            return $user;
        });
    }

    private function ensureAddress(User $user, string $street, string $phone, BusinessClock $clock): Address
    {
        $existing = Address::query()->where('user_id', $user->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $datasetVersion = (string) config('store.shipping.dataset_version');
        $province = AdministrativeUnit::query()
            ->where('dataset_version', $datasetVersion)
            ->where('level', 'PROVINCE')
            ->orderBy('id')
            ->firstOrFail();
        $ward = AdministrativeUnit::query()
            ->where('dataset_version', $datasetVersion)
            ->where('level', 'WARD')
            ->where('parent_id', $province->id)
            ->orderBy('id')
            ->firstOrFail();

        $address = new Address;
        $address->user_id = $user->id;
        $address->recipient_name = $user->full_name;
        $address->phone = $phone;
        $address->province_id = $province->id;
        $address->ward_id = $ward->id;
        $address->province_code_snapshot = $province->code;
        $address->province_name_snapshot = $province->name;
        $address->ward_code_snapshot = $ward->code;
        $address->ward_name_snapshot = $ward->name;
        $address->dataset_version = $datasetVersion;
        $address->street_address = $street;
        $address->is_default = true;
        $address->created_at = $clock->now();
        $address->updated_at = $clock->now();
        $address->save();

        return $address;
    }

    private function ensureQaSuppliers(): void
    {
        if (! Supplier::withTrashed()->where('code', 'QA-NCC-ACTIVE')->exists()) {
            $active = new Supplier;
            $active->code = 'QA-NCC-ACTIVE';
            $active->name = 'Công ty TNHH Giày Thể Thao Minh Phát (QA)';
            $active->phone = '02838123456';
            $active->email = 'contact@minhphat-qa.test';
            $active->address = '250 Lý Thường Kiệt, Quận 11, TP.HCM';
            $active->is_active = true;
            $active->save();
        }

        if (! Supplier::withTrashed()->where('code', 'QA-NCC-INACTIVE')->exists()) {
            $inactive = new Supplier;
            $inactive->code = 'QA-NCC-INACTIVE';
            $inactive->name = 'Xưởng Gia Công Hoàng Gia (Ngừng hợp tác QA)';
            $inactive->phone = '02838999888';
            $inactive->email = 'old@hoanggia-qa.test';
            $inactive->address = '18 Quốc lộ 1A, Bình Tân, TP.HCM';
            $inactive->is_active = false;
            $inactive->save();
        }
    }

    private function ensureInactiveBanner(): void
    {
        $title = '[QA] Khuyến mãi Mùa Thu (Đã tắt)';
        if (Banner::query()->where('title', $title)->exists()) {
            return;
        }

        $banner = new Banner;
        $banner->title = $title;
        $banner->subtitle = 'Banner mẫu ở trạng thái ngừng hiển thị trên trang chủ.';
        $banner->image_path = 'banners/demo-hero-1.jpg';
        $banner->link_url = '/shoes';
        $banner->cta_text = 'Khám phá ngay';
        $banner->sort_order = 99;
        $banner->is_active = false;
        $banner->save();
    }

    private function ensureQaCoupons(CarbonImmutable $now): void
    {
        $definitions = [
            'CENAQA-FIXED' => [
                'name' => '[QA] Giảm 100.000đ đơn từ 500.000đ',
                'type' => CouponType::Fixed,
                'value' => 100_000,
                'min_order_amount' => 500_000,
                'max_discount_amount' => null,
                'usage_limit' => 50,
                'per_user_limit' => 5,
                'starts_at' => $now->subDays(30)->startOfDay(),
                'expires_at' => $now->addDays(60)->endOfDay(),
                'is_active' => true,
            ],
            'CENAQA-FREESHIP' => [
                'name' => '[QA] Miễn phí vận chuyển toàn quốc',
                'type' => CouponType::FreeShipping,
                'value' => 0,
                'min_order_amount' => 300_000,
                'max_discount_amount' => null,
                'usage_limit' => 100,
                'per_user_limit' => 5,
                'starts_at' => $now->subDays(30)->startOfDay(),
                'expires_at' => $now->addDays(60)->endOfDay(),
                'is_active' => true,
            ],
            'CENAQA-EXPIRED' => [
                'name' => '[QA] Mã giảm 15% (Đã hết hạn)',
                'type' => CouponType::Percentage,
                'value' => 15,
                'min_order_amount' => 0,
                'max_discount_amount' => 150_000,
                'usage_limit' => 20,
                'per_user_limit' => 1,
                'starts_at' => $now->subDays(60)->startOfDay(),
                'expires_at' => $now->subDays(10)->endOfDay(),
                'is_active' => true,
            ],
            'CENAQA-OFF' => [
                'name' => '[QA] Mã giảm 50.000đ (Đang tạm tắt)',
                'type' => CouponType::Fixed,
                'value' => 50_000,
                'min_order_amount' => 0,
                'max_discount_amount' => null,
                'usage_limit' => 20,
                'per_user_limit' => 1,
                'starts_at' => $now->subDays(30)->startOfDay(),
                'expires_at' => $now->addDays(60)->endOfDay(),
                'is_active' => false,
            ],
        ];

        foreach ($definitions as $code => $attrs) {
            if (Coupon::withTrashed()->where('code', $code)->exists()) {
                continue;
            }
            $coupon = new Coupon;
            $coupon->code = $code;
            foreach ($attrs as $field => $val) {
                $coupon->{$field} = $val;
            }
            $coupon->save();
        }
    }

    /** @return array{0: Product, 1: Product} */
    private function ensureQaProducts(User $actor, ProductManagementService $products): array
    {
        $menCategory = Category::query()->where('slug', 'men')->firstOrFail();
        $unisexCategory = Category::query()->where('slug', 'unisex')->firstOrFail();

        $menImg = public_path('legacy/img/men.jpg');
        $womenImg = public_path('legacy/img/women.jpg');
        $unisexImg = public_path('legacy/img/unisex.jpg');

        $sp1 = Product::withTrashed()->where('slug', 'cena-aerostride-pro-qa')->first();
        if ($sp1 === null) {
            $sp1 = $products->save($actor, null, [
                'name' => 'Cena AeroStride Pro QA',
                'slug' => 'cena-aerostride-pro-qa',
                'category_id' => $menCategory->id,
                'brand_line' => 'Cena Running · QA',
                'description' => 'Sản phẩm mẫu kiểm thử đầy đủ tồn kho, biến động kho, hoàn hàng và đánh giá.',
                'base_price' => 2_250_000,
                'promotion_price' => 1_890_000,
                'warranty_months' => 6,
                'exchange_days' => 7,
                'is_active' => 1,
                'colors' => [
                    [
                        'style_color_code' => 'CENA-QA-AS-BLK',
                        'color_name' => 'Đen Than',
                        'color_key' => 'black',
                        'primary_hex' => '#111827',
                        'is_active' => 1,
                        'variants' => [
                            ['size_value' => '40', 'sku' => 'CENA-QA-AS-BLK-40', 'is_active' => 1],
                            ['size_value' => '41', 'sku' => 'CENA-QA-AS-BLK-41', 'is_active' => 1],
                        ],
                        'images' => [
                            ['file' => new UploadedFile($menImg, 'men.jpg', 'image/jpeg', null, true), 'alt_text' => 'Cena AeroStride Pro QA Đen Than', 'is_thumbnail' => true],
                        ],
                    ],
                    [
                        'style_color_code' => 'CENA-QA-AS-CRM',
                        'color_name' => 'Trắng Kem',
                        'color_key' => 'white',
                        'primary_hex' => '#f3f4f6',
                        'is_active' => 1,
                        'variants' => [
                            ['size_value' => '40', 'sku' => 'CENA-QA-AS-CRM-40', 'is_active' => 1],
                            ['size_value' => '41', 'sku' => 'CENA-QA-AS-CRM-41', 'is_active' => 1],
                        ],
                        'images' => [
                            ['file' => new UploadedFile($womenImg, 'women.jpg', 'image/jpeg', null, true), 'alt_text' => 'Cena AeroStride Pro QA Trắng Kem', 'is_thumbnail' => true],
                        ],
                    ],
                ],
            ]);
        }

        $sp2 = Product::withTrashed()->where('slug', 'cena-cloudrun-lite-qa')->first();
        if ($sp2 === null) {
            $sp2 = $products->save($actor, null, [
                'name' => 'Cena CloudRun Lite QA',
                'slug' => 'cena-cloudrun-lite-qa',
                'category_id' => $unisexCategory->id,
                'brand_line' => 'Cena Lifestyle · QA',
                'description' => 'Sản phẩm mẫu kiểm thử sắp hết hàng, hết hàng và trạng thái chưa có đánh giá.',
                'base_price' => 1_650_000,
                'promotion_price' => null,
                'warranty_months' => 6,
                'exchange_days' => 7,
                'is_active' => 1,
                'colors' => [
                    [
                        'style_color_code' => 'CENA-QA-CR-NVY',
                        'color_name' => 'Xanh Navy',
                        'color_key' => 'blue',
                        'primary_hex' => '#1e3a8a',
                        'is_active' => 1,
                        'variants' => [
                            ['size_value' => '39', 'sku' => 'CENA-QA-CR-NVY-39', 'is_active' => 1],
                            ['size_value' => '40', 'sku' => 'CENA-QA-CR-NVY-40', 'is_active' => 1],
                        ],
                        'images' => [
                            ['file' => new UploadedFile($unisexImg, 'unisex.jpg', 'image/jpeg', null, true), 'alt_text' => 'Cena CloudRun Lite QA Xanh Navy', 'is_thumbnail' => true],
                        ],
                    ],
                ],
            ]);
        }

        $sp1->load('colors.variants');
        $sp2->load('colors.variants');

        return [$sp1, $sp2];
    }

    /** @param list<array{0: int, 1: int, 2: int}> $lines */
    private function ensureQaStockImports(
        User $actor,
        StockImportService $imports,
        BusinessClock $clock,
        CarbonImmutable $importInstant,
        array $lines
    ): void {
        $supplier = Supplier::query()->where('code', 'DEMO-NCC-1')->firstOrFail();
        $items = array_map(fn (array $row): array => [
            'variant_id' => $row[0],
            'quantity' => $row[1],
            'unit_cost_amount' => $row[2],
        ], $lines);

        $clock->freeze($importInstant);

        if (! StockImport::query()->where('command_key', self::IMPORT_POSTED_COMMAND_KEY)->exists()) {
            $posted = $imports->saveDraft($actor, null, [
                'command_key' => self::IMPORT_POSTED_COMMAND_KEY,
                'supplier_id' => $supplier->id,
                'note' => 'Phiếu nhập kho đầy đủ QA (đã ghi sổ)',
                'items' => $items,
            ]);
            $imports->post($actor, $posted);
        }

        if (! StockImport::query()->where('command_key', self::IMPORT_DRAFT_COMMAND_KEY)->exists()) {
            $imports->saveDraft($actor, null, [
                'command_key' => self::IMPORT_DRAFT_COMMAND_KEY,
                'supplier_id' => $supplier->id,
                'note' => 'Phiếu nhập kho nháp QA (chỉ Admin thấy và duyệt)',
                'items' => [
                    ['variant_id' => $lines[0][0], 'quantity' => 5, 'unit_cost_amount' => 1_150_000],
                ],
            ]);
        }
    }

    private function findVariant(Product $product, string $colorName, string $sizeValue): ProductVariant
    {
        return $product->colors->firstWhere('color_name', $colorName)
            ->variants->firstWhere('size_value', $sizeValue);
    }
}
