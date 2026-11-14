<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Staff moderation: reply to and hide/unhide reviews. The customer's rating and comment are never edited here.
 */
class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'visible' => ['nullable', 'in:0,1'],
        ]);
        $reviews = Review::query()->with(['user', 'unit.item'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                $query->where(fn ($inner) => $inner
                    ->whereRaw("comment LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->whereRaw("full_name LIKE ? ESCAPE '!'", [$pattern]))
                    ->orWhereHas('unit.item', fn ($itemQuery) => $itemQuery->whereRaw("product_name_snapshot LIKE ? ESCAPE '!'", [$pattern])));
            })
            ->when(isset($filters['rating']) && $filters['rating'] !== '', fn ($query) => $query->where('rating', (int) $filters['rating']))
            ->when(isset($filters['visible']) && $filters['visible'] !== '', fn ($query) => $query->where('is_visible', $filters['visible'] === '1'))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        return view('admin.reviews.index', ['reviews' => $reviews, 'filters' => $filters]);
    }

    public function reply(Request $request, Review $review): RedirectResponse|JsonResponse
    {
        $reply = $request->validate(['admin_reply' => ['nullable', 'string', 'max:2000']])['admin_reply'] ?? null;
        $saved = $this->reviews->reply($request->user(), $review, $reply);
        $message = 'Đã lưu phản hồi.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'reply_content' => $saved->admin_reply,
                'replied_at' => $saved->replied_at?->setTimezone((string) config('store.business_timezone'))->format('d/m/Y H:i'),
            ]);
        }

        return back()->with('status', $message);
    }

    public function visibility(Request $request, Review $review): RedirectResponse|JsonResponse
    {
        $visible = (bool) $request->validate(['visible' => ['required', 'boolean']])['visible'];
        $this->reviews->setVisible($review, $visible);
        $message = $visible ? 'Đã hiện đánh giá.' : 'Đã ẩn đánh giá.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'is_visible' => $visible]);
        }

        return back()->with('status', $message);
    }
}
