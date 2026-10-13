<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\MappaProgetto;
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

        $schede = $progetto->machines->map(function ($m) use ($mappa, $requisiti) {
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
    public function show(string $slug, string $codice, MappaProgetto $mappa): View
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();
        $macchina = $progetto->machines()->with('questions')->where('codice', $codice)->firstOrFail();
        $p = $mappa->progetto($macchina);
        $requisiti = $mappa->requisiti($progetto->info ?? []);

        return view('progetti.show', [
            'progetto' => $progetto,
            'progetti' => Project::orderBy('ordine')->get(['slug', 'nome']),
            'macchina' => $macchina,
            'macchine' => $progetto->machines,
            'p' => $p,
            'mappa' => $mappa->mappa($p),
            'requisiti' => $requisiti,
            'avanzamento' => $mappa->avanzamento($p),
            'copertura' => $mappa->copertura($p, $requisiti),
            'persone' => $progetto->persone()->get()->keyBy('codice'),
            'compiti' => $progetto->compiti()->whereIn('stato', ['proposto', 'aperto'])->get()
                ->filter(fn ($c) => in_array($macchina->codice, $c->macchine ?? [], true)),
            'storia' => $progetto->events()->limit(300)->get()
                ->filter(fn ($e) => in_array($macchina->codice, $e->macchine ?? [], true))->take(12),
        ]);
    }
}
