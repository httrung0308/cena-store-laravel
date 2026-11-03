<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ArticleRequest;
use App\Models\Article;
use App\Services\ArticleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** News management, Admin only (routes use role:admin). */
class ArticleController extends Controller
{
    public function __construct(private readonly ArticleService $articles) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', Rule::enum(ArticleStatus::class)]]);
        $articles = Article::query()
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('updated_at')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        return view('admin.articles.index', ['articles' => $articles, 'filters' => $filters]);
    }

    public function create(): View
    {
        return view('admin.articles.form', ['article' => new Article]);
    }

    public function edit(Article $article): View
    {
        return view('admin.articles.form', ['article' => $article]);
    }

    public function store(ArticleRequest $request): RedirectResponse
    {
        $this->articles->save($request->user(), null, $request->safe()->only(['title', 'summary', 'body', 'status']), $request->file('cover'));

        return to_route('admin.articles.index')->with('status', 'Đã tạo bài viết.');
    }

    public function update(ArticleRequest $request, Article $article): RedirectResponse
    {
        $this->articles->save($request->user(), $article, $request->safe()->only(['title', 'summary', 'body', 'status']), $request->file('cover'));

        return to_route('admin.articles.index')->with('status', 'Đã lưu bài viết.');
    }

    public function hide(Article $article): RedirectResponse
    {
        $this->articles->hide($article);

        return to_route('admin.articles.index')->with('status', 'Đã ẩn bài viết; bài vẫn được lưu lại.');
    }
}
