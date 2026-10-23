<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\Project;
use Illuminate\Support\Carbon;

/**
 * Area pubblica "stato avanzamento" (Francesco, 23/10/2026): pagina in sola lettura per Salpa,
 * Edica, fornitori e certificatore, raggiungibile con un link segreto senza login.
 *
 * La chiave non e' salvata da nessuna parte: e' l'HMAC di slug e `info.pubblico.versione` con
 * APP_KEY; per cambiare il link basta alzare la versione nel file del PC.
 *
 * Qui si costruisce SOLO quello che puo' uscire: dalle schede si prendono titolo, stato e date
 * delle tappe, lo stato SCADA con la data della visita e lo stato dei requisiti. I testi liberi
 * vengono solo dal campo `pubblico` della macchina, scritto apposta dal PC. Mai note, fonti,
 * `certificazione.manca/prossimo/sintesi/rischio`, variabili, IP, persone, compiti o mail.
 */
class Pubblico
{
    public function __construct(private Certificazione $cert, private MappaProgetto $mappa) {}

    /** Il blocco `info.pubblico` del progetto (array vuoto se manca). */
    public static function impostazioni(Project $progetto): array
    {
        $p = $progetto->info['pubblico'] ?? null;

        return is_array($p) ? $p : [];
    }

    /** L'area pubblica e' accesa solo con `info.pubblico.attivo === true`. */
    public static function attivo(Project $progetto): bool
    {
        return (self::impostazioni($progetto)['attivo'] ?? null) === true;
    }

    /** Chiave del link: 20 caratteri esadecimali dall'HMAC di slug e versione. */
    public static function chiave(Project $progetto): string
    {
        $v = self::impostazioni($progetto)['versione'] ?? 1;
        $versione = is_scalar($v) && (string) $v !== '' ? (string) $v : '1';

        return substr(hash_hmac('sha256', 'pubblico|'.$progetto->slug.'|'.$versione, (string) config('app.key')), 0, 20);
    }

    public static function chiaveValida(Project $progetto, string $chiave): bool
    {
        return hash_equals(self::chiave($progetto), $chiave);
    }

    public static function url(Project $progetto): string
    {
        return route('pubblico', [$progetto->slug, self::chiave($progetto)]);
    }

    /** Solo il nome dell'azienda: `pubblico.fornitore`, altrimenti `fornitore` fino a " —" o " (". */
    public static function fornitore(Machine $m): string
    {
        $pub = $m->dato('pubblico');
        if (is_array($pub) && is_scalar($pub['fornitore'] ?? null) && trim((string) $pub['fornitore']) !== '') {
            return trim((string) $pub['fornitore']);
        }
        $f = $m->dato('fornitore');
        if (! is_scalar($f)) {
            return '';
        }
        $f = (string) $f;
        foreach ([' —', ' ('] as $sep) {
            $pos = mb_strpos($f, $sep);
            if ($pos !== false) {
                $f = mb_substr($f, 0, $pos);
            }
        }

        return trim($f);
    }

    /**
     * Tutto cio' che la pagina pubblica mostra, gia' filtrato.
     *
     * @return array{testata: array, macchine: array, riepilogo: array, requisiti: array<string, string>}
     */
    public function pagina(Project $progetto): array
    {
        $imp = self::impostazioni($progetto);
        $requisiti = $this->mappa->requisiti($progetto->info ?? []);
        $oggi = Carbon::today();

        $righe = collect();
        $macchine = [];
        foreach ($progetto->machines->reject(fn ($m) => $m->dato('escludiDaiConteggi'))->values() as $m) {
            $scheda = $this->cert->scheda($m);
            $righe->push(['scheda' => $scheda]);

            $tappe = [];
            foreach ($scheda['tappe'] ?? [] as $t) {
                $tappe[] = [
                    'titolo' => $t['titolo'],
                    'stato' => $t['stato'],
                    'data' => $t['data'],
                    'previsto' => $t['previsto'],
                    'inRitardo' => $t['stato'] !== 'ok' && $t['data'] === null && $t['previsto'] !== null && $t['previsto']->lt($oggi),
                ];
            }

            $p = $this->mappa->progetto($m);
            $req = [];
            foreach (array_keys($requisiti) as $r) {
                $req[$r] = MappaProgetto::stato($p['requisiti'][$r]['stato'] ?? null);
            }

            $pub = $m->dato('pubblico');
            $pub = is_array($pub) ? $pub : [];
            $manca = [];
            foreach ((array) ($pub['manca'] ?? []) as $x) {
                if (is_string($x)) {
                    $x = ['testo' => $x];
                }
                if (($v = $this->voce($x, $oggi)) !== null) {
                    $manca[] = $v;
                }
            }
            $prossimo = $this->voce($pub['prossimo'] ?? null, $oggi);

            $scada = null;
            if (($stato = Scada::statoDi($m)) !== null) {
                $s = $m->dato('scada');
                $visto = is_scalar($s['visto'] ?? null) ? (string) $s['visto'] : '';
                $scada = [
                    'stato' => $stato,
                    'etichetta' => Scada::STATI[$stato][0],
                    'classe' => Scada::STATI[$stato][1],
                    'visto' => preg_match('/^\d{4}-\d{2}-\d{2}/', $visto) ? Scada::giornoMese($visto) : '',
                ];
            }

            $ritardo = collect($manca)->contains('scaduto', true)
                || ($prossimo['scaduto'] ?? false)
                || collect($tappe)->contains('inRitardo', true);

            $macchine[] = [
                'codice' => $m->codice,
                'num' => (string) $m->dato('num', $m->codice),
                'nome' => (string) $m->dato('nome', ''),
                'fornitore' => self::fornitore($m),
                'compilata' => $scheda !== null,
                'percentuale' => $scheda['percentuale'] ?? 0,
                'ultimaOk' => $scheda['ultimaOk'] ?? -1,
                'tappe' => $tappe,
                'bloccata' => collect($tappe)->contains('stato', 'rischio'),
                'scada' => $scada,
                'requisiti' => $req,
                'stato' => $this->testo($pub['stato'] ?? null),
                'dati' => $this->testo($pub['dati'] ?? null),
                'prossimo' => $prossimo,
                'manca' => $manca,
                'ritardo' => $ritardo,
            ];
        }

        $scadenza = $this->data($progetto->info['scadenza'] ?? null);
        $aggiornato = $imp['aggiornato'] ?? null;
        $aggiornatoData = $this->data($aggiornato);

        return [
            'testata' => [
                'nome' => $progetto->nome,
                'aggiornato' => $aggiornatoData ? $aggiornatoData->format('d/m/Y') : $this->testo($aggiornato),
                'sintesi' => $this->testo($imp['sintesi'] ?? null),
                'scadenza' => $scadenza,
                'giorni' => $scadenza ? (int) $oggi->diffInDays($scadenza, false) : null,
                'media' => count($macchine) ? (int) round(collect($macchine)->avg('percentuale')) : 0,
                'inRitardo' => collect($macchine)->where('ritardo', true)->count(),
            ],
            'macchine' => $macchine,
            // solo id, titolo e conteggi: niente note
            'riepilogo' => array_map(fn ($t) => ['titolo' => $t['titolo'], 'ok' => $t['ok'], 'totale' => $t['totale']], $this->cert->riepilogo($righe)),
            'requisiti' => $requisiti,
        ];
    }

    /** Voce {testo, azienda, entro} di `pubblico.manca` o `pubblico.prossimo`, o null. */
    private function voce(mixed $x, Carbon $oggi): ?array
    {
        if (! is_array($x) || ($testo = $this->testo($x['testo'] ?? null)) === '') {
            return null;
        }
        $entro = $this->data($x['entro'] ?? null);

        return ['testo' => $testo, 'azienda' => $this->testo($x['azienda'] ?? null), 'entro' => $entro,
            'scaduto' => $entro !== null && $entro->lt($oggi)];
    }

    private function testo(mixed $v): string
    {
        return is_scalar($v) ? trim((string) $v) : '';
    }

    private function data(mixed $v): ?Carbon
    {
        if (! is_string($v) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($v))) {
            return null;
        }
        try {
            return Carbon::parse(trim($v))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
