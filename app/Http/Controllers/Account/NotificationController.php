<?php

namespace App\Http\Controllers\Account;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\StoreNotificationResource;
use App\Services\StoreNotificationService;
use App\Support\ApiResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly StoreNotificationService $notifications) {}

    public function index(Request $request): View
    {
        $notifications = $request->user()->storeNotifications()
            ->with('order:id,order_code')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(20);

        return view('account.notifications.index', compact('notifications'));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success($request, ['unread_count' => $this->notifications->unreadCount($request->user())]);
    }

    public function markReadApi(Request $request, int $notification): JsonResponse
    {
        try {
            $record = $this->notifications->markRead($request->user(), $notification);
        } catch (DomainException $exception) {
            if ($exception->errorCode !== ErrorCode::NotFound) {
                throw $exception;
            }

            return ApiResponse::error($request, ErrorCode::NotFound);
        }

        return ApiResponse::success($request, [
            'notification' => (new StoreNotificationResource($record->loadMissing('order:id,order_code')))->resolve($request),
        ], 'Đã đánh dấu thông báo đã đọc.');
    }

    public function markRead(Request $request, int $notification): RedirectResponse
    {
        $this->notifications->markRead($request->user(), $notification);

        return redirect()->route('notifications.index')->with('status', 'Đã đánh dấu thông báo đã đọc.');
    }
}
