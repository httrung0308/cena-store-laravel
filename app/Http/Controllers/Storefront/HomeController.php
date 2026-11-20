<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\HomeQueryService;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function index(HomeQueryService $homeService): View
    {
        return view('storefront.home', $homeService->data());
    }
}
