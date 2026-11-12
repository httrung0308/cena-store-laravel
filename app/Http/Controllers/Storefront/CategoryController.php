<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\HomeQueryService;
use Illuminate\Contracts\View\View;

/**
 * Trang Danh mục & Phân loại công khai: hiển thị đầy đủ Đối tượng, Kiểu dáng và Mục đích sử dụng đang hoạt động.
 */
class CategoryController extends Controller
{
    public function index(HomeQueryService $homeService): View
    {
        return view('storefront.categories', [
            'categories' => $homeService->categories(),
            'silhouettes' => $homeService->silhouettes(),
            'activities' => $homeService->activities(),
        ]);
    }
}
