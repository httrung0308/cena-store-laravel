<?php

use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/shoes', [ProductController::class, 'index'])->name('shoes.index');
Route::get('/shoes/{slug}', [ProductController::class, 'show'])->name('shoes.show');
