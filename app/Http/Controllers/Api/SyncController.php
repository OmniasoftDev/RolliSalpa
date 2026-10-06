<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appuntamento;
use App\Models\Compito;
use App\Models\Decisione;
use App\Models\Question;
use App\Services\Sincronizzazione;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class SyncController extends Controller
{
    /** POST /api/sync — corpo: il contenuto di pannello/<progetto>.json. */
    public function sync(Request $request, Sincronizzazione $sync): JsonResponse
    {
        $dati = $request->json()->all();
        if (! $dati) {
            return response()->json(['errore' => 'Corpo JSON vuoto o non valido.'], 422);
        }
        try {
            return response()->json(['ok' => true] + $sync->applica($dati));
        } catch (InvalidArgumentException $e) {
            return response()->json(['errore' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/stato — a ogni controllo orario il PC dice cosa ha fatto:
     * {"controllo": "2026-10-02 14:00", "mail": "2026-10-02 11:20", "rolling": "2026-10-02 14:00", "rollingVersione": "v19"}.
     * Si tiene in cache (nessuna tabella): serve solo alla striscia in cima alle pagine.
     */
    public function stato(Request $request): JsonResponse
    {
        $campi = [];
        foreach (['controllo', 'mail', 'rolling', 'rollingVersione'] as $k) {
            $v = $request->input($k);
            if (is_string($v) && strlen($v) <= 40) {
                $campi[$k] = $v;
            }
        }
        // mail che parlano del progetto ma escluse dal filtro mittenti: {quando, da, oggetto, progetto?}
        $escluse = [];
        foreach (array_slice((array) $request->input('escluse', []), 0, 50) as $m) {
            // un elemento vuoto (PowerShell 5.1 con "[]") non e' una mail: altrimenti il contatore segna 1 con l'elenco vuoto
            if (is_array($m) && (($m['oggetto'] ?? '') !== '' || ($m['da'] ?? '') !== '')) {
                $escluse[] = collect($m)->only(['quando', 'da', 'oggetto', 'progetto'])
                    ->map(fn ($v) => mb_substr((string) $v, 0, 200))->all();
            }
        }
        Cache::forever('stato_pc', $campi + ['escluse' => $escluse, 'ricevuto' => now()->format('Y-m-d H:i')]);

        // eventi del calendario Google Salpa-Rolli, letti dal PC con l'indirizzo iCal (pagina Workflow):
        // {"letto": "2026-10-06 10:05", "eventi": [{progetto, uid, titolo, inizio, fine, luogo, descrizione, tuttoIlGiorno}]}
        $calendario = $request->input('calendario');
        if (is_array($calendario) && is_array($calendario['eventi'] ?? null)) {
            $eventi = [];
            foreach (array_slice($calendario['eventi'], 0, 500) as $g) {
                if (! is_array($g) || ! in_array($g['progetto'] ?? null, ['salpa', 'rolli'], true) || ! is_string($g['inizio'] ?? null)) {
                    continue;
                }
                $eventi[] = collect($g)->only(['progetto', 'uid', 'titolo', 'inizio', 'fine', 'luogo', 'descrizione'])
                    ->map(fn ($v) => mb_substr((string) $v, 0, 1000))->all() + ['tuttoIlGiorno' => (bool) ($g['tuttoIlGiorno'] ?? false)];
            }
            Cache::forever('calendario_google', ['letto' => mb_substr((string) ($calendario['letto'] ?? now()->format('Y-m-d H:i')), 0, 20), 'eventi' => $eventi]);
        }

        return response()->json(['ok' => true]);
    }

    /** GET /api/spunte — domande spuntate o tolte dal browser, da riportare nei file sul PC. */
    public function spunte(): JsonResponse
    {
        $spunte = Question::with('machine.project')
            ->where('fatto_web', true)
            ->get()
            ->map(fn (Question $q) => [
                'progetto' => $q->machine->project->slug,
                'macchina' => $q->machine->codice,
                'id' => $q->chiave,
                'chi' => $q->chi,
                'cosa' => $q->cosa,
                'fatto' => $q->fatto,
                'fattoIl' => $q->fatto_il?->format('Y-m-d'),
                'aggiornata' => $q->updated_at?->format('Y-m-d H:i'),
            ]);

        $decisioni = Decisione::with('project')->where('fatta_web', true)->get()
            ->map(fn (Decisione $d) => [
                'progetto' => $d->project->slug,
                'id' => $d->codice,
                'testo' => $d->testo,
                'fatta' => $d->fatta,
                'fattaIl' => $d->fatta_il?->format('Y-m-d'),
            ]);

        // Tutti i compiti tranne quelli ancora proposti: li gestisce Francesco dal web, il PC li riporta nei .md
        $compiti = Compito::with('project')->where('stato', '!=', 'proposto')->orderBy('id')->get()
            ->map(fn (Compito $c) => [
                'progetto' => $c->project->slug,
                'id' => $c->codice ?: 'w'.$c->id,
                'persona' => $c->persona,
                'testo' => $c->testo,
                'macchine' => $c->macchine ?? [],
                'fonte' => $c->fonte,
                'scadenza' => $c->scadenza?->format('Y-m-d'),
                'stato' => $c->stato,
                'assegnatoIl' => $c->assegnato_il?->format('Y-m-d'),
                'chiusoIl' => $c->chiuso_il?->format('Y-m-d'),
                'esito' => $c->esito,
                'aggiornato' => $c->updated_at?->format('Y-m-d H:i'),
            ]);

        // Appuntamenti accettati o rifiutati da Francesco: il PC mette gli accettati nel calendario Google Salpa-Rolli
        $appuntamenti = Appuntamento::with('project')->where('stato', '!=', 'proposto')->orderBy('inizio')->get()
            ->map(fn (Appuntamento $a) => [
                'progetto' => $a->project->slug,
                'id' => $a->codice,
                'origine' => $a->origine,
                'titolo' => $a->titolo,
                'dettaglio' => $a->dettaglio,
                'luogo' => $a->luogo,
                'fonte' => $a->fonte,
                'macchine' => $a->macchine ?? [],
                'inizio' => $a->inizio->toIso8601String(),
                'fine' => $a->fine->toIso8601String(),
                'stato' => $a->stato,
                'decisoIl' => $a->deciso_il?->format('Y-m-d H:i'),
                'aggiornato' => $a->updated_at?->format('Y-m-d H:i'),
            ]);

        return response()->json(['spunte' => $spunte, 'decisioni' => $decisioni, 'compiti' => $compiti, 'appuntamenti' => $appuntamenti]);
    }
}
