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

    /** Livelli della mappa, dall'alto. */
    public const LIVELLI = [
        0 => 'Gestionale e logistica',
        1 => 'Supervisione (SCADA, cloud)',
        2 => 'Controllo macchina (PLC, HMI)',
        3 => 'Campo e persone',
    ];

    private const LIVELLO_TIPO = ['gestionale' => 0, 'scada' => 1, 'cloud' => 1, 'plc' => 2, 'hmi' => 2, 'macchina' => 3, 'sensore' => 3, 'persona' => 3];

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
        ];
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
            $livello = isset($n['livello']) && is_numeric($n['livello']) ? max(0, min(3, (int) $n['livello'])) : (self::LIVELLO_TIPO[$n['tipo'] ?? ''] ?? 2);
            $nodi[$id] = array_merge($n, ['id' => $id, 'livello' => $livello, 'stato' => self::stato($n['stato'] ?? null)]);
        }
        if ($nodi === []) {
            return ['nodi' => [], 'collegamenti' => [], 'livelli' => [], 'altezza' => 0];
        }

        // solo i livelli usati, uno sotto l'altro
        $usati = array_values(array_unique(array_map(fn ($n) => $n['livello'], $nodi)));
        sort($usati);
        $livelli = [];
        foreach ($usati as $riga => $l) {
            $inLivello = array_values(array_filter($nodi, fn ($n) => $n['livello'] === $l));
            $y = self::MARGINE_Y + $riga * self::PASSO_Y;
            $livelli[] = ['nome' => self::LIVELLI[$l], 'y' => $y];
            $quanti = count($inLivello);
            foreach ($inLivello as $j => $n) {
                $cx = self::LARGHEZZA * ($j + 0.5) / $quanti;
                $nodi[$n['id']]['x'] = (int) round($cx - self::NODO_L / 2);
                $nodi[$n['id']]['y'] = $y;
                $nodi[$n['id']]['w'] = self::NODO_L;
                $nodi[$n['id']]['h'] = self::NODO_H;
            }
        }

        // collegamenti: piu' collegamenti tra gli stessi due nodi si allargano a ventaglio
        $coppie = [];
        $collegamenti = [];
        foreach ($progetto['collegamenti'] as $i => $k) {
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
        foreach ($collegamenti as $num => $c) {
            $k = $c['k'];
            [$da, $a] = [$c['da'], $c['a']];
            $chiave = $da['id'] < $a['id'] ? $da['id'].'|'.$a['id'] : $a['id'].'|'.$da['id'];
            $n = $totali[$chiave] + 1;
            $scarto = ($c['indice'] - ($n - 1) / 2) * 26;
            [$d, $lx, $ly] = $this->tracciato($da, $a, $scarto);
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
            ]);
        }

        return [
            'nodi' => array_values($nodi),
            'collegamenti' => $out,
            'livelli' => $livelli,
            'altezza' => self::MARGINE_Y + (count($usati) - 1) * self::PASSO_Y + self::NODO_H + 40,
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
            $mx = ($x1 + $x2) / 2;
            if (abs($x2 - $x1) < 40) { // nodi vicini: arco piu' largo
                $alto -= 30;
            }

            return [sprintf('M%.0f %.0f C%.0f %.0f %.0f %.0f %.0f %.0f', $x1, $y, $x1 + ($destra ? 30 : -30), $alto, $x2 + ($destra ? -30 : 30), $alto, $x2, $y), $mx, $alto + 12];
        }

        $giu = $a['y'] > $da['y'];
        $x1 = $cxDa + $scarto;
        $x2 = $cxA + $scarto;
        $y1 = $giu ? $da['y'] + $da['h'] : $da['y'];
        $y2 = $giu ? $a['y'] : $a['y'] + $a['h'];
        $my = ($y1 + $y2) / 2;

        return [sprintf('M%.0f %.0f C%.0f %.0f %.0f %.0f %.0f %.0f', $x1, $y1, $x1, $my, $x2, $my, $x2, $y2), ($x1 + $x2) / 2, $my];
    }
}
