<?php

use App\Http\Controllers\Api\SyncController;
use App\Http\Middleware\TokenSincronizzazione;
use Illuminate\Support\Facades\Route;

// Chiamate dello script orario sul PC di Francesco (automazione/pubblica-pannello.ps1).
Route::middleware([TokenSincronizzazione::class, 'throttle:30,1'])->group(function () {
    Route::post('/sync', [SyncController::class, 'sync']);
    Route::get('/spunte', [SyncController::class, 'spunte']);
});
