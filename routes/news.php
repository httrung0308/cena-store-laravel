<?php

use App\Http\Controllers\Storefront\NewsController;
use Illuminate\Support\Facades\Route;

Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/news/{slug}', [NewsController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('news.show');
Route::post('/news/{slug}/feedback', [NewsController::class, 'feedback'])->where('slug', '[a-z0-9-]+')->middleware('throttle:20,1')->name('news.feedback');
