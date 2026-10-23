<?php

namespace App\Services;

use App\Models\Project;

/**
 * Area pubblica (23/10/2026): prima di mostrare una stringa dei dati tecnici si tolgono le citazioni
 * tra quadre ("[Mail 09/10 Uberti]", fonti scritte dentro il testo) e i nomi delle persone della
 * rubrica del progetto diventano il nome della loro azienda: "Nome Cognome", "Ing. Cognome" e il
 * cognome da solo, senza distinguere maiuscole, a parola intera. I cognomi che sono anche parole
 * comuni (es. Voce, Voi) si sostituiscono solo con l'iniziale maiuscola.
 */
class Anonimizza
{
    private const TITOLI = ['ing', 'dott', 'dr', 'geom', 'arch', 'sig', 'sig.ra', 'per. ind', 'p.i'];

    private const PARTICELLE = ['de', 'di', 'da', 'dal', 'del', 'della', 'dalla', 'la', 'lo', 'van', 'von'];

    /** Cognomi che sono anche parole comuni: solo con l'iniziale maiuscola. */
    private const PAROLE_COMUNI = ['voce', 'voi', 'neve', 'rosa', 'sole', 'mare', 'monte', 'conte', 'bianco', 'nero', 'rossi', 'fiore', 'sale', 'porta', 'serra'];

    /** @var array<int, array{0: string, 1: string}> [regex, azienda], dal nome piu' lungo */
    private array $regole = [];

    public function __construct(iterable $persone)
    {
        $voci = [];
        foreach ($persone as $p) {
            $nome = is_array($p) ? ($p['nome'] ?? '') : ($p->nome ?? '');
            $azienda = self::azienda((string) (is_array($p) ? ($p['azienda'] ?? '') : ($p->azienda ?? '')));
            if (trim((string) $nome) === '' || $azienda === '') {
                continue;
            }
            foreach (self::candidati((string) $nome) as [$titolo, $parole]) {
                $cognome = self::cognome($parole);
                if ($cognome === null || mb_strlen(str_replace(' ', '', $cognome)) < 3
                    || mb_stripos($azienda, $cognome) !== false) {
                    continue;
                }
                $intero = implode(' ', $parole);
                $forme = [$intero];
                if ($titolo !== '') {
                    $forme[] = $titolo.' '.$intero;
                    $forme[] = $titolo.' '.$cognome;
                }
                foreach ($forme as $f) {
                    $voci[mb_strtolower($f)] = [$f, $azienda, false];
                }
                $voci[mb_strtolower($cognome)] ??= [$cognome, $azienda, in_array(mb_strtolower($cognome), self::PAROLE_COMUNI, true)];
            }
        }
        uksort($voci, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach ($voci as [$forma, $azienda, $soloMaiuscola]) {
            $corpo = str_replace(' ', '\s+', preg_quote($forma, '/'));
            $corpo = str_replace('\.\s+', '\.\s*', $corpo);
            $this->regole[] = ['/(?<![\p{L}\p{N}])'.$corpo.'(?![\p{L}\p{N}])/u'.($soloMaiuscola ? '' : 'i'), $azienda];
        }
    }

    public static function perProgetto(Project $progetto): self
    {
        return new self($progetto->persone()->get(['nome', 'azienda']));
    }

    /** Testo pulito: senza citazioni tra quadre e senza nomi di persone. */
    public function testo(mixed $v): string
    {
        if (is_array($v)) {
            $v = implode(', ', array_filter(array_map(fn ($x) => is_scalar($x) ? (string) $x : '', $v), fn ($x) => $x !== ''));
        }
        if (! is_scalar($v)) {
            return '';
        }
        $t = preg_replace('/\s*\[[^\]]*\]/u', '', (string) $v);
        foreach ($this->regole as [$re, $azienda]) {
            $t = preg_replace($re, $azienda, $t);
        }

        return trim(preg_replace('/[ \t]{2,}/', ' ', (string) $t));
    }

    /** "Edica (programmatore SCADA) [I]" -> "Edica" */
    private static function azienda(string $a): string
    {
        return trim(preg_replace('/\s*[\(\[].*$/u', '', $a));
    }

    /**
     * Nomi contenuti nel campo `nome`: "Ing. Marco Romano" -> [["Ing.", [Marco, Romano]]];
     * "CFT (F. Dioni, M. Gonizzi)" -> il testo fuori parentesi e i nomi in parentesi.
     * Le voci con "referente" o "da chiedere" non sono persone.
     *
     * @return array<int, array{0: string, 1: array<int, string>}>
     */
    private static function candidati(string $nome): array
    {
        $pezzi = [];
        if (preg_match('/^(.*?)\((.*)\)/u', $nome, $m)) {
            $pezzi[] = $m[1];
            foreach (explode(',', $m[2]) as $x) {
                $pezzi[] = $x;
            }
        } else {
            $pezzi[] = $nome;
        }
        $out = [];
        foreach ($pezzi as $x) {
            $x = trim(preg_replace('/\s*\[[^\]]*\]/u', '', $x));
            if ($x === '' || preg_match('/referente|da chiedere|\//iu', $x)) {
                continue;
            }
            $parole = preg_split('/\s+/u', $x);
            $titolo = '';
            if (count($parole) > 1 && in_array(mb_strtolower(rtrim($parole[0], '.')), self::TITOLI, true)) {
                $titolo = array_shift($parole);
            }
            $out[] = [$titolo, $parole];
        }

        return $out;
    }

    /** Ultima parola, con le particelle davanti ("Di Carmine", "Dal Zotto", "De Amicis"). */
    private static function cognome(array $parole): ?string
    {
        $n = count($parole);
        if ($n === 0) {
            return null;
        }
        $c = [$parole[$n - 1]];
        for ($i = $n - 2; $i >= 1; $i--) {
            if (! in_array(mb_strtolower($parole[$i]), self::PARTICELLE, true)) {
                break;
            }
            array_unshift($c, $parole[$i]);
        }
        $cognome = implode(' ', $c);

        return preg_match('/^\p{Lu}/u', $cognome) ? $cognome : null;
    }
}
