<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Certificazione;
use App\Services\MappaProgetto;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * "Certificazione" (Francesco, 16/10/2026): l'avanzamento di ogni macchina verso la
 * certificazione 4.0, a tappe con date, con cosa manca e il prossimo passo.
 */
class CertificazioneController extends Controller
{
    /** GET /{slug}/certificazione[?filtro=rischio|senzadati] */
    public function show(Request $request, string $slug, Certificazione $cert, MappaProgetto $mappa): View
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();
        $requisiti = $mappa->requisiti($progetto->info ?? []);

        $righe = $progetto->machines->reject(fn ($m) => $m->dato('escludiDaiConteggi'))->values()->map(function ($m) use ($cert, $mappa) {
            $scheda = $cert->scheda($m);

            return ['macchina' => $m, 'scheda' => $scheda, 'p' => $mappa->progetto($m),
                'rischio' => $cert->aRischio($scheda), 'senzaDati' => $cert->senzaDatiVivi($scheda)];
        });

        $filtro = in_array($request->query('filtro'), ['rischio', 'senzadati'], true) ? $request->query('filtro') : '';
        $visibili = match ($filtro) {
            'rischio' => $righe->where('rischio', true)->values(),
            'senzadati' => $righe->where('senzaDati', true)->values(),
            default => $righe,
        };

        $scadenza = $progetto->info['scadenza'] ?? null;
        $giorni = null;
        if (is_string($scadenza) && $scadenza !== '') {
            try {
                $giorni = (int) Carbon::today()->diffInDays(Carbon::parse($scadenza)->startOfDay(), false);
            } catch (\Throwable) {
                $giorni = null;
            }
        }

        return view('certificazione.show', [
            'progetto' => $progetto,
            'progetti' => Project::orderBy('ordine')->get(['slug', 'nome']),
            'righe' => $visibili,
            'tutte' => $righe,
            'riepilogo' => $cert->riepilogo($righe),
            'requisiti' => $requisiti,
            'filtro' => $filtro,
            'giorni' => $giorni,
            'scadenza' => $scadenza,
        ]);
    }
}
