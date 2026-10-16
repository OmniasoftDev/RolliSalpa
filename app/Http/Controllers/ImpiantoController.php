<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\MappaProgetto;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Mappa impianto" (Francesco, 16/10/2026): tutte le macchine del progetto in un'unica mappa a
 * blocchi e frecce, con i sistemi condivisi (SCADA, storico, Data Collector, gestionale, rete:
 * nodi con id "g-…") una volta sola in alto. Vista Oggi (visto sullo SCADA) o Obiettivo 4.0.
 */
class ImpiantoController extends Controller
{
    private function dati(Request $request, string $slug, MappaProgetto $mappa): array
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();
        $vista = $request->query('vista') === 'obiettivo' ? 'obiettivo' : 'oggi';
        $macchine = $progetto->machines->reject(fn ($m) => $m->dato('escludiDaiConteggi'))->values()
            ->map(fn ($m) => ['codice' => $m->codice, 'num' => (string) $m->dato('num', $m->codice), 'nome' => (string) $m->dato('nome', ''), 'progetto' => $mappa->progetto($m)])
            ->all();

        return [
            'progetto' => $progetto,
            'vista' => $vista,
            'mappa' => $mappa->impianto($macchine, $vista),
            'macchine' => $macchine,
            // data della visita piu' vecchia tra le viste Oggi (per l'eta' dei dati)
            'dataOggi' => collect($macchine)->map(fn ($m) => $m['progetto']['oggi']['aggiornato'] ?? null)->filter()->sort()->first(),
            'senzaVista' => collect($macchine)->filter(fn ($m) => $mappa->grafo($m['progetto'], $vista, true)['nodi'] === [])->values(),
        ];
    }

    /** GET /{slug}/impianto */
    public function show(Request $request, string $slug, MappaProgetto $mappa): View
    {
        return view('impianto.show', $this->dati($request, $slug, $mappa) + [
            'progetti' => Project::orderBy('ordine')->get(['slug', 'nome']),
        ]);
    }

    /** GET /{slug}/impianto/mappa — la stessa mappa a schermo intero (nuova finestra). */
    public function schermo(Request $request, string $slug, MappaProgetto $mappa): View
    {
        $d = $this->dati($request, $slug, $mappa);

        return view('progetti.schermo', $d + [
            'titolo' => 'Mappa impianto · '.$d['progetto']->nome,
            'p' => null,
            'codice' => '',
            'impianto' => true,
            'base' => route('impianto.mappa', $slug),
            'indietro' => route('impianto', $slug),
        ]);
    }
}
