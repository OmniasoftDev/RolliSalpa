<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\MappaProgetto;
use App\Services\Scada;
use Illuminate\View\View;

/**
 * "Requisiti" (Francesco, 16/10/2026): matrice macchine × R1–R13 con lo stato di ogni requisito
 * (campo `progetto.requisiti` della macchina), il testo "come" e la prova (`evidenza`: schermata o testo).
 * Serve a preparare la presentazione all'Ing. Romano e a vedere i requisiti piu' scoperti.
 */
class RequisitiController extends Controller
{
    /** Stati che non contano nel totale dei requisiti applicabili. */
    private const NON_APPLICABILI = ['na', 'escluso'];

    /** GET /{slug}/requisiti */
    public function show(string $slug, MappaProgetto $mappa): View
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();
        $requisiti = $mappa->requisiti($progetto->info ?? []);

        $righe = $progetto->machines->reject(fn ($m) => $m->dato('escludiDaiConteggi'))->values()->map(function ($m) use ($mappa, $requisiti, $progetto) {
            $p = $mappa->progetto($m);
            $celle = [];
            foreach (array_keys($requisiti) as $r) {
                $voce = is_array($p['requisiti'][$r] ?? null) ? $p['requisiti'][$r] : [];
                $celle[$r] = [
                    'stato' => MappaProgetto::stato($voce['stato'] ?? null),
                    'come' => is_scalar($voce['come'] ?? null) ? (string) $voce['come'] : '',
                    'evidenza' => $this->evidenza($voce['evidenza'] ?? null, $progetto->slug, $m->codice),
                ];
            }
            $applicabili = collect($celle)->reject(fn ($c) => in_array($c['stato'], self::NON_APPLICABILI, true));
            $ok = $applicabili->where('stato', 'ok')->count();

            return [
                'macchina' => $m,
                'celle' => $celle,
                'ok' => $ok,
                'applicabili' => $applicabili->count(),
                'perc' => $applicabili->count() ? (int) round(100 * $ok / $applicabili->count()) : null,
            ];
        });

        $colonne = [];
        foreach (array_keys($requisiti) as $r) {
            $app = $righe->reject(fn ($x) => in_array($x['celle'][$r]['stato'], self::NON_APPLICABILI, true));
            $colonne[$r] = ['ok' => $app->filter(fn ($x) => $x['celle'][$r]['stato'] === 'ok')->count(), 'applicabili' => $app->count()];
        }

        // I requisiti piu' scoperti: quelli con il minor numero di macchine ok (tra gli applicabili)
        $valide = collect($colonne)->filter(fn ($c) => $c['applicabili'] > 0);
        $minimo = $valide->min('ok');
        $scoperti = $minimo === null ? [] : $valide->filter(fn ($c) => $c['ok'] === $minimo)->keys()->all();

        return view('requisiti.show', [
            'progetto' => $progetto,
            'progetti' => Project::orderBy('ordine')->get(['slug', 'nome']),
            'requisiti' => $requisiti,
            'righe' => $righe,
            'colonne' => $colonne,
            'scoperti' => $scoperti,
            'minimo' => $minimo,
        ]);
    }

    /** La prova di un requisito: {"file": "x.jpg"} (schermata caricata dal PC) o {"testo": "..."}; null se manca. */
    private function evidenza(mixed $e, string $slug, string $codice): ?array
    {
        if (is_string($e) && $e !== '') {
            $e = Scada::nomeFile($e) !== null ? ['file' => $e] : ['testo' => $e];
        }
        if (! is_array($e)) {
            return null;
        }
        $file = is_string($e['file'] ?? null) ? Scada::nomeFile($e['file']) : null;
        $testo = is_scalar($e['testo'] ?? null) ? (string) $e['testo'] : '';
        if ($file === null && $testo === '') {
            return null;
        }

        return ['url' => $file ? route('schermate.file', [$slug, $codice, $file]) : null, 'file' => $file, 'testo' => $testo];
    }
}
