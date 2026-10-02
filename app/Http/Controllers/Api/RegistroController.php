<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RegistroController extends Controller
{
    /**
     * POST /api/controlli — a fine controllo orario (automazione/registra-controllo.ps1):
     * {"controlli": [...], "mail": [...], "finestra": {"dal": "...", "codici": [...], "firma": "..."}}.
     * Risponde con l'impronta calcolata dal server e "quadra": true se le mail coincidono.
     */
    public function registra(Request $request, Registro $registro): JsonResponse
    {
        $dati = $request->json()->all();
        if (! $dati) {
            return response()->json(['errore' => 'Corpo JSON vuoto o non valido.'], 422);
        }
        try {
            return response()->json(['ok' => true] + $registro->registra($dati));
        } catch (InvalidArgumentException $e) {
            return response()->json(['errore' => $e->getMessage()], 422);
        }
    }
}
