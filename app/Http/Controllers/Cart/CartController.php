<?php

namespace App\Http\Controllers\Cart;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\CartVersionRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Models\Cart;
use App\Services\CartService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoints used by the cart drawer. The cart is always resolved from the session (guest token)
 * or the signed-in user; item ids from another cart are 404.
 */
class CartController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function show(Request $request): JsonResponse
    {
        $notices = $request->session()->get(CartService::SESSION_NOTICES, []);

        return ApiResponse::success($request, $this->carts->present($this->carts->find($request->user(), $request->session()), is_array($notices) ? $notices : []));
    }

    public function store(AddCartItemRequest $request): JsonResponse
    {
        return $this->respond($request, fn () => $this->carts->add(
            $request->user(), $request->session(),
            (int) $request->validated('variant_id'), (int) $request->validated('quantity'),
            $request->validated('version') === null ? null : (int) $request->validated('version'),
        ), 'Đã thêm vào giỏ hàng.');
    }

    public function update(UpdateCartItemRequest $request, int $item): JsonResponse
    {
        return $this->respond($request, fn () => $this->carts->update(
            $request->user(), $request->session(), $item, (int) $request->validated('quantity'), (int) $request->validated('version'),
        ), 'Đã cập nhật số lượng.');
    }

    public function destroy(CartVersionRequest $request, int $item): JsonResponse
    {
        return $this->respond($request, fn () => $this->carts->remove(
            $request->user(), $request->session(), $item, (int) $request->validated('version'),
        ), 'Đã xóa khỏi giỏ hàng.');
    }

    public function clear(CartVersionRequest $request): JsonResponse
    {
        return $this->respond($request, fn () => $this->carts->clear(
            $request->user(), $request->session(), (int) $request->validated('version'),
        ), 'Đã xóa toàn bộ giỏ hàng.');
    }

    /**
     * A stale version answers 409 together with the current cart so the drawer can reconcile.
     *
     * @param  Closure(): Cart  $action
     */
    private function respond(Request $request, Closure $action, string $message): JsonResponse
    {
        try {
            $cart = $action();
        } catch (DomainException $exception) {
            if ($exception->errorCode !== ErrorCode::CartStale) {
                throw $exception;
            }
            $response = ApiResponse::error($request, ErrorCode::CartStale);
            $payload = $response->getData(true);
            $payload['data'] = $this->carts->present($this->carts->find($request->user(), $request->session()));

            return $response->setData($payload);
        }

        return ApiResponse::success($request, $this->carts->present($cart), $message);
    }
}
