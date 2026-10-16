<?php

namespace App\Services;

use App\Models\Machine;

/**
 * Progetto 4.0 di una macchina (campo `progetto` della macchina nel file del PC) e
 * disposizione della mappa concettuale: nodi su livelli (gestionale in alto, campo in
 * basso, come la piramide dell'automazione), collegamenti come curve con il verso.
 *
 * Tutto si calcola qui, lato server, cosi' la pagina e' un SVG statico che si legge
 * anche senza JavaScript e i test possono controllarlo.
 */
class MappaProgetto
{
    /** Stati validi in tutto il progetto: simbolo, etichetta. */
    public const STATI = [
        'ok' => ['✓', 'Fatto / funziona'],
        'corso' => ['▸', 'In lavorazione'],
        'attesa' => ['…', 'Aspettiamo altri'],
        'verificare' => ['?', 'Da verificare'],
        'rischio' => ['!', 'Bloccato / a rischio'],
        'no' => ['·', 'Non iniziato / manca'],
        'na' => ['–', 'Non applicabile'],
        'escluso' => ['×', 'Fuori perimetro'],
    ];

    /** Livelli della mappa, dall'alto (16/10/2026: aggiunti Data Collector e rete). */
    public const LIVELLI = [
        0 => 'Gestionale e logistica',
        1 => 'Data Collector',
        2 => 'Supervisione (SCADA, storico, cloud)',
        3 => 'Rete (router, eWON, NAT)',
        4 => 'Controllo macchina (PLC, HMI, dispositivi)',
        5 => 'Campo e persone',
    ];

    private const LIVELLO_TIPO = [
        'gestionale' => 0, 'datacollector' => 1, 'scada' => 2, 'storico' => 2, 'cloud' => 2, 'rete' => 3,
        'plc' => 4, 'hmi' => 4, 'dispositivo' => 4, 'macchina' => 5, 'sensore' => 5, 'persona' => 5,
    ];

    /** `"livello": n` numerico nei file e' nella vecchia scala a 4 livelli (0 gestionale … 3 campo). */
    private const LIVELLO_VECCHIO = [0 => 0, 1 => 2, 2 => 4, 3 => 5];

    /** Nome breve del tipo di nodo, per il riquadro della mappa. */
    public const TIPI = [
        'gestionale' => 'Gestionale', 'datacollector' => 'Data Collector', 'scada' => 'SCADA', 'storico' => 'Storico',
        'cloud' => 'Cloud', 'rete' => 'Rete', 'plc' => 'PLC', 'hmi' => 'HMI', 'dispositivo' => 'Dispositivo',
        'macchina' => 'Macchina', 'sensore' => 'Sensore', 'persona' => 'Persona',
    ];

    /** Cosa vuol dire il colore di una freccia (legenda della mappa, Francesco 16/10/2026). */
    public const LEGENDA_FILI = [
        'ok' => 'i dati passano davvero',
        'corso' => 'collegato, ma senza dati utili',
        'attesa' => 'preparato, ma vuoto',
        'rischio' => 'non risponde',
        'no' => 'manca',
        'verificare' => 'da verificare',
        'na' => 'non applicabile / fuori perimetro',
    ];

    /** Viste della mappa: com'e' oggi (visto sullo SCADA) e l'obiettivo 4.0. */
    public const VISTE = ['oggi' => 'Oggi', 'obiettivo' => 'Obiettivo 4.0'];

    /** Requisiti dell'Ing. Romano (verbale riunione 30/09/2026), se il file non li porta in info.requisiti. */
    public const REQUISITI = [
        'R1' => 'Ricette / istruzioni da remoto', 'R2' => 'Presa in lavorazione', 'R3' => 'Input e output', 'R4' => 'Tempi',
        'R5' => 'Temperature', 'R6' => 'Pressione', 'R7' => 'Velocità', 'R8' => 'Stato macchina', 'R9' => 'Allarmi',
        'R10' => 'OEE', 'R11' => 'IP istantaneo su SCADA', 'R12' => 'Storico dati', 'R13' => 'Bidirezionalità con il gestionale',
    ];

    public const LARGHEZZA = 1000;
    private const NODO_L = 196;
    private const NODO_H = 58;
    private const PASSO_Y = 150;
    private const MARGINE_Y = 44;

    public static function stato(mixed $s): string
    {
        return is_string($s) && isset(self::STATI[$s]) ? $s : 'no';
    }

    /** Il progetto della macchina, con tutte le chiavi presenti (elenchi vuoti dove manca). */
    public function progetto(Machine $macchina): array
    {
        $p = $macchina->dato('progetto');
        $p = is_array($p) ? $p : [];
        $elenco = fn ($k) => array_values(array_filter(is_array($p[$k] ?? null) ? $p[$k] : [], 'is_array'));

        return [
            'impostato' => $p !== [],
            'aggiornato' => is_string($p['aggiornato'] ?? null) ? $p['aggiornato'] : null,
            'sintesi' => is_string($p['sintesi'] ?? null) ? $p['sintesi'] : '',
            'principi' => array_values(array_filter((array) ($p['principi'] ?? []), 'is_string')),
            'anagrafica' => $elenco('anagrafica'),
            'attori' => $elenco('attori'),
            'nodi' => $elenco('nodi'),
            'collegamenti' => $elenco('collegamenti'),
            'uscite' => $elenco('uscite'),
            'ingressi' => $elenco('ingressi'),
            'bidirezionale' => is_array($p['bidirezionale'] ?? null) ? $p['bidirezionale'] : null,
            'requisiti' => is_array($p['requisiti'] ?? null) ? $p['requisiti'] : [],
            'strade' => $elenco('strade'),
            'aperti' => $elenco('aperti'),
            'avanzamento' => $elenco('avanzamento'),
            'documenti' => $elenco('documenti'),
            'oggi' => $this->oggi($p['oggi'] ?? null),
        ];
    }

    /** La vista "Oggi" (`progetto.oggi`), o null se manca o non ha nodi. */
    private function oggi(mixed $o): ?array
    {
        if (! is_array($o)) {
            return null;
        }
        $elenco = fn ($k) => array_values(array_filter(is_array($o[$k] ?? null) ? $o[$k] : [], 'is_array'));
        $nodi = $elenco('nodi');

        return $nodi === [] ? null : [
            'aggiornato' => is_string($o['aggiornato'] ?? null) ? $o['aggiornato'] : null,
            'fonte' => is_string($o['fonte'] ?? null) ? $o['fonte'] : null,
            'nodi' => $nodi,
            'collegamenti' => $elenco('collegamenti'),
        ];
    }

    /** La vista richiesta, valida rispetto ai dati: "oggi" solo se c'e', di default "oggi" se c'e'. */
    public function vista(array $progetto, ?string $chiesta): string
    {
        if ($chiesta === 'obiettivo' || $progetto['oggi'] === null) {
            return 'obiettivo';
        }

        return 'oggi';
    }

    /** Nodi e collegamenti della vista; con $stretto la vista "oggi" mancante resta vuota (Mappa impianto). */
    public function grafo(array $progetto, string $vista, bool $stretto = false): array
    {
        if ($vista === 'oggi' && $progetto['oggi'] !== null) {
            return ['nodi' => $progetto['oggi']['nodi'], 'collegamenti' => $progetto['oggi']['collegamenti']];
        }
        if ($vista === 'oggi' && $stretto) {
            return ['nodi' => [], 'collegamenti' => []];
        }

        return ['nodi' => $progetto['nodi'], 'collegamenti' => $progetto['collegamenti']];
    }

    /** Livello di un nodo: forzato con `livello` (numero nella vecchia scala, o nome di un tipo), altrimenti dal tipo. */
    public static function livello(array $n): int
    {
        $l = $n['livello'] ?? null;
        if (is_numeric($l)) {
            return self::LIVELLO_VECCHIO[max(0, min(3, (int) $l))];
        }
        if (is_string($l) && isset(self::LIVELLO_TIPO[$l])) {
            return self::LIVELLO_TIPO[$l];
        }

        return self::LIVELLO_TIPO[$n['tipo'] ?? ''] ?? 4;
    }

    /** Requisiti del progetto: da info.requisiti ([["R1","nome"],...] o {"R1":"nome"}) o quelli del verbale. */
    public function requisiti(array $info): array
    {
        $r = $info['requisiti'] ?? null;
        if (! is_array($r) || $r === []) {
            return self::REQUISITI;
        }
        $out = [];
        foreach ($r as $k => $v) {
            if (is_array($v) && count($v) >= 2) {
                $out[(string) $v[0]] = (string) $v[1];
            } elseif (is_string($v)) {
                $out[(string) $k] = $v;
            }
        }

        return $out ?: self::REQUISITI;
    }

    /** Fatti / totale delle voci di avanzamento. */
    public function avanzamento(array $progetto): array
    {
        $voci = $progetto['avanzamento'];

        return [count(array_filter($voci, fn ($v) => ! empty($v['fatto']))), count($voci)];
    }

    /** Conteggio degli stati dei requisiti (per la barra di copertura). */
    public function copertura(array $progetto, array $requisiti): array
    {
        $conta = array_fill_keys(array_keys(self::STATI), 0);
        foreach (array_keys($requisiti) as $r) {
            $conta[self::stato($progetto['requisiti'][$r]['stato'] ?? null)]++;
        }

        return $conta;
    }

    /**
     * Disposizione della mappa: nodi con x, y (angolo in alto a sinistra) e collegamenti con il
     * tracciato SVG, il punto dell'etichetta e il numero mostrato nel tondino.
     */
    public function mappa(array $progetto): array
    {
        $nodi = [];
        foreach ($progetto['nodi'] as $i => $n) {
            $id = (string) ($n['id'] ?? 'n'.$i);
            $nodi[$id] = array_merge($n, ['id' => $id, 'livello' => self::livello($n), 'stato' => self::stato($n['stato'] ?? null)]);
        }
        if ($nodi === []) {
            return ['nodi' => [], 'collegamenti' => [], 'livelli' => [], 'altezza' => 0, 'larghezza' => self::LARGHEZZA];
        }

        // solo i livelli usati, uno sotto l'altro
        $usati = array_values(array_unique(array_map(fn ($n) => $n['livello'], $nodi)));
        sort($usati);
        $livelli = [];
        foreach ($usati as $riga => $l) {
            $inLivello = array_values(array_filter($nodi, fn ($n) => $n['livello'] === $l));
            $y = self::MARGINE_Y + $riga * self::PASSO_Y;
            $livelli[] = ['nome' => self::LIVELLI[$l], 'y' => $y, 'h' => self::NODO_H + 40];
            $quanti = count($inLivello);
            foreach ($inLivello as $j => $n) {
                $cx = self::LARGHEZZA * ($j + 0.5) / $quanti;
                $nodi[$n['id']]['x'] = (int) round($cx - self::NODO_L / 2);
                $nodi[$n['id']]['y'] = $y;
                $nodi[$n['id']]['w'] = self::NODO_L;
                $nodi[$n['id']]['h'] = self::NODO_H;
            }
        }

        return [
            'nodi' => array_values($nodi),
            'collegamenti' => $this->fili($nodi, $progetto['collegamenti']),
            'livelli' => $livelli,
            'altezza' => self::MARGINE_Y + (count($usati) - 1) * self::PASSO_Y + self::NODO_H + 40,
            'larghezza' => self::LARGHEZZA,
        ];
    }

    /** Collegamenti con il tracciato: i nodi devono avere gia' x, y, w, h. */
    private function fili(array $nodi, array $elenco): array
    {
        // collegamenti: piu' collegamenti tra gli stessi due nodi si allargano a ventaglio
        $coppie = [];
        $collegamenti = [];
        foreach ($elenco as $i => $k) {
            $da = (string) ($k['da'] ?? '');
            $a = (string) ($k['a'] ?? '');
            if (! isset($nodi[$da], $nodi[$a]) || $da === $a) {
                continue;
            }
            $chiave = $da < $a ? $da.'|'.$a : $a.'|'.$da;
            $indice = $coppie[$chiave] = ($coppie[$chiave] ?? -1) + 1;
            $collegamenti[] = ['k' => $k, 'da' => $nodi[$da], 'a' => $nodi[$a], 'indice' => $indice];
        }
        $totali = $coppie;

        $out = [];
        $occupati = [];
        foreach ($collegamenti as $num => $c) {
            $k = $c['k'];
            [$da, $a] = [$c['da'], $c['a']];
            $chiave = $da['id'] < $a['id'] ? $da['id'].'|'.$a['id'] : $a['id'].'|'.$da['id'];
            $n = $totali[$chiave] + 1;
            $scarto = ($c['indice'] - ($n - 1) / 2) * 40;
            [$d, $punti] = $this->tracciato($da, $a, $scarto);
            [$lx, $ly] = $this->etichetta($punti, $occupati);
            $out[] = array_merge($k, [
                'id' => (string) ($k['id'] ?? 'k'.$num),
                'numero' => $num + 1,
                'stato' => self::stato($k['stato'] ?? null),
                'entrambi' => ($k['verso'] ?? 'uno') === 'entrambi',
                'path' => $d,
                'lx' => (int) round($lx),
                'ly' => (int) round($ly),
                'nomeDa' => (string) ($da['nome'] ?? $da['id']),
                'nomeA' => (string) ($a['nome'] ?? $a['id']),
                'dati' => array_values(array_filter((array) ($k['dati'] ?? []), 'is_string')),
                'variabili' => array_values(array_filter((array) ($k['variabili'] ?? []), 'is_string')),
            ]);
        }

        return $out;
    }

    /**
     * Mappa d'insieme dell'impianto (Francesco, 16/10/2026): unisce la vista scelta di tutte le
     * macchine. I nodi condivisi (id che iniziano con "g-": SCADA, storico, Data Collector,
     * gestionale, rete) compaiono una volta sola, centrati in alto; gli altri nodi stanno nella
     * colonna della loro macchina, con il numero della macchina davanti al nome.
     *
     * @param  array<int, array{codice: string, num: string, nome: string, progetto: array}>  $macchine
     */
    public function impianto(array $macchine, string $vista): array
    {
        $COL = self::NODO_L + 24;
        $RIGA = self::NODO_H + 18;
        $nodi = [];
        $elenco = [];
        $colonne = [];
        foreach ($macchine as $m) {
            $g = $this->grafo($m['progetto'], $vista, true);
            if ($g['nodi'] === []) {
                continue;
            }
            $colonne[] = $m;
            $mappaId = [];
            foreach ($g['nodi'] as $i => $n) {
                $orig = (string) ($n['id'] ?? 'n'.$i);
                $condiviso = str_starts_with($orig, 'g-');
                $id = $condiviso ? $orig : $m['codice'].':'.$orig;
                $mappaId[$orig] = $id;
                if (isset($nodi[$id])) {
                    $nodi[$id]['macchine'][] = $m['codice'];
                    continue;
                }
                $nodi[$id] = array_merge($n, [
                    'id' => $id,
                    'livello' => self::livello($n),
                    'stato' => self::stato($n['stato'] ?? null),
                    'condiviso' => $condiviso,
                    'codice' => $m['codice'],
                    'macchine' => [$m['codice']],
                    'nome' => $condiviso ? (string) ($n['nome'] ?? $orig) : trim($m['num'].' · '.($n['nome'] ?? $orig)),
                ]);
            }
            foreach ($g['collegamenti'] as $i => $k) {
                $da = $mappaId[(string) ($k['da'] ?? '')] ?? null;
                $a = $mappaId[(string) ($k['a'] ?? '')] ?? null;
                if ($da === null || $a === null) {
                    continue;
                }
                $elenco[] = array_merge($k, ['id' => $m['codice'].':'.($k['id'] ?? 'k'.$i), 'da' => $da, 'a' => $a, 'codice' => $m['codice'], 'macchinaNum' => $m['num']]);
            }
        }
        if ($nodi === []) {
            return ['nodi' => [], 'collegamenti' => [], 'livelli' => [], 'altezza' => 0, 'larghezza' => self::LARGHEZZA, 'colonne' => []];
        }

        $nColonne = max(1, count($colonne));
        $larghezza = max(self::LARGHEZZA, $nColonne * $COL + 40);
        $xColonna = [];
        foreach ($colonne as $c => $m) {
            $xColonna[$m['codice']] = (int) round(20 + $c * $COL + ($COL - self::NODO_L) / 2);
        }

        $usati = array_values(array_unique(array_map(fn ($n) => $n['livello'], $nodi)));
        sort($usati);
        $livelli = [];
        $y = self::MARGINE_Y;
        foreach ($usati as $l) {
            $inLivello = array_filter($nodi, fn ($n) => $n['livello'] === $l);
            $condivisi = array_values(array_filter($inLivello, fn ($n) => $n['condiviso']));
            $propri = array_values(array_filter($inLivello, fn ($n) => ! $n['condiviso']));
            $righe = 0;
            if ($condivisi) {
                $quanti = count($condivisi);
                foreach ($condivisi as $j => $n) {
                    $cx = $larghezza * ($j + 0.5) / $quanti;
                    $nodi[$n['id']] = array_merge($nodi[$n['id']], ['x' => (int) round($cx - self::NODO_L / 2), 'y' => $y, 'w' => self::NODO_L, 'h' => self::NODO_H]);
                }
                $righe = 1;
            }
            $perMacchina = [];
            foreach ($propri as $n) {
                $r = $perMacchina[$n['codice']] = ($perMacchina[$n['codice']] ?? -1) + 1;
                $nodi[$n['id']] = array_merge($nodi[$n['id']], ['x' => $xColonna[$n['codice']], 'y' => $y + ($righe + $r) * $RIGA, 'w' => self::NODO_L, 'h' => self::NODO_H]);
            }
            $righe += $perMacchina ? max($perMacchina) + 1 : 0;
            $alto = ($righe - 1) * $RIGA + self::NODO_H + 40;
            $livelli[] = ['nome' => self::LIVELLI[$l], 'y' => $y, 'h' => $alto];
            $y += $alto + (self::PASSO_Y - self::NODO_H - 40);
        }

        $fili = $this->fili($nodi, $elenco);

        return [
            'nodi' => array_values($nodi),
            'collegamenti' => $fili,
            'livelli' => $livelli,
            'altezza' => $y - (self::PASSO_Y - self::NODO_H - 40) + 20, // sotto: il nome di ogni colonna
            'larghezza' => $larghezza,
            'colonne' => array_map(fn ($m) => ['codice' => $m['codice'], 'num' => $m['num'], 'nome' => $m['nome'], 'x' => $xColonna[$m['codice']]], $colonne),
        ];
    }

    /** Curva tra due nodi: verticale tra livelli diversi, ad arco sopra i nodi sullo stesso livello. */
    private function tracciato(array $da, array $a, float $scarto): array
    {
        $cxDa = $da['x'] + $da['w'] / 2;
        $cxA = $a['x'] + $a['w'] / 2;

        if ($da['y'] === $a['y']) {
            $destra = $cxA > $cxDa;
            $x1 = $destra ? $da['x'] + $da['w'] : $da['x'];
            $x2 = $destra ? $a['x'] : $a['x'] + $a['w'];
            $y = $da['y'] + $da['h'] / 2 + $scarto * 0.6;
            $alto = $y - 46 - abs($scarto);
            if (abs($x2 - $x1) < 40) { // nodi vicini: arco piu' largo
                $alto -= 30;
            }

            return $this->curva([$x1, $y], [$x1 + ($destra ? 30 : -30), $alto], [$x2 + ($destra ? -30 : 30), $alto], [$x2, $y]);
        }

        // ogni filo esce dal lato del nodo rivolto verso l'altro: fili diversi non partono dallo stesso punto
        $giu = $a['y'] > $da['y'];
        $limite = max(0, $da['w'] / 2 - 18 - abs($scarto));
        $dx = $cxA - $cxDa;
        $x1 = $cxDa + max(-$limite, min($limite, $dx * 0.35)) + $scarto;
        $x2 = $cxA + max(-$limite, min($limite, -$dx * 0.35)) + $scarto;
        $y1 = $giu ? $da['y'] + $da['h'] : $da['y'];
        $y2 = $giu ? $a['y'] : $a['y'] + $a['h'];
        $my = ($y1 + $y2) / 2;

        return $this->curva([$x1, $y1], [$x1, $my], [$x2, $my], [$x2, $y2]);
    }

    /** Curva di Bezier cubica: il tracciato SVG e i suoi quattro punti. */
    private function curva(array ...$p): array
    {
        return [sprintf('M%.0f %.0f C%.0f %.0f %.0f %.0f %.0f %.0f', ...array_merge(...$p)), $p];
    }

    /**
     * Posto del tondino col numero: a meta' curva, oppure piu' avanti o indietro sulla stessa
     * curva se li' c'e' gia' il numero di un altro collegamento (fili che si incrociano).
     */
    private function etichetta(array $p, array &$occupati): array
    {
        $punto = function (float $t) use ($p) {
            $u = 1 - $t;
            $c = [$u ** 3, 3 * $u * $u * $t, 3 * $u * $t * $t, $t ** 3];

            return [$c[0] * $p[0][0] + $c[1] * $p[1][0] + $c[2] * $p[2][0] + $c[3] * $p[3][0],
                $c[0] * $p[0][1] + $c[1] * $p[1][1] + $c[2] * $p[2][1] + $c[3] * $p[3][1]];
        };
        $scelto = $punto(0.5);
        foreach ([0.5, 0.35, 0.65, 0.25, 0.75, 0.2, 0.8] as $t) {
            [$x, $y] = $punto($t);
            $libero = true;
            foreach ($occupati as [$ox, $oy]) {
                if (abs($x - $ox) < 28 && abs($y - $oy) < 28) {
                    $libero = false;
                    break;
                }
            }
            if ($libero) {
                $scelto = [$x, $y];
                break;
            }
        }
        $occupati[] = $scelto;

        return $scelto;
    }
}
