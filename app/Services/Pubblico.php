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
        $pulisci = Anonimizza::perProgetto($progetto);

        $righe = collect();
        $macchine = [];
        foreach ($progetto->machines->reject(fn ($m) => $m->dato('escludiDaiConteggi'))->values() as $m) {
            $scheda = $this->cert->scheda($m);
            $righe->push(['scheda' => $scheda]);

            $tappe = [];
            foreach ($scheda['tappe'] ?? [] as $t) {
                $tappe[] = [
                    'id' => $t['id'],
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
                'tecnici' => $this->tecnici($m, $p, $pulisci),
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
                'giorni' => $scadenza ? (int) round($oggi->diffInDays($scadenza, false)) : null,
                'media' => count($macchine) ? (int) round(collect($macchine)->avg('percentuale')) : 0,
                'inRitardo' => collect($macchine)->where('ritardo', true)->count(),
            ],
            'macchine' => $macchine,
            'gantt' => $this->gantt($progetto, $macchine, $scadenza, $pulisci),
            // solo id, titolo e conteggi: niente note
            'riepilogo' => array_map(fn ($t) => ['titolo' => $t['titolo'], 'ok' => $t['ok'], 'totale' => $t['totale']], $this->cert->riepilogo($righe)),
            'requisiti' => $requisiti,
        ];
    }

    /**
     * "Dati tecnici" della macchina (Francesco, 23/10/2026: IP, protocollo, variabili...). Solo campi
     * strutturati, ogni stringa passata da Anonimizza (niente citazioni tra quadre, persone -> azienda).
     * Mai `nota`, `fonte`, `dettaglio`, `sintesi`, `domande`, `schermate`, credenziali.
     */
    private function tecnici(Machine $m, array $p, Anonimizza $x): array
    {
        $t = fn ($v) => $x->testo($v);
        $out = ['macchina' => [], 'anagrafica' => [], 'scada' => null, 'uscite' => [], 'ingressi' => [], 'collegamenti' => [], 'nodi' => []];

        foreach (['ip' => 'IP', 'protocollo' => 'Protocollo', 'rete' => 'Rete'] as $k => $etichetta) {
            if (($v = $t($m->dato($k))) !== '') {
                $out['macchina'][] = [$etichetta, $v];
            }
        }
        foreach ($p['anagrafica'] as $a) {
            $voce = $t($a['voce'] ?? '');
            $valore = $t($a['valore'] ?? '');
            if ($voce !== '' || $valore !== '') {
                $out['anagrafica'][] = [$voce, $valore];
            }
        }

        $s = $m->dato('scada');
        if (is_array($s) && $s !== [] && ($stato = Scada::statoDi($m)) !== null) {
            $variabili = [];
            foreach ((array) ($s['variabili'] ?? []) as $v) {
                if (is_array($v) && is_scalar($v['nome'] ?? null)) {
                    $variabili[] = ['nome' => $t($v['nome']), 'valore' => $t($v['valore'] ?? ''), 'tipo' => $t($v['tipo'] ?? ''),
                        'accesso' => $t($v['accesso'] ?? ''), 'qualita' => $t($v['qualita'] ?? '')];
                }
            }
            $pagine = [];
            foreach ((array) ($s['pagine'] ?? []) as $pg) {
                if (is_string($pg)) {
                    $pg = ['nome' => $pg];
                }
                if (is_array($pg) && is_scalar($pg['nome'] ?? null)) {
                    $pagine[] = ['nome' => $t($pg['nome']), 'data' => $this->giorno($pg['data'] ?? null, $x)];
                }
            }
            $out['scada'] = [
                'etichetta' => Scada::STATI[$stato][0],
                'classe' => Scada::STATI[$stato][1],
                'visto' => $this->giorno($s['visto'] ?? null, $x),
                'connessione' => $t($s['connessione'] ?? ''),
                'storico' => $t($s['storico'] ?? ''),
                'variabili' => $variabili,
                'pagine' => $pagine,
            ];
        }

        $req = fn ($r) => implode(' ', array_filter(array_map(fn ($z) => is_scalar($z) ? (string) $z : '', (array) $r)));
        foreach ($p['uscite'] as $u) {
            if (($dato = $t($u['dato'] ?? '')) !== '') {
                $out['uscite'][] = ['dato' => $dato, 'da' => '', 'variabile' => $t($u['variabile'] ?? ''), 'req' => $req($u['req'] ?? []), 'stato' => MappaProgetto::stato($u['stato'] ?? null)];
            }
        }
        foreach ($p['ingressi'] as $u) {
            if (($dato = $t($u['dato'] ?? '')) !== '') {
                $out['ingressi'][] = ['dato' => $dato, 'da' => $t($u['da'] ?? ''), 'variabile' => $t($u['variabile'] ?? ''), 'req' => $req($u['req'] ?? []), 'stato' => MappaProgetto::stato($u['stato'] ?? null)];
            }
        }

        $nomi = [];
        foreach ($p['nodi'] as $n) {
            $nome = $t($n['nome'] ?? '');
            if ($nome === '') {
                continue;
            }
            if (is_scalar($n['id'] ?? null)) {
                $nomi[(string) $n['id']] = $nome;
            }
            $out['nodi'][] = ['nome' => $nome, 'tipo' => $t($n['tipo'] ?? ''), 'ip' => $t($n['ip'] ?? ''), 'modello' => $t($n['modello'] ?? ''), 'stato' => MappaProgetto::stato($n['stato'] ?? null)];
        }
        foreach ($p['collegamenti'] as $k) {
            $da = is_scalar($k['da'] ?? null) ? ($nomi[(string) $k['da']] ?? '') : '';
            $a = is_scalar($k['a'] ?? null) ? ($nomi[(string) $k['a']] ?? '') : '';
            $titolo = $t($k['titolo'] ?? '');
            if ($titolo === '' && $da === '' && $a === '') {
                continue;
            }
            $out['collegamenti'][] = ['titolo' => $titolo, 'da' => $da, 'a' => $a, 'entrambi' => ($k['verso'] ?? 'uno') === 'entrambi',
                'protocollo' => $t($k['protocollo'] ?? ''), 'dati' => $t($k['dati'] ?? []), 'stato' => MappaProgetto::stato($k['stato'] ?? null)];
        }

        $out['vuoto'] = ! $out['macchina'] && ! $out['anagrafica'] && ! $out['scada'] && ! $out['uscite'] && ! $out['ingressi'] && ! $out['collegamenti'] && ! $out['nodi'];

        return $out;
    }

    /** "16/10/2026" da una data AAAA-MM-GG, altrimenti il testo pulito. */
    private function giorno(mixed $v, Anonimizza $x): string
    {
        $d = is_scalar($v) ? (string) $v : '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $d)) {
            try {
                return Carbon::parse(substr($d, 0, 10))->format('d/m/Y');
            } catch (\Throwable) {
            }
        }

        return $x->testo($d);
    }

    /** Inizio fisso dell'asse del Gantt (lunedi' della prima settimana di lavoro). */
    public const GANTT_DAL = '2026-09-28';

    /** Prima tappa: parte da qui se non c'e' una tappa precedente. */
    public const GANTT_PRIMA = '2026-09-30';

    /**
     * Gantt del workflow (Francesco, 23/10/2026): una riga per macchina con le tappe come segmenti
     * consecutivi, gli appuntamenti accettati con macchine come rombi, e in cima la riga "Progetto"
     * con le fasi del planning senza macchine. Posizioni in % dell'asse (settimane da lunedi').
     */
    private function gantt(Project $progetto, array $macchine, ?Carbon $scadenza, Anonimizza $x): ?array
    {
        $dal = Carbon::parse(self::GANTT_DAL)->startOfWeek(Carbon::MONDAY);
        $fine = ($scadenza ?? Carbon::parse('2026-11-30'))->copy()->startOfDay();
        if ($fine->lte($dal)) {
            return null;
        }
        $al = $fine->copy()->endOfWeek(Carbon::SUNDAY)->addDay()->startOfDay(); // esclusivo
        $al = $al->min($dal->copy()->addWeeks(52)); // al massimo un anno di settimane
        $giorni = (int) round($dal->diffInDays($al));
        $pos = fn (Carbon $d) => round(max(0, min($giorni, (int) round($dal->diffInDays($d, false)))) / $giorni * 100, 3);
        $unGiorno = 100 / $giorni;
        $oggi = Carbon::today();
        $stati = MappaProgetto::STATI;

        $settimane = [];
        for ($d = $dal->copy(); $d->lt($al); $d->addWeek()) {
            $settimane[] = ['etichetta' => $d->format('d/m'), 'x' => $pos($d), 'w' => round(7 * $unGiorno, 3)];
        }
        $mesiNomi = [1 => 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
        $mesi = [];
        for ($d = $dal->copy(); $d->lt($al); $d = $d->copy()->startOfMonth()->addMonth()) {
            $f = $d->copy()->startOfMonth()->addMonth();
            $f = $f->gt($al) ? $al->copy() : $f;
            $mesi[] = ['nome' => $mesiNomi[$d->month], 'x' => $pos($d), 'w' => round($pos($f) - $pos($d), 3)];
        }

        // appuntamenti accettati con macchine: rombi sulle righe (titolo senza nomi di persone)
        $rombi = [];
        foreach ($progetto->appuntamenti()->where('stato', 'accettato')->get() as $ap) {
            $titolo = $x->testo($ap->titolo);
            if (! $ap->inizio || $titolo === '' || preg_match('/^(ANNULLATO|SPOSTATO|NON PARTECIPO)/iu', $titolo)) {
                continue;
            }
            $giorno = $ap->inizio->copy()->startOfDay();
            if ($giorno->lt($dal) || $giorno->gte($al)) {
                continue;
            }
            foreach ((array) $ap->macchine as $codice) {
                if (is_string($codice)) {
                    $rombi[$codice][] = ['x' => round($pos($giorno) + $unGiorno / 2, 3), 'testo' => $ap->inizio->format('d/m H:i').' — '.$titolo];
                }
            }
        }

        $righe = [];
        foreach ($macchine as $m) {
            $segmenti = [];
            $inizio = Carbon::parse(self::GANTT_PRIMA);
            foreach ($m['tappe'] as $i => $tp) {
                $data = $tp['stato'] === 'ok' ? ($tp['data'] ?? $tp['previsto']) : ($tp['previsto'] ?? $tp['data']);
                if ($data === null) {
                    continue;
                }
                $fineSeg = $data->copy()->addDay();
                $da = $inizio->lt($fineSeg) ? $inizio : $data->copy();
                $x1 = $pos($da);
                $w = round($pos($fineSeg) - $x1, 3);
                $ritardo = $tp['stato'] !== 'ok' && $tp['previsto'] !== null && $tp['previsto']->lt($oggi);
                $n = preg_match('/^t\d+$/i', (string) ($tp['id'] ?? '')) ? strtoupper($tp['id']) : 'T'.($i + 1);
                $segmenti[] = [
                    'x' => $x1, 'w' => max(0.6, $w),
                    'stato' => $tp['stato'],
                    'etichetta' => $w >= 6 * $unGiorno ? $n.' '.$tp['titolo'] : ($w >= 2.5 * $unGiorno ? $n : ''),
                    'testo' => $n.' '.$tp['titolo'].': '.$stati[$tp['stato']][1].' — '.($tp['stato'] === 'ok' ? 'fatta il ' : 'prevista ').$data->format('d/m/Y').($ritardo ? ' (in ritardo)' : ''),
                    'ritardo' => $ritardo,
                ];
                $inizio = $fineSeg->gt($inizio) ? $fineSeg : $inizio;
            }
            $righe[] = ['codice' => $m['codice'], 'num' => $m['num'], 'nome' => $m['nome'], 'segmenti' => $segmenti, 'rombi' => $rombi[$m['codice']] ?? []];
        }

        // riga "Progetto": fasi del planning senza macchine, su piu' corsie se si sovrappongono
        $fasi = [];
        foreach ((array) ($progetto->info['planning'] ?? []) as $pl) {
            if (! is_array($pl) || array_filter((array) ($pl['macchine'] ?? [])) !== []) {
                continue;
            }
            $d1 = $this->data($pl['dal'] ?? null);
            $d2 = $this->data($pl['al'] ?? null) ?? $d1;
            $titolo = $x->testo($pl['titolo'] ?? '');
            if (! $d1 || $titolo === '') {
                continue;
            }
            $st = in_array($pl['stato'] ?? '', ['previsto', 'fisso', 'corso', 'fatto', 'rischio'], true) ? $pl['stato'] : 'previsto';
            $fasi[] = ['d1' => $d1, 'd2' => $d2->lt($d1) ? $d1 : $d2, 'titolo' => $titolo, 'stato' => $st];
        }
        usort($fasi, fn ($a, $b) => $a['d1'] <=> $b['d1']);
        $corsie = [];
        $barre = [];
        $nomiStato = ['previsto' => 'prevista', 'fisso' => 'data fissata', 'corso' => 'in corso', 'fatto' => 'fatta', 'rischio' => 'a rischio'];
        foreach ($fasi as $f) {
            $c = 0;
            while (isset($corsie[$c]) && $corsie[$c]->gte($f['d1'])) {
                $c++;
            }
            $corsie[$c] = $f['d2'];
            $x1 = $pos($f['d1']);
            $barre[] = ['x' => $x1, 'w' => max(0.6, round($pos($f['d2']->copy()->addDay()) - $x1, 3)), 'corsia' => $c, 'stato' => $f['stato'],
                'etichetta' => $f['titolo'],
                'testo' => $f['titolo'].' — '.$f['d1']->format('d/m').($f['d2']->eq($f['d1']) ? '' : '–'.$f['d2']->format('d/m')).' ('.$nomiStato[$f['stato']].')'];
        }

        return [
            'settimane' => $settimane,
            'mesi' => $mesi,
            'oggi' => $oggi->gte($dal) && $oggi->lt($al) ? round($pos($oggi) + $unGiorno / 2, 3) : null,
            'oggiTesto' => $oggi->format('d/m'),
            'scadenza' => $pos($fine->copy()->addDay()),
            'scadenzaTesto' => $fine->format('d/m'),
            'righe' => $righe,
            'progetto' => $barre,
            'corsie' => max(1, count($corsie)),
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
