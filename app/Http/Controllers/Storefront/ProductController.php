<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CatalogRequest;
use App\Services\CatalogQueryService;
use App\Services\ReviewService;
use Illuminate\Contracts\View\View;

/**
 * Public catalogue pages. Read-only: listing and detail never touch stock or the ledgers.
 */
class ProductController extends Controller
{
    public function index(CatalogRequest $request, CatalogQueryService $catalog): View
    {
        $filters = $request->filters();

        return view('catalog.index', [
            'paginator' => $catalog->listing($filters),
            'filterProps' => $catalog->filterOptions() + [
                'filters' => $filters,
                'action' => route('shoes.index'),
                'resetUrl' => route('shoes.index'),
            ],
        ]);
    }

    public function show(CatalogRequest $request, string $slug, CatalogQueryService $catalog, ReviewService $reviews): View
    {
        $detail = $catalog->detail($slug, $request->validated('style_color'));
        $detail['reviews'] = $reviews->visibleFor($detail['product']['id'])
            ->join('users as author', 'author.id', '=', 'r.user_id')
            ->limit(20)
            ->get(['r.rating', 'r.comment', 'r.admin_reply', 'r.created_at', 'author.full_name', 'c.color_name', 'oi.size_value_snapshot'])
            ->map(fn (object $review): array => [
                'rating' => (float) $review->rating,
                'comment' => $review->comment,
                'reply' => $review->admin_reply,
                'date' => $review->created_at,
                'author' => $this->maskName($review->full_name),
                'purchase' => trim($review->color_name.' · Size '.$review->size_value_snapshot),
            ])->all();

        return view('catalog.show', $detail);
    }

    /** Public pages show "Minh N." instead of the full name. */
    private function maskName(?string $name): string
    {
        $parts = preg_split('/\s+/u', trim((string) $name)) ?: [];
        if ($parts === [] || $parts[0] === '') {
            return 'Khách hàng';
        }
        $last = array_pop($parts);

        return $parts === [] ? $last : $last.' '.mb_substr($parts[0], 0, 1).'.';
    }
}
