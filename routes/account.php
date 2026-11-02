<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\AdministrativeUnitController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

/*
 * Sign-in, registration, sign-out, account overview, address book and the province/ward lookup API
 * (owner: Claude, PR-C1..C3). The route named "login" must live here: guests hitting
 * an "auth" route are redirected to it.
 */
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1')->name('register.store');
});

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');
Route::get('/account', AccountController::class)->middleware('auth')->name('account.index');

Route::middleware('auth')->prefix('account/addresses')->name('account.addresses.')->whereNumber('address')->group(function (): void {
    Route::get('/', [AddressController::class, 'index'])->name('index');
    Route::get('/create', [AddressController::class, 'create'])->name('create');
    Route::post('/', [AddressController::class, 'store'])->name('store');
    Route::get('/{address}/edit', [AddressController::class, 'edit'])->name('edit');
    Route::put('/{address}', [AddressController::class, 'update'])->name('update');
    Route::delete('/{address}', [AddressController::class, 'destroy'])->name('destroy');
    Route::patch('/{address}/default', [AddressController::class, 'makeDefault'])->name('default');
});

Route::middleware('throttle:120,1')->prefix('api/locations')->name('locations.')->group(function (): void {
    Route::get('/provinces', [AdministrativeUnitController::class, 'provinces'])->name('provinces');
    Route::get('/provinces/{provinceCode}/wards', [AdministrativeUnitController::class, 'wards'])->name('wards');
});
