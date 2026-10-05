<?php

use App\Http\Controllers\AppuntiController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CompitiController;
use App\Http\Controllers\LavoroController;
use App\Http\Controllers\PannelloController;
use App\Http\Controllers\RegistroController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', [PannelloController::class, 'index'])->name('home');
    Route::post('/domande/{question}', [PannelloController::class, 'segna'])->name('domande.segna');
    Route::post('/decisioni/{decisione}', [PannelloController::class, 'decisione'])->name('decisioni.segna');
    Route::post('/eventi/{event}/visto', [PannelloController::class, 'visto'])->name('eventi.visto');
    Route::post('/{slug}/eventi/visti', [PannelloController::class, 'tuttiVisti'])->where('slug', '[a-z0-9-]+')->name('eventi.visti');
    Route::post('/{slug}/appunti', [AppuntiController::class, 'store'])->where('slug', '[a-z0-9-]+')->name('appunti.store');
    Route::get('/{slug}/chat', [ChatController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('chat');
    Route::post('/{slug}/chat', [ChatController::class, 'invia'])->where('slug', '[a-z0-9-]+')->middleware('throttle:20,1')->name('chat.invia');
    Route::get('/{slug}/compiti', [CompitiController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('compiti');
    Route::post('/{slug}/compiti', [CompitiController::class, 'store'])->where('slug', '[a-z0-9-]+')->name('compiti.store');
    Route::post('/compiti/{compito}', [CompitiController::class, 'aggiorna'])->name('compiti.aggiorna');
    Route::get('/{slug}/lavoro', [LavoroController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('lavoro');
    Route::post('/{slug}/lavoro/bozza', [LavoroController::class, 'bozza'])->where('slug', '[a-z0-9-]+')->middleware('throttle:20,1')->name('lavoro.bozza');
    Route::post('/{slug}/lavoro/appuntamento', [LavoroController::class, 'accetta'])->where('slug', '[a-z0-9-]+')->name('lavoro.appuntamento');
    Route::post('/appuntamenti/{appuntamento}', [LavoroController::class, 'appuntamento'])->name('appuntamenti.segna');
    Route::delete('/appunti/{appunto}', [AppuntiController::class, 'destroy'])->name('appunti.destroy');
    Route::get('/allegati/{allegato}', [AppuntiController::class, 'file'])->name('allegati.file');
    Route::get('/registro', [RegistroController::class, 'show'])->name('registro');
    Route::post('/mail/viste', [RegistroController::class, 'tutteViste'])->name('mail.viste');
    Route::post('/mail/{mail}/vista', [RegistroController::class, 'vista'])->name('mail.vista');
    // Ultima: /salpa, /rolli ...
    Route::get('/{slug}', [PannelloController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('pannello');
});
