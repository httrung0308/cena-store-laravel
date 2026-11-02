<?php

use App\Http\Controllers\Account\OrderController;
use App\Http\Controllers\Storefront\CheckoutController;
use Illuminate\Support\Facades\Route;

/*
 * Checkout (COD) and the customer's own orders. Loaded from web.php.
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');

    Route::get('/account/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/account/orders/{orderCode}', [OrderController::class, 'show'])->where('orderCode', 'ORD-[0-9]{8}-[0-9A-Z]{26}')->name('orders.show');
});
