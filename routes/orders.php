<?php

use App\Http\Controllers\Account\OrderController;
use App\Http\Controllers\Account\ReviewController;
use App\Http\Controllers\Account\DemoPaymentController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Middleware\EnsureNonAdminShopping;
use Illuminate\Support\Facades\Route;

/*
 * Checkout (COD) and the customer's own orders. Loaded from web.php.
 */
Route::middleware(['auth', EnsureNonAdminShopping::class])->group(function (): void {
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
    Route::get('/account/orders/{orderCode}/payment', [DemoPaymentController::class, 'show'])->where('orderCode', 'ORD-[0-9]{8}-[0-9A-Z]{26}')->name('orders.payment.show');
    Route::post('/account/orders/{orderCode}/payment', [DemoPaymentController::class, 'complete'])->where('orderCode', 'ORD-[0-9]{8}-[0-9A-Z]{26}')->middleware('throttle:10,1')->name('orders.payment.complete');
    Route::post('/account/orders/{orderCode}/payment/retry', [DemoPaymentController::class, 'retry'])->where('orderCode', 'ORD-[0-9]{8}-[0-9A-Z]{26}')->middleware('throttle:10,1')->name('orders.payment.retry');

    Route::get('/account/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/account/orders/{orderCode}', [OrderController::class, 'show'])->where('orderCode', 'ORD-[0-9]{8}-[0-9A-Z]{26}')->name('orders.show');
    Route::post('/account/orders/{orderCode}/cancel', [OrderController::class, 'cancel'])->where('orderCode', 'ORD-[0-9]{8}-[0-9A-Z]{26}')->middleware('throttle:10,1')->name('orders.cancel');
    Route::post('/account/reviews', [ReviewController::class, 'store'])->middleware('throttle:20,1')->name('account.reviews.store');
});
