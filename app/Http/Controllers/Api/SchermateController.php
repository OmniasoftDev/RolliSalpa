<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Scada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Schermate dello SCADA (e altre immagini) caricate dal PC per macchina o sistema:
 * storage disco local `schermate/{slug}/{codice}/{file}`. Nessuna tabella: i titoli stanno nel file del PC.
 */
class SchermateController extends Controller
{
    /** POST /api/schermate (multipart: progetto, codice, file) — idempotente: lo stesso nome sovrascrive. */
    public function store(Request $request): JsonResponse
    {
        $dati = $request->validate([
            'progetto' => ['required', 'string', 'regex:/^[a-z0-9-]+$/'],
            'codice' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,20}$/'],
            'file' => ['required', 'file', 'max:8192', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $progetto = Project::where('slug', $dati['progetto'])->first();
        if (! $progetto || ! $this->codiceValido($progetto, $dati['codice'])) {
            return response()->json(['errore' => 'Progetto o codice inesistente.'], 422);
        }

        $nome = Scada::nomeFile($request->file('file')->getClientOriginalName());
        if ($nome === null) {
            return response()->json(['errore' => 'Nome file non valido (jpg, jpeg, png, webp).'], 422);
        }

        $percorso = Storage::disk('local')->putFileAs(Scada::cartella($progetto->slug, $dati['codice']), $request->file('file'), $nome);

        return response()->json(['ok' => true, 'percorso' => $percorso]);
    }

    /** GET /api/schermate?progetto=slug — i file presenti per codice. */
    public function index(Request $request, Scada $scada): JsonResponse
    {
        $progetto = Project::where('slug', (string) $request->query('progetto'))->first();
        if (! $progetto) {
            return response()->json(['errore' => 'Progetto inesistente.'], 422);
        }

        $codici = $progetto->machines->pluck('codice')->merge(collect($progetto->sistemi())->pluck('codice'))->unique();
        $elenco = [];
        foreach ($codici as $c) {
            $file = $scada->presenti($progetto->slug, $c);
            if ($file) {
                $elenco[$c] = $file;
            }
        }

        return response()->json(['progetto' => $progetto->slug, 'schermate' => (object) $elenco]);
    }

    private function codiceValido(Project $progetto, string $codice): bool
    {
        return $progetto->machines()->where('codice', $codice)->exists()
            || collect($progetto->sistemi())->contains('codice', $codice);
    }
}
