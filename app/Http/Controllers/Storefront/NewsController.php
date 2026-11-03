<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Contracts\View\View;

/** Public news pages: only published articles; drafts and hidden articles answer 404. */
class NewsController extends Controller
{
    public function index(): View
    {
        return view('storefront.news.index', [
            'articles' => Article::query()->published()->orderByDesc('published_at')->orderByDesc('id')->paginate(9),
        ]);
    }

    public function show(string $slug): View
    {
        return view('storefront.news.show', [
            'article' => Article::query()->published()->where('slug', $slug)->firstOrFail(),
        ]);
    }
}
