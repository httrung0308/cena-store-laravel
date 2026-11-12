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
        $data = [
            'paginator' => $catalog->listing($filters),
            'filterProps' => $catalog->filterOptions() + [
                'filters' => $filters,
                'action' => route('shoes.index'),
                'resetUrl' => route('shoes.index'),
            ],
        ];

        if ($request->header('X-Catalog-Partial') === '1') {
            return view('components.catalog.listing', $data);
        }

        return view('catalog.index', $data);
    }

    public function show(CatalogRequest $request, string $slug, CatalogQueryService $catalog, ReviewService $reviews): View
    {
        $detail = $catalog->detail($slug, $request->validated('style_color'));
        $productId = $detail['product']['id'];

        $rawRatingFilter = $request->query('review_rating');
        $reviewRatingFilter = is_numeric($rawRatingFilter) && (int) $rawRatingFilter >= 1 && (int) $rawRatingFilter <= 5
            ? (int) $rawRatingFilter
            : null;
        $reviewWithReply = $request->boolean('with_reply');

        $reviewPaginator = $reviews->visibleFilteredFor($productId, $reviewRatingFilter, $reviewWithReply)
            ->join('users as author', 'author.id', '=', 'r.user_id')
            ->select(['r.rating', 'r.comment', 'r.admin_reply', 'r.created_at', 'author.full_name', 'c.color_name', 'oi.size_value_snapshot'])
            ->paginate(6, ['*'], 'review_page')
            ->withQueryString()
            ->fragment('product-reviews');

        $detail['reviews'] = $reviewPaginator->getCollection()
            ->map(fn (object $review): array => [
                'rating' => (float) $review->rating,
                'comment' => $review->comment,
                'reply' => $review->admin_reply,
                'date' => $review->created_at,
                'author' => $this->maskName($review->full_name),
                'purchase' => trim($review->color_name.' · Size '.$review->size_value_snapshot),
            ])->all();

        $detail['reviewPaginator'] = $reviewPaginator;
        $detail['reviewSummary'] = $reviews->summaryFor($productId);
        $detail['reviewFilters'] = [
            'rating' => $reviewRatingFilter,
            'with_reply' => $reviewWithReply,
        ];
        $detail['customerReviewState'] = $reviews->customerReviewState($request->user(), $productId);
        $detail['relatedProducts'] = $catalog->relatedProducts(
            $productId,
            (int) $detail['product']['category_id'],
            (string) $detail['product']['gender_slug']
        );

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
