<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Services\Sincronizzazione;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class SyncController extends Controller
{
    /** POST /api/sync — corpo: il contenuto di pannello/<progetto>.json. */
    public function sync(Request $request, Sincronizzazione $sync): JsonResponse
    {
        $dati = $request->json()->all();
        if (! $dati) {
            return response()->json(['errore' => 'Corpo JSON vuoto o non valido.'], 422);
        }
        try {
            return response()->json(['ok' => true] + $sync->applica($dati));
        } catch (InvalidArgumentException $e) {
            return response()->json(['errore' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/stato — a ogni controllo orario il PC dice cosa ha fatto:
     * {"controllo": "2026-10-02 14:00", "mail": "2026-10-02 11:20", "rolling": "2026-10-02 14:00", "rollingVersione": "v19"}.
     * Si tiene in cache (nessuna tabella): serve solo alla striscia in cima alle pagine.
     */
    public function stato(Request $request): JsonResponse
    {
        $campi = [];
        foreach (['controllo', 'mail', 'rolling', 'rollingVersione'] as $k) {
            $v = $request->input($k);
            if (is_string($v) && strlen($v) <= 40) {
                $campi[$k] = $v;
            }
        }
        Cache::forever('stato_pc', $campi + ['ricevuto' => now()->format('Y-m-d H:i')]);

        return response()->json(['ok' => true]);
    }

    /** GET /api/spunte — domande spuntate o tolte dal browser, da riportare nei file sul PC. */
    public function spunte(): JsonResponse
    {
        $spunte = Question::with('machine.project')
            ->where('fatto_web', true)
            ->get()
            ->map(fn (Question $q) => [
                'progetto' => $q->machine->project->slug,
                'macchina' => $q->machine->codice,
                'id' => $q->chiave,
                'chi' => $q->chi,
                'cosa' => $q->cosa,
                'fatto' => $q->fatto,
                'fattoIl' => $q->fatto_il?->format('Y-m-d'),
                'aggiornata' => $q->updated_at?->format('Y-m-d H:i'),
            ]);

        return response()->json(['spunte' => $spunte]);
    }
}
