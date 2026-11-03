<?php

use App\Http\Controllers\Storefront\NewsController;
use Illuminate\Support\Facades\Route;

Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/news/{slug}', [NewsController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('news.show');
