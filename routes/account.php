<?php

use App\Http\Controllers\Account\AdministrativeUnitController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

/*
 * Sign-in, registration, sign-out, address book and the province/ward lookup API
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

Route::middleware('throttle:120,1')->prefix('api/locations')->name('locations.')->group(function (): void {
    Route::get('/provinces', [AdministrativeUnitController::class, 'provinces'])->name('provinces');
    Route::get('/provinces/{provinceCode}/wards', [AdministrativeUnitController::class, 'wards'])->name('wards');
});
