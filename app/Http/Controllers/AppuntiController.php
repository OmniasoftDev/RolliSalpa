<?php

namespace App\Http\Controllers;

use App\Models\AllegatoAppunto;
use App\Models\Appunto;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppuntiController extends Controller
{
    /** POST /{slug}/appunti — nuovo appunto dal sito, con foto/PDF. */
    public function store(Request $request, string $slug): JsonResponse
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();
        $codici = $progetto->machines()->pluck('codice')->all();

        $dati = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(Appunto::TIPI))],
            'testo' => ['required', 'string', 'max:20000'],
            'macchine' => ['array'],
            'macchine.*' => [Rule::in($codici)],
            'allegati' => ['array', 'max:12'],
            'allegati.*' => ['file', 'max:15360', 'mimes:jpg,jpeg,png,webp,heic,pdf'],
        ], [
            'testo.required' => 'Scrivi il testo dell\'appunto.',
            'allegati.*.max' => 'Ogni allegato puo\' pesare al massimo 15 MB.',
            'allegati.*.mimes' => 'Si possono allegare solo foto (jpg, png, webp, heic) e PDF.',
        ]);

        $appunto = DB::transaction(function () use ($request, $progetto, $dati) {
            $appunto = Appunto::create([
                'project_id' => $progetto->id,
                'user_id' => $request->user()->id,
                'tipo' => $dati['tipo'],
                'macchine' => array_values($dati['macchine'] ?? []),
                'testo' => $dati['testo'],
            ]);
            foreach ($request->file('allegati', []) as $file) {
                $percorso = $file->store('appunti/'.now()->format('Y/m'), 'local');
                $appunto->allegati()->create([
                    'nome' => mb_substr($file->getClientOriginalName() ?: basename($percorso), 0, 200),
                    'percorso' => $percorso,
                    'mime' => $file->getMimeType() ?: 'application/octet-stream',
                    'dimensione' => $file->getSize(),
                ]);
            }

            return $appunto;
        });

        return response()->json(['ok' => true, 'id' => $appunto->id]);
    }

    /** DELETE /appunti/{appunto} — solo se non ancora elaborato (errori di battitura, foto sbagliate). */
    public function destroy(Appunto $appunto): JsonResponse
    {
        if ($appunto->elaborato_at) {
            return response()->json(['errore' => 'Appunto gia\' elaborato: non si puo\' piu\' cancellare dal sito.'], 422);
        }
        foreach ($appunto->allegati as $a) {
            Storage::disk('local')->delete($a->percorso);
        }
        $appunto->delete();

        return response()->json(['ok' => true]);
    }

    /** GET /allegati/{allegato} — mostra la foto o il PDF (solo utenti collegati). */
    public function file(AllegatoAppunto $allegato): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($allegato->percorso), 404);

        return Storage::disk('local')->response($allegato->percorso, $allegato->nome, [
            'Content-Type' => $allegato->mime,
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }
}
