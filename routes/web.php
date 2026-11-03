<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'storefront.home')->name('home');

/*
 * Each area lives in its own route file so parallel work never edits the same file.
 * Ownership: docs/collaboration/PLAN_7_CHUC_NANG.md (section 4).
 */
require __DIR__.'/storefront.php';
require __DIR__.'/account.php';
require __DIR__.'/cart.php';
require __DIR__.'/orders.php';
require __DIR__.'/news.php';

Route::middleware(['auth', 'role:admin,staff'])
    ->prefix('admin')
    ->name('admin.')
    ->group(__DIR__.'/admin.php');
