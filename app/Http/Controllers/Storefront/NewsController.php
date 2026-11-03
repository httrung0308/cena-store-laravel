<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Contracts\View\View;

/** Public news pages: only published articles; drafts and hidden articles answer 404. */
class NewsController extends Controller
{
    /** Latest other published articles shown under an article. */
    private const RELATED_COUNT = 3;

    public function index(): View
    {
        return view('storefront.news.index', [
            'articles' => Article::query()->published()->orderByDesc('published_at')->orderByDesc('id')->paginate(9),
        ]);
    }

    public function show(string $slug): View
    {
        $article = Article::query()->published()->where('slug', $slug)->firstOrFail();

        return view('storefront.news.show', [
            'article' => $article,
            'related' => Article::query()->published()->whereKeyNot($article->id)
                ->orderByDesc('published_at')->orderByDesc('id')->limit(self::RELATED_COUNT)->get(),
        ]);
    }
}
