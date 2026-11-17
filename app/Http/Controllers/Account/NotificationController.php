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
        $unreadCount = $this->notifications->unreadCount($request->user());

        return view('account.notifications.index', compact('notifications', 'unreadCount'));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success($request, ['unread_count' => $this->notifications->unreadCount($request->user())]);
    }

    public function recentApi(Request $request): JsonResponse
    {
        $user = $request->user();
        $recent = $this->notifications->recentForUser($user);

        return ApiResponse::success($request, [
            'unread_count' => $this->notifications->unreadCount($user),
            'notifications' => StoreNotificationResource::collection($recent)->resolve($request),
        ]);
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

    public function markAllReadApi(Request $request): JsonResponse
    {
        $count = $this->notifications->markAllRead($request->user());

        return ApiResponse::success($request, [
            'marked_count' => $count,
            'unread_count' => 0,
        ]);
    }

    public function markRead(Request $request, int $notification): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return $this->markReadApi($request, $notification);
        }

        $this->notifications->markRead($request->user(), $notification);

        return redirect()->route('notifications.index');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->notifications->markAllRead($request->user());

        return redirect()->route('notifications.index');
    }
}
