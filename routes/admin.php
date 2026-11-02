<?php

use Illuminate\Support\Facades\Route;

/*
 * Back office (owner: Codex, PR-X1..X3). Loaded from web.php with:
 *   prefix "/admin", name prefix "admin.", middleware ['auth', 'role:admin,staff'].
 * Restrict admin-only actions further with ->middleware('role:admin').
 * The admin sidebar shows a link only when its route exists: admin.categories.index,
 * admin.suppliers.index, admin.products.index, admin.stock-imports.index.
 */
Route::view('/', 'admin.dashboard')->name('dashboard');
