<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CatalogRequest;
use App\Services\CatalogQueryService;
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

    public function show(CatalogRequest $request, string $slug, CatalogQueryService $catalog): View
    {
        return view('catalog.show', $catalog->detail($slug, $request->validated('style_color')));
    }
}
