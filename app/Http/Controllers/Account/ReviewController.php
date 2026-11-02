<?php

namespace App\Http\Controllers\Account;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ReviewRequest;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;

/**
 * Create or edit the signed-in customer's review of one delivered pair.
 */
class ReviewController extends Controller
{
    public function store(ReviewRequest $request, ReviewService $reviews): RedirectResponse
    {
        try {
            $reviews->submit($request->user(), (int) $request->validated('unit_id'), (string) $request->validated('rating'), (string) $request->validated('comment'));
        } catch (DomainException $exception) {
            abort_if($exception->errors === [], 404);

            return back()->withErrors($exception->errors);
        }

        return back()->with('status', 'Cảm ơn bạn đã đánh giá!');
    }
}
