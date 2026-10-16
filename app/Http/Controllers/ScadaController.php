<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Scada;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "SCADA" (Francesco, 16/10/2026): per ogni macchina del progetto cosa si vede nello SCADA Edica
 * (campo `scada` del file del PC) e le schermate caricate dal PC; in cima le schermate del sistema SCADA.
 */
class ScadaController extends Controller
{
    /** GET /{slug}/scada */
    public function show(string $slug, Scada $scada): View
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();
        $righe = $progetto->machines->reject(fn ($m) => $m->dato('escludiDaiConteggi'))->values()
            ->map(fn ($m) => ['macchina' => $m, 's' => $scada->scheda($progetto, $m)]);

        $sistemi = collect($progetto->sistemi())->where('tipo', 'scada')->values()
            ->map(fn ($s) => $s + ['galleria' => $scada->schermate($progetto, $s['codice'], $s['schermate'])]);

        return view('scada.show', [
            'progetto' => $progetto,
            'progetti' => Project::orderBy('ordine')->get(['slug', 'nome']),
            'righe' => $righe,
            'sistemi' => $sistemi,
            'conteggi' => collect(Scada::STATI)->map(fn ($x, $k) => $righe->filter(fn ($r) => ($r['s']['stato'] ?? 'assente') === $k)->count()),
        ]);
    }

    /** GET /{slug}/schermate/{codice}/{file} — la schermata (solo utenti autenticati). */
    public function file(string $slug, string $codice, string $file): StreamedResponse
    {
        $nome = Scada::nomeFile($file);
        abort_unless($nome !== null && $nome === $file, 404);
        $percorso = Scada::cartella($slug, $codice).'/'.$nome;
        abort_unless(Storage::disk('local')->exists($percorso), 404);

        return Storage::disk('local')->response($percorso, $nome, ['Cache-Control' => 'private, max-age=86400']);
    }
}
