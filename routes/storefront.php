<?php

use Illuminate\Support\Facades\Route;

/*
 * Public catalogue pages (owner: Codex, PR-X4).
 * The navbar links to route('shoes.index', ['gender' => 'men|women|kids|unisex']); keep that name and query key.
 * Until PR-X4 replaces it, /shoes shows a "coming soon" page.
 */
Route::view('/shoes', 'storefront.coming-soon')->name('shoes.index');
