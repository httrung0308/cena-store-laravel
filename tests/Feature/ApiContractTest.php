<?php

namespace Tests\Feature;

use App\Enums\ErrorCode;
use App\Enums\OrderStatus;
use App\Exceptions\DomainException;
use App\Http\Resources\Customer\OrderResource;
use App\Http\Resources\Customer\ProductResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Support\ApiResponse;
use App\Support\BusinessClock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use DatabaseTransactions;

    public function test_success_uses_integer_vnd_canonical_size_and_server_generated_request_id(): void
    {
        Route::get('/api/__contract/success', fn (Request $request) => ApiResponse::success($request, ['amount' => 1000000, 'size_value' => '40.5'], status: 201));
        $first = $this->withHeader('X-Request-ID', 'untrusted-id')->getJson('/api/__contract/success');
        $first->assertCreated()->assertJsonPath('success', true)->assertJsonPath('data.amount', 1000000)->assertJsonPath('data.size_value', '40.5');
        $id = $first->json('meta.request_id');
        $this->assertTrue(Str::isUuid($id));
        $first->assertHeader('X-Request-ID', $id);
        $second = $this->getJson('/api/__contract/success');
        $this->assertNotSame($id, $second->json('meta.request_id'));
    }

    #[DataProvider('domainCodes')]
    public function test_domain_error_code_and_http_status_are_shared_for_ajax(ErrorCode $code): void
    {
        Route::post('/api/__contract/domain', fn () => throw new DomainException($code));
        $response = $this->postJson('/api/__contract/domain');
        $response->assertStatus($code->httpStatus())->assertJsonPath('success', false)
            ->assertJsonPath('code', $code->value)->assertJsonPath('message', $code->message());
        $this->assertTrue(Str::isUuid($response->json('meta.request_id')));
        $response->assertHeader('X-Request-ID', $response->json('meta.request_id'));
    }

    public static function domainCodes(): iterable
    {
        foreach (ErrorCode::cases() as $code) {
            yield $code->value => [$code];
        }
    }

    public function test_validation_has_field_arrays_and_does_not_redirect_ajax_to_html(): void
    {
        Route::post('/__contract/validate', fn (Request $request) => $request->validate(['quantity' => ['required', 'integer', 'min:1']]));
        $response = $this->withHeader('X-Requested-With', 'XMLHttpRequest')->withHeader('Accept', 'text/html')->post('/__contract/validate', ['quantity' => 0]);
        $response->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED')->assertJsonStructure(['errors' => ['quantity'], 'meta' => ['request_id']]);
        $this->assertIsArray($response->json('errors.quantity'));
    }

    public function test_explicit_framework_response_exceptions_keep_their_intended_response(): void
    {
        Route::get('/api/__contract/explicit', fn (Request $request) => throw new HttpResponseException(ApiResponse::error($request, ErrorCode::ValidationFailed, ['quantity' => ['Số lượng không hợp lệ.']])));
        $this->getJson('/api/__contract/explicit')->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('errors.quantity.0', 'Số lượng không hợp lệ.');
    }

    public function test_ajax_authentication_csrf_authorization_and_throttle_keep_their_real_status(): void
    {
        $exceptions = [
            'auth' => [new AuthenticationException('PRIVATE-AUTH'), 401, 'UNAUTHENTICATED'],
            'csrf' => [new TokenMismatchException('PRIVATE-CSRF'), 419, 'SESSION_EXPIRED'],
            'permission' => [new AuthorizationException('PRIVATE-PERMISSION'), 403, 'FORBIDDEN'],
            'throttle' => [new HttpException(429, 'PRIVATE-THROTTLE', headers: ['Retry-After' => '60']), 429, 'TOO_MANY_REQUESTS'],
            'maintenance' => [new HttpException(503, 'PRIVATE-MAINTENANCE', headers: ['Retry-After' => '120']), 503, 'SERVICE_UNAVAILABLE'],
        ];
        foreach ($exceptions as $key => [$exception, $status, $code]) {
            Route::get('/__contract/'.$key, fn () => throw $exception);
            $response = $this->withHeader('X-Requested-With', 'XMLHttpRequest')->withHeader('Accept', 'text/html')->get('/__contract/'.$key);
            $response->assertStatus($status)->assertJsonPath('code', $code)->assertJsonPath('success', false);
            $this->assertStringNotContainsString('PRIVATE-', $response->getContent());
            if ($status === 429) {
                $response->assertHeader('Retry-After', '60');
            }
        }
    }

    public function test_missing_endpoint_and_wrong_method_return_json_with_correct_status_and_allow_header(): void
    {
        $this->getJson('/api/__contract/missing')->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
        Route::get('/api/__contract/method', fn () => 'ok');
        $this->postJson('/api/__contract/method')->assertStatus(405)->assertJsonPath('code', 'METHOD_NOT_ALLOWED')->assertHeader('Allow', 'GET, HEAD');
    }

    public function test_real_auth_middleware_returns_json_401_without_a_login_route_for_ajax_and_api_paths(): void
    {
        Route::middleware('auth')->get('/__contract/protected', fn () => 'PRIVATE');
        Route::middleware('auth')->get('/api/__contract/protected', fn () => 'PRIVATE');
        $this->withHeader('X-Requested-With', 'XMLHttpRequest')->withHeader('Accept', 'text/html')
            ->get('/__contract/protected')->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
        $this->flushHeaders();
        $this->withHeader('Accept', 'text/html')->get('/api/__contract/protected')
            ->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_browser_auth_redirects_to_the_named_login_route_while_ajax_still_gets_401(): void
    {
        Route::middleware('auth')->get('/__contract/guarded', fn () => 'PRIVATE');
        $this->get('/__contract/guarded')->assertRedirect(route('login'));
        $this->withHeader('X-Requested-With', 'XMLHttpRequest')->withHeader('Accept', 'text/html')
            ->get('/__contract/guarded')->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_server_failure_is_safe_even_when_debug_is_enabled(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/__contract/failure', fn () => throw new RuntimeException('PRIVATE SQL SELECT password FROM users C:/private/.env'));
        $response = $this->getJson('/api/__contract/failure');
        $response->assertStatus(500)->assertJsonPath('code', 'INTERNAL_ERROR')->assertJsonStructure(['meta' => ['request_id']]);
        foreach (['PRIVATE', 'SELECT', 'password', '.env', 'RuntimeException', 'trace', 'exception', 'file', 'line'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
        $this->assertSame(['success', 'code', 'message', 'errors', 'meta'], array_keys($response->json()));
    }

    public function test_server_error_never_includes_domain_field_details(): void
    {
        Route::get('/api/__contract/server-domain', fn () => throw new DomainException(ErrorCode::InternalError, ['internal' => ['PRIVATE-SQL']]));
        $response = $this->getJson('/api/__contract/server-domain');
        $response->assertStatus(500)->assertJsonPath('code', 'INTERNAL_ERROR');
        $this->assertSame([], $response->json('errors'));
        $this->assertStringNotContainsString('PRIVATE-SQL', $response->getContent());
    }

    public function test_blade_business_errors_flash_safe_messages_and_never_flash_credentials(): void
    {
        Route::middleware('web')->post('/__contract/blade', fn () => throw new DomainException(ErrorCode::CartStale, ['cart' => ['Vui lòng tải lại giỏ.']]));
        $response = $this->from('/')->post('/__contract/blade', [
            'quantity' => '2', 'password' => 'PRIVATE-PASSWORD', 'otp' => '123456', 'token' => 'PRIVATE-TOKEN', 'account_number' => 'PRIVATE-BANK',
        ]);
        $response->assertRedirect('/')->assertSessionHasErrors('cart')->assertSessionHas('error_code', 'CART_STALE')->assertSessionHas('error', ErrorCode::CartStale->message());
        $this->assertSame(['quantity' => '2'], session()->getOldInput());
    }

    public function test_blade_validation_uses_framework_error_bag_and_sensitive_input_filter(): void
    {
        Route::middleware('web')->post('/__contract/blade-validation', fn () => throw ValidationException::withMessages(['email' => ['Email không hợp lệ.']]));
        $this->from('/')->post('/__contract/blade-validation', ['email' => 'bad', 'password' => 'PRIVATE', 'otp_code' => 'PRIVATE'])
            ->assertRedirect('/')->assertSessionHasErrors('email');
        $this->assertSame(['email' => 'bad'], session()->getOldInput());
    }

    public function test_browser_forbidden_and_internal_failure_do_not_return_success_redirects_or_debug_pages(): void
    {
        config(['app.debug' => true]);
        Route::get('/__contract/browser-forbidden', fn () => abort(403, 'PRIVATE-FORBIDDEN'));
        Route::get('/__contract/browser-failure', fn () => throw new RuntimeException('PRIVATE-INTERNAL'));
        $forbidden = $this->get('/__contract/browser-forbidden');
        $forbidden->assertForbidden();
        $this->assertStringNotContainsString('PRIVATE-', $forbidden->getContent());
        $failure = $this->get('/__contract/browser-failure');
        $failure->assertStatus(500)->assertSee('Mã tra cứu:');
        $this->assertStringNotContainsString('PRIVATE-', $failure->getContent());
    }

    public function test_existing_customer_resource_and_nested_collection_share_success_envelope_without_nested_envelopes(): void
    {
        $product = Product::factory()->create()->fresh();
        $color = ProductColor::factory()->create(['product_id' => $product->id]);
        ProductVariant::factory()->create(['product_color_id' => $color->id, 'size_value' => '40.5', 'size_numeric' => '40.5']);
        $product->load('colors.variants');
        Route::get('/api/__contract/product', fn () => new ProductResource($product));
        $response = $this->getJson('/api/__contract/product');
        $response->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.id', $product->id)->assertJsonStructure(['meta' => ['request_id']]);
        $this->assertArrayNotHasKey('success', $response->json('data'));
        $this->assertArrayNotHasKey('success', $response->json('data.colors.0'));
        $this->assertArrayNotHasKey('success', $response->json('data.colors.0.variants.0'));
        $this->assertArrayNotHasKey('average_cost', $response->json('data.colors.0.variants.0'));
        $response->assertJsonPath('data.colors.0.variants.0.size_value', '40.5');
        $this->assertIsInt($response->json('data.base_price'));
        Route::get('/api/__contract/products', fn () => ProductResource::collection(Product::query()->paginate(2)));
        $collection = $this->getJson('/api/__contract/products');
        $collection->assertOk()->assertJsonPath('success', true)->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'total', 'request_id']]);
        $this->assertCount(1, $collection->json('data'));
        $this->assertArrayNotHasKey('success', $collection->json('data.0'));
        Route::get('/api/__contract/plain-products', fn () => ProductResource::collection(Product::all()));
        $this->getJson('/api/__contract/plain-products')->assertOk()->assertJsonPath('success', true)->assertJsonStructure(['data', 'meta' => ['request_id']]);
    }

    public function test_status_labels_are_separate_from_canonical_values_and_owner_guards_remain_active(): void
    {
        $order = Order::factory()->create()->fresh();
        Route::get('/api/__contract/order', fn () => new OrderResource($order));
        $this->getJson('/api/__contract/order')->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
        $this->actingAs($order->user)->getJson('/api/__contract/order')->assertOk()
            ->assertJsonPath('data.order_status', 'PENDING')->assertJsonPath('data.status_label', OrderStatus::Pending->label())
            ->assertJsonPath('data.order_status_label', OrderStatus::Pending->label())->assertJsonPath('data.payment_status', 'UNPAID');
    }

    public function test_container_clock_and_limits_match_the_canonical_configuration(): void
    {
        $clock = app(BusinessClock::class);
        $this->assertSame($clock, app(BusinessClock::class));
        $clock->freeze('2026-10-31T03:00:00Z');
        $this->assertSame(900, $clock->deadline('otp')->getTimestamp() - $clock->now()->getTimestamp());
        $this->assertSame(['order_total' => 1000000000, 'variant_quantity' => 10, 'cart_lines' => 50, 'otp_attempts' => 5], config('store.limits'));
        $this->assertSame(['otp_minutes' => 15, 'otp_resend_seconds' => 60, 'online_payment_minutes' => 30, 'cod_confirmation_hours' => 24, 'rma_receive_days' => 7], config('store.deadlines'));
        $clock->unfreeze();
        foreach (glob(app_path('Enums/*.php')) as $file) {
            $enum = 'App\\Enums\\'.basename($file, '.php');
            foreach ($enum::cases() as $case) {
                $this->assertNotSame('', $case->label());
                $this->assertIsString($case->value);
            }
        }
    }
}
