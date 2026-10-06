<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AllegatoAppunto;
use App\Models\Appunto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** API per lo script sul PC: scarica gli appunti da elaborare e li segna come elaborati. */
class AppuntiController extends Controller
{
    /** GET /api/appunti — appunti non ancora elaborati, con l'elenco degli allegati. */
    public function index(): JsonResponse
    {
        $appunti = Appunto::with(['project', 'allegati', 'user'])
            ->whereNull('elaborato_at')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Appunto $a) => [
                'id' => $a->id,
                'progetto' => $a->project->slug,
                'tipo' => $a->tipo,
                'macchine' => $a->macchine ?? [],
                'testo' => $a->testo,
                'autore' => $a->user?->name,
                'creato' => $a->created_at->format('Y-m-d H:i'),
                'allegati' => $a->allegati->map(fn (AllegatoAppunto $f) => [
                    'id' => $f->id, 'nome' => $f->nome, 'mime' => $f->mime, 'dimensione' => $f->dimensione,
                ])->values(),
            ]);

        return response()->json(['appunti' => $appunti]);
    }

    /** GET /api/allegati/{allegato} — il file, per salvarlo sul PC. */
    public function file(AllegatoAppunto $allegato): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($allegato->percorso), 404);

        return Storage::disk('local')->download($allegato->percorso, $allegato->nome, ['Content-Type' => $allegato->mime]);
    }

    /** POST /api/appunti/elaborati — {"id": [1, 2]}: Claude li ha registrati nei file del progetto. */
    public function elaborati(Request $request): JsonResponse
    {
        $id = array_filter((array) $request->input('id', []), 'is_numeric');
        $n = Appunto::whereIn('id', $id)->whereNull('elaborato_at')->update(['elaborato_at' => now()]);

        return response()->json(['ok' => true, 'elaborati' => $n]);
    }

    /** DELETE /api/appunti/{id} — dal PC, anche se elaborato: per gli appunti di prova (il sito blocca gli elaborati). */
    public function cancella(int $id): JsonResponse
    {
        // id e non binding del modello: il token si controlla prima di cercare l'appunto (401, non 404)
        $appunto = Appunto::findOrFail($id);
        foreach ($appunto->allegati as $a) {
            Storage::disk('local')->delete($a->percorso);
        }
        $appunto->delete();

        return response()->json(['ok' => true]);
    }
}
