<?php

use App\Http\Controllers\Api\AppuntiController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Middleware\TokenSincronizzazione;
use Illuminate\Support\Facades\Route;

// Chiamate dello script orario sul PC di Francesco (automazione/pubblica-pannello.ps1).
// 120 al minuto: a ogni controllo si scaricano anche le foto degli appunti.
Route::middleware([TokenSincronizzazione::class, 'throttle:120,1'])->group(function () {
    Route::post('/sync', [SyncController::class, 'sync']);
    Route::get('/spunte', [SyncController::class, 'spunte']);
    Route::get('/appunti', [AppuntiController::class, 'index']);
    Route::post('/appunti/elaborati', [AppuntiController::class, 'elaborati']);
    Route::get('/allegati/{allegato}', [AppuntiController::class, 'file']);
});
