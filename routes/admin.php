<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\SupplierController;
use Illuminate\Support\Facades\Route;

/*
 * Back office (owner: Codex, PR-X1..X3). Loaded from web.php with:
 *   prefix "/admin", name prefix "admin.", middleware ['auth', 'role:admin,staff'].
 * Restrict admin-only actions further with ->middleware('role:admin').
 * The admin sidebar shows a link only when its route exists: admin.categories.index,
 * admin.suppliers.index, admin.products.index, admin.stock-imports.index.
 */
Route::view('/', 'admin.dashboard')->name('dashboard');

Route::resource('categories', CategoryController::class)->except(['show', 'destroy']);
Route::patch('categories/{category}/deactivate', [CategoryController::class, 'deactivate'])->name('categories.deactivate');

Route::resource('suppliers', SupplierController::class)->except(['show', 'destroy']);
Route::patch('suppliers/{supplier}/deactivate', [SupplierController::class, 'deactivate'])->name('suppliers.deactivate');
