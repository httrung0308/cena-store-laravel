<?php

use App\Http\Controllers\Cart\CartController;
use App\Http\Middleware\EnsureNonAdminShopping;
use Illuminate\Support\Facades\Route;

/*
 * Server-side cart for guests and signed-in customers (owner: Claude, PR-C4).
 * JSON only; the drawer in components/storefront/panels uses these routes.
 */
Route::middleware([EnsureNonAdminShopping::class, 'throttle:60,1'])->prefix('cart')->name('cart.')->group(function (): void {
    Route::get('/', [CartController::class, 'show'])->name('show');
    Route::post('/items', [CartController::class, 'store'])->name('items.store');
    Route::post('/clear', [CartController::class, 'clear'])->name('clear');
    Route::patch('/items/{item}', [CartController::class, 'update'])->whereNumber('item')->name('items.update');
    Route::delete('/items/{item}', [CartController::class, 'destroy'])->whereNumber('item')->name('items.destroy');
});
