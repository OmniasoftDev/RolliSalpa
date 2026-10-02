<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PannelloController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', [PannelloController::class, 'index'])->name('home');
    Route::post('/domande/{question}', [PannelloController::class, 'segna'])->name('domande.segna');
    // Ultima: /salpa, /rolli ...
    Route::get('/{slug}', [PannelloController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('pannello');
});
