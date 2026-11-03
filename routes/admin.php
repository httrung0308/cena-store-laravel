<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\StockImportController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
 * Back office (owner: Codex, PR-X1..X3). Loaded from web.php with:
 *   prefix "/admin", name prefix "admin.", middleware ['auth', 'role:admin,staff'].
 * Restrict admin-only actions further with ->middleware('role:admin').
 * The admin sidebar shows a link only when its route exists: admin.categories.index,
 * admin.suppliers.index, admin.products.index, admin.stock-imports.index.
 */
Route::view('/', 'admin.dashboard')->name('dashboard');
Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

Route::resource('categories', CategoryController::class)->except(['show', 'destroy']);
Route::patch('categories/{category}/deactivate', [CategoryController::class, 'deactivate'])->name('categories.deactivate');

Route::resource('suppliers', SupplierController::class)->except(['show', 'destroy']);
Route::patch('suppliers/{supplier}/deactivate', [SupplierController::class, 'deactivate'])->name('suppliers.deactivate');

Route::resource('products', ProductController::class)->except(['show', 'destroy']);
Route::patch('products/{product}/deactivate', [ProductController::class, 'deactivate'])->name('products.deactivate');

Route::resource('stock-imports', StockImportController::class)->except('destroy');
Route::post('stock-imports/{stock_import}/post', [StockImportController::class, 'post'])->name('stock-imports.post');
Route::post('stock-imports/{stock_import}/cancel', [StockImportController::class, 'cancel'])->name('stock-imports.cancel');

Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order')->name('orders.show');
Route::post('orders/{order}/{action}', [OrderController::class, 'transition'])->whereNumber('order')
    ->whereIn('action', ['confirm', 'pack', 'ship', 'deliver', 'cancel', 'fail'])->middleware('throttle:30,1')->name('orders.transition');

Route::middleware('role:admin')->prefix('users')->name('users.')->whereNumber('user')->group(function (): void {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/{user}', [UserController::class, 'show'])->name('show');
    Route::patch('/{user}', [UserController::class, 'update'])->name('update');
    Route::patch('/{user}/active', [UserController::class, 'active'])->name('active');
    Route::put('/{user}/roles', [UserController::class, 'roles'])->name('roles');
});

Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
Route::patch('reviews/{review}/reply', [ReviewController::class, 'reply'])->whereNumber('review')->name('reviews.reply');
Route::patch('reviews/{review}/visibility', [ReviewController::class, 'visibility'])->whereNumber('review')->name('reviews.visibility');
