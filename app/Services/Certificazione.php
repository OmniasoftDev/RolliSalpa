<?php

namespace App\Services;

use App\Models\Machine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * "Certificazione" (Francesco, 16/10/2026): per ogni macchina il cammino lineare verso la
 * certificazione 4.0, a tappe con date, e cosa manca. Dati: campo `certificazione` della
 * macchina nel file del PC (salvato in `machines.dati` come `progetto`).
 */
class Certificazione
{
    /** Peso di ogni stato nella percentuale; null = escluso dal totale. */
    private const PESI = ['ok' => 1.0, 'corso' => 0.5, 'verificare' => 0.5, 'attesa' => 0.0, 'rischio' => 0.0, 'no' => 0.0, 'na' => null, 'escluso' => null];

    /** Il campo `certificazione` normalizzato, o null se la macchina non ce l'ha. */
    public function scheda(Machine $macchina): ?array
    {
        $c = $macchina->dato('certificazione');
        if (! is_array($c) || $c === []) {
            return null;
        }

        $tappe = [];
        foreach ((array) ($c['tappe'] ?? []) as $i => $t) {
            if (! is_array($t)) {
                continue;
            }
            $tappe[] = [
                'id' => (string) ($t['id'] ?? 't'.($i + 1)),
                'titolo' => (string) ($t['titolo'] ?? ''),
                'stato' => MappaProgetto::stato($t['stato'] ?? null),
                'data' => self::data($t['data'] ?? null),
                'previsto' => self::data($t['previsto'] ?? null),
                'nota' => (string) ($t['nota'] ?? ''),
                'fonte' => (string) ($t['fonte'] ?? ''),
            ];
        }

        $manca = [];
        foreach ((array) ($c['manca'] ?? []) as $m) {
            if (is_string($m)) {
                $m = ['testo' => $m];
            }
            if (! is_array($m) || trim((string) ($m['testo'] ?? '')) === '') {
                continue;
            }
            $entro = self::data($m['entro'] ?? null);
            $manca[] = [
                'testo' => (string) $m['testo'],
                'chi' => (string) ($m['chi'] ?? ''),
                'entro' => $entro,
                'scaduto' => $entro !== null && $entro->lt(Carbon::today()),
                'tappa' => (string) ($m['tappa'] ?? ''),
            ];
        }

        $prossimo = null;
        if (is_array($c['prossimo'] ?? null) && trim((string) ($c['prossimo']['testo'] ?? '')) !== '') {
            $entro = self::data($c['prossimo']['entro'] ?? null);
            $prossimo = ['testo' => (string) $c['prossimo']['testo'], 'chi' => (string) ($c['prossimo']['chi'] ?? ''), 'entro' => $entro,
                'scaduto' => $entro !== null && $entro->lt(Carbon::today())];
        } elseif (is_string($c['prossimo'] ?? null) && trim($c['prossimo']) !== '') {
            $prossimo = ['testo' => $c['prossimo'], 'chi' => '', 'entro' => null, 'scaduto' => false];
        }

        return [
            'aggiornato' => self::data($c['aggiornato'] ?? null),
            'sintesi' => (string) ($c['sintesi'] ?? ''),
            'tappe' => $tappe,
            'manca' => $manca,
            'prossimo' => $prossimo,
            'fineprevista' => self::data($c['fineprevista'] ?? null),
            'rischio' => trim((string) ($c['rischio'] ?? '')),
            'percentuale' => $this->percentuale($tappe),
            'ultimaOk' => $this->ultimaOk($tappe),
        ];
    }

    /** ok = 1, corso/verificare = 0,5, attesa/rischio/no = 0; na/escluso fuori dal totale. */
    public function percentuale(array $tappe): int
    {
        $tot = 0;
        $fatto = 0.0;
        foreach ($tappe as $t) {
            if (in_array($t['stato'], ['na', 'escluso'], true)) {
                continue;
            }
            $peso = self::PESI[$t['stato']] ?? 0.0;
            $tot++;
            $fatto += $peso;
        }

        return $tot ? (int) round($fatto / $tot * 100) : 0;
    }

    /** Indice dell'ultima tappa fatta (la linea dello stepper e' piena fino a li'), -1 se nessuna. */
    public function ultimaOk(array $tappe): int
    {
        $u = -1;
        foreach (array_values($tappe) as $i => $t) {
            if ($t['stato'] === 'ok') {
                $u = $i;
            }
        }

        return $u;
    }

    /** A rischio: una tappa bloccata o un rischio scritto. */
    public function aRischio(?array $s): bool
    {
        if ($s === null) {
            return false;
        }

        return $s['rischio'] !== '' || collect($s['tappe'])->contains(fn ($t) => $t['stato'] === 'rischio');
    }

    /** Senza dati vivi: la tappa "Collegata allo SCADA" (t3) non e' fatta, o la scheda manca. */
    public function senzaDatiVivi(?array $s): bool
    {
        if ($s === null) {
            return true;
        }
        $t3 = collect($s['tappe'])->firstWhere('id', 't3');

        return $t3 !== null && $t3['stato'] !== 'ok';
    }

    /**
     * Tappe di tutte le macchine (per id, nell'ordine in cui compaiono) con quante macchine le hanno fatte.
     *
     * @param  Collection<int, array{scheda: ?array}>  $righe
     */
    public function riepilogo(Collection $righe): array
    {
        $tappe = [];
        foreach ($righe as $r) {
            foreach ($r['scheda']['tappe'] ?? [] as $t) {
                $tappe[$t['id']] ??= ['id' => $t['id'], 'titolo' => $t['titolo'], 'ok' => 0, 'totale' => 0];
                if (in_array($t['stato'], ['na', 'escluso'], true)) {
                    continue;
                }
                $tappe[$t['id']]['totale']++;
                if ($t['stato'] === 'ok') {
                    $tappe[$t['id']]['ok']++;
                }
            }
        }

        return array_values($tappe);
    }

    private static function data(mixed $v): ?Carbon
    {
        if (! is_string($v) || trim($v) === '') {
            return null;
        }
        try {
            return Carbon::parse($v)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
