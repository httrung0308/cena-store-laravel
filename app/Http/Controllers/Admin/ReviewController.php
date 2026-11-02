<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Contracts\View\View;
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
        $filters = $request->validate(['visible' => ['nullable', 'in:0,1']]);
        $reviews = Review::query()->with(['user', 'unit.item'])
            ->when(isset($filters['visible']), fn ($query) => $query->where('is_visible', $filters['visible'] === '1'))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        return view('admin.reviews.index', ['reviews' => $reviews, 'filters' => $filters]);
    }

    public function reply(Request $request, Review $review): RedirectResponse
    {
        $reply = $request->validate(['admin_reply' => ['nullable', 'string', 'max:2000']])['admin_reply'] ?? null;
        $this->reviews->reply($request->user(), $review, $reply);

        return back()->with('status', 'Đã lưu phản hồi.');
    }

    public function visibility(Request $request, Review $review): RedirectResponse
    {
        $visible = (bool) $request->validate(['visible' => ['required', 'boolean']])['visible'];
        $this->reviews->setVisible($review, $visible);

        return back()->with('status', $visible ? 'Đã hiện đánh giá.' : 'Đã ẩn đánh giá.');
    }
}
