<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\ArticleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Public news pages: only published articles; drafts and hidden articles answer 404. */
class NewsController extends Controller
{
    /** Latest other published articles shown under an article. */
    private const RELATED_COUNT = 3;

    public function __construct(private readonly ArticleService $articles) {}

    public function index(): View
    {
        return view('storefront.news.index', [
            'articles' => Article::query()->published()->orderByDesc('published_at')->orderByDesc('id')->paginate(9),
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $article = Article::query()->published()->where('slug', $slug)->firstOrFail();

        return view('storefront.news.show', [
            'article' => $article,
            'myFeedback' => $this->articles->feedbackOf($article, $request->user(), $request->session()->getId()),
            'helpfulCount' => $article->feedback()->where('is_helpful', true)->count(),
            'related' => Article::query()->published()->whereKeyNot($article->id)
                ->orderByDesc('published_at')->orderByDesc('id')->limit(self::RELATED_COUNT)->get(),
        ]);
    }

    /** "Was this helpful?" from any reader, signed in or not; answering again changes the answer. */
    public function feedback(Request $request, string $slug): RedirectResponse
    {
        $data = $request->validate(['helpful' => ['required', 'in:yes,no']]);
        $article = Article::query()->published()->where('slug', $slug)->firstOrFail();
        $this->articles->recordFeedback($article, $request->user(), $request->session()->getId(), $data['helpful'] === 'yes');

        return redirect()->to(route('news.show', $article->slug).'#article-feedback')->with('feedback_saved', true);
    }
}
