<?php

namespace App\Http\Controllers;

use App\Models\Credenziale;
use App\Models\Project;
use App\Services\MappaProgetto;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Progetti 4.0" (Francesco, 13/10/2026): ogni macchina come progetto separato, con chi fa
 * cosa, dati in ingresso e in uscita, bidirezionalita', requisiti R1–R13 e la mappa
 * concettuale delle connessioni. I dati sono il campo `progetto` della macchina nel file del PC.
 */
class ProgettiController extends Controller
{
    /** GET /{slug}/progetti — una scheda per macchina con avanzamento e copertura dei requisiti. */
    public function index(string $slug, MappaProgetto $mappa): View
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();
        $requisiti = $mappa->requisiti($progetto->info ?? []);

        // le voci escluse dai conteggi (es. r00 "Domande generali" di Rolli) non sono macchine
        $schede = $progetto->machines->reject(fn ($m) => $m->dato('escludiDaiConteggi'))->values()->map(function ($m) use ($mappa, $requisiti) {
            $p = $mappa->progetto($m);

            return ['macchina' => $m, 'p' => $p, 'avanzamento' => $mappa->avanzamento($p), 'copertura' => $mappa->copertura($p, $requisiti)];
        });

        return view('progetti.index', [
            'progetto' => $progetto,
            'progetti' => Project::orderBy('ordine')->get(['slug', 'nome']),
            'schede' => $schede,
            'requisiti' => $requisiti,
        ]);
    }

    /** GET /{slug}/progetti/{codice} — il progetto 4.0 di una macchina. */
    public function show(Request $request, string $slug, string $codice, MappaProgetto $mappa): View
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();
        $macchina = $progetto->machines()->with('questions')->where('codice', $codice)->firstOrFail();
        $p = $mappa->progetto($macchina);
        $requisiti = $mappa->requisiti($progetto->info ?? []);
        $vista = $mappa->vista($p, $request->query('vista'));

        return view('progetti.show', [
            'progetto' => $progetto,
            'progetti' => Project::orderBy('ordine')->get(['slug', 'nome']),
            'macchina' => $macchina,
            'macchine' => $progetto->machines->reject(fn ($m) => $m->dato('escludiDaiConteggi'))->values(),
            'p' => $p,
            'mappa' => $mappa->mappa($mappa->grafo($p, $vista)),
            'vista' => $vista,
            'requisiti' => $requisiti,
            'avanzamento' => $mappa->avanzamento($p),
            'copertura' => $mappa->copertura($p, $requisiti),
            'persone' => $progetto->persone()->get()->keyBy('codice'),
            'compiti' => $progetto->compiti()->whereIn('stato', ['proposto', 'aperto'])->get()
                ->filter(fn ($c) => in_array($macchina->codice, $c->macchine ?? [], true)),
            'credenziali' => Credenziale::perOggetto($progetto, 'macchina', $macchina->codice),
            'storia' => $progetto->events()->limit(300)->get()
                ->filter(fn ($e) => in_array($macchina->codice, $e->macchine ?? [], true))->take(12),
        ]);
    }

    /** GET /{slug}/progetti/{codice}/mappa — la mappa della macchina a schermo intero (nuova finestra). */
    public function mappa(Request $request, string $slug, string $codice, MappaProgetto $mappa): View
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();
        $macchina = $progetto->machines()->where('codice', $codice)->firstOrFail();
        $p = $mappa->progetto($macchina);
        $vista = $mappa->vista($p, $request->query('vista'));

        return view('progetti.schermo', [
            'progetto' => $progetto,
            'titolo' => trim($macchina->dato('num', '').' '.$macchina->dato('nome')),
            'p' => $p,
            'vista' => $vista,
            'mappa' => $mappa->mappa($mappa->grafo($p, $vista)),
            'codice' => $macchina->codice,
            'impianto' => false,
            'base' => route('progetti.mappa', [$slug, $codice]),
            'indietro' => route('progetti.show', [$slug, $codice]),
        ]);
    }
}
