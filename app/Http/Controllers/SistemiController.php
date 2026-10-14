<?php

namespace App\Http\Controllers;

use App\Models\Appunto;
use App\Models\Credenziale;
use App\Models\Project;
use Illuminate\View\View;

/**
 * "Sistemi" (Francesco, 14/10/2026): SCADA, Data Collector, gestionale e rete che stanno in mezzo
 * tra le macchine e il gestionale. I dati sono il campo `sistemi` del file del PC, salvato in
 * projects.info.sistemi (nessuna tabella nuova); le foto caricate dal sito sono gli allegati
 * degli appunti che citano il codice del sistema (es. "s1") tra le macchine.
 */
class SistemiController extends Controller
{
    public const TIPI = ['scada' => 'SCADA', 'datacollector' => 'Data Collector', 'gestionale' => 'Gestionale', 'rete' => 'Rete'];

    public const PROPRIETA = ['terzi' => 'di terzi', 'omniasoft' => 'Omniasoft', 'da decidere' => 'da decidere'];

    /** GET /{slug}/sistemi — una scheda per sistema. */
    public function index(string $slug): View
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();

        return view('sistemi.index', [
            'progetto' => $progetto,
            'progetti' => Project::orderBy('ordine')->get(['slug', 'nome']),
            'sistemi' => $progetto->sistemi(),
        ]);
    }

    /** GET /{slug}/sistemi/{codice} — il sistema, con collegamenti alle macchine, foto e credenziali. */
    public function show(string $slug, string $codice): View
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();
        $sistema = collect($progetto->sistemi())->firstWhere('codice', $codice);
        abort_unless($sistema, 404);

        // foto e screenshot caricati dal sito: allegati immagine degli appunti che citano il sistema, piu' recenti prima
        $foto = Appunto::with('allegati')->where('project_id', $progetto->id)->latest()->limit(500)->get()
            ->filter(fn (Appunto $a) => in_array($codice, $a->macchine ?? [], true))
            ->flatMap(fn (Appunto $a) => $a->allegati->filter(fn ($f) => $f->immagine())->map(fn ($f) => ['file' => $f, 'appunto' => $a]))
            ->values();

        return view('sistemi.show', [
            'progetto' => $progetto,
            'progetti' => Project::orderBy('ordine')->get(['slug', 'nome']),
            'sistema' => $sistema,
            'sistemi' => $progetto->sistemi(),
            'macchine' => $progetto->machines,
            'perCodice' => $progetto->machines->keyBy('codice'),
            'foto' => $foto,
            'credenziali' => Credenziale::perOggetto($progetto, 'sistema', $codice),
        ]);
    }
}
