<?php

namespace App\Services;

use App\Models\Compito;
use App\Models\Machine;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * "Sullo SCADA" (Francesco, 16/10/2026): quello che si e' visto dentro lo SCADA Edica per ogni
 * macchina (connessione, variabili con valori, storico, pagine, schermate). Dati: campo `scada`
 * della macchina nel file del PC (salvato in `machines.dati`). Le schermate sono file caricati dal
 * PC con POST /api/schermate in storage (disco local) `schermate/{slug}/{codice}/{file}`.
 */
class Scada
{
    /** stato => [etichetta, classe colore] */
    public const STATI = [
        'vivo' => ['dati vivi', 'sc-vivo'],
        'collegato' => ['collegata, dati incompleti', 'sc-collegato'],
        'preparato' => ['tag preparati, vuoti', 'sc-preparato'],
        'nonrisponde' => ['non risponde', 'sc-nonrisponde'],
        'assente' => ['nessuna connessione', 'sc-assente'],
    ];

    /** Etichetta breve per il quadro (colonna "Su SCADA"). */
    public const BREVI = ['vivo' => 'vivo', 'collegato' => 'collegato', 'preparato' => 'preparato', 'nonrisponde' => 'non risponde', 'assente' => 'assente'];

    public const ESTENSIONI = ['jpg', 'jpeg', 'png', 'webp'];

    /** Oltre questi giorni dalla visita i dati SCADA sono "da rivedere alla prossima visita" (grigi). */
    public const GIORNI_VALIDI = 7;

    public static function cartella(string $slug, string $codice): string
    {
        return 'schermate/'.$slug.'/'.$codice;
    }

    /** Nome file sicuro: solo [A-Za-z0-9._-], niente "..", estensione immagine; null se non valido. */
    public static function nomeFile(string $nome): ?string
    {
        $nome = preg_replace('/[^A-Za-z0-9._-]/', '_', basename(str_replace('\\', '/', $nome)));
        $nome = ltrim((string) $nome, '.');
        if ($nome === '' || str_contains($nome, '..') || strlen($nome) > 120) {
            return null;
        }

        return in_array(strtolower(pathinfo($nome, PATHINFO_EXTENSION)), self::ESTENSIONI, true) ? $nome : null;
    }

    /** I file presenti sul server per un codice (macchina o sistema). */
    public function presenti(string $slug, string $codice): array
    {
        $disco = Storage::disk('local');

        return collect($disco->files(self::cartella($slug, $codice)))->map(fn ($p) => basename($p))->sort()->values()->all();
    }

    /** Elenco delle schermate {file,titolo,data,url,presente}, dal campo del file e dai file sul server. */
    public function schermate(Project $progetto, string $codice, array $voci): array
    {
        $presenti = $this->presenti($progetto->slug, $codice);
        $out = [];
        foreach ($voci as $v) {
            if (! is_array($v) || ! is_string($v['file'] ?? null)) {
                continue;
            }
            $file = self::nomeFile($v['file']);
            if ($file === null) {
                continue;
            }
            $out[] = [
                'file' => $file,
                'titolo' => (string) ($v['titolo'] ?? $file),
                'data' => (string) ($v['data'] ?? ''),
                'presente' => in_array($file, $presenti, true),
                'url' => route('schermate.file', [$progetto->slug, $codice, $file]),
            ];
        }

        return $out;
    }

    /** Il campo `scada` normalizzato, o null se la macchina non ce l'ha. */
    public function scheda(Project $progetto, Machine $macchina): ?array
    {
        $s = $macchina->dato('scada');
        if (! is_array($s) || $s === []) {
            return null;
        }
        $stato = array_key_exists($s['stato'] ?? '', self::STATI) ? $s['stato'] : 'assente';
        $testo = fn ($k) => is_scalar($s[$k] ?? null) ? (string) $s[$k] : '';

        $variabili = [];
        foreach ((array) ($s['variabili'] ?? []) as $v) {
            if (! is_array($v) || ! is_scalar($v['nome'] ?? null)) {
                continue;
            }
            $q = is_scalar($v['qualita'] ?? null) ? (string) $v['qualita'] : '';
            $variabili[] = [
                'nome' => (string) $v['nome'],
                'valore' => is_scalar($v['valore'] ?? null) ? (string) $v['valore'] : '',
                'tipo' => is_scalar($v['tipo'] ?? null) ? (string) $v['tipo'] : '',
                'accesso' => is_scalar($v['accesso'] ?? null) ? (string) $v['accesso'] : '',
                'qualita' => $q,
                'buona' => $q === '' || stripos($q, 'good') === 0,
                'nota' => is_scalar($v['nota'] ?? null) ? (string) $v['nota'] : '',
            ];
        }

        $pagine = [];
        foreach ((array) ($s['pagine'] ?? []) as $p) {
            if (is_string($p)) {
                $p = ['nome' => $p];
            }
            if (is_array($p) && is_scalar($p['nome'] ?? null)) {
                $pagine[] = ['nome' => (string) $p['nome'], 'data' => is_scalar($p['data'] ?? null) ? (string) $p['data'] : '', 'nota' => is_scalar($p['nota'] ?? null) ? (string) $p['nota'] : ''];
            }
        }

        return [
            'visto' => $testo('visto'),
            'giorni' => self::giorni($testo('visto')),
            'vecchio' => self::vecchio($testo('visto')),
            'fonte' => $testo('fonte'),
            'stato' => $stato,
            'etichetta' => self::STATI[$stato][0],
            'classe' => self::STATI[$stato][1],
            'connessione' => $testo('connessione'),
            'sintesi' => $testo('sintesi'),
            'variabili' => $variabili,
            'storico' => $testo('storico'),
            'pagine' => $pagine,
            'schermate' => $this->schermate($progetto, $macchina->codice, (array) ($s['schermate'] ?? [])),
            'domande' => array_values(array_filter(array_map(fn ($d) => is_scalar($d) ? (string) $d : '', (array) ($s['domande'] ?? [])))),
        ];
    }

    /** Giorni passati dalla visita (data AAAA-MM-GG), o null se la data non c'e'. */
    public static function giorni(?string $data): ?int
    {
        if (! is_string($data) || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $data)) {
            return null;
        }

        return (int) Carbon::parse(substr($data, 0, 10))->startOfDay()->diffInDays(today(), false);
    }

    /** Vero se la visita e' piu' vecchia di GIORNI_VALIDI giorni. */
    public static function vecchio(?string $data): bool
    {
        $g = self::giorni($data);

        return $g !== null && $g > self::GIORNI_VALIDI;
    }

    /** Lo stato SCADA della macchina (vivo, collegato, ...) se ha il campo `scada`, altrimenti null. */
    public static function statoDi(Machine $m): ?string
    {
        $s = $m->dato('scada');
        if (! is_array($s) || $s === []) {
            return null;
        }

        return isset(self::STATI[$s['stato'] ?? '']) ? $s['stato'] : 'assente';
    }

    /** "oggi", "ieri", "N giorni fa". */
    public static function fa(int $giorni): string
    {
        return match (true) {
            $giorni <= 0 => 'oggi',
            $giorni === 1 => 'ieri',
            default => $giorni.' giorni fa',
        };
    }

    /**
     * Domande nate dallo SCADA: `scada.domande` di ogni macchina e i compiti proposti/aperti la cui
     * fonte o testo cita lo SCADA visto da dentro.
     *
     * @return array{domande: array<int, array{num: string, nome: string, codice: string, testo: string}>, compiti: \Illuminate\Support\Collection}
     */
    public function domande(Project $progetto): array
    {
        $domande = [];
        foreach ($progetto->machines->reject(fn ($m) => $m->dato('escludiDaiConteggi')) as $m) {
            $s = $m->dato('scada');
            foreach ((array) (is_array($s) ? ($s['domande'] ?? []) : []) as $d) {
                if (is_scalar($d) && trim((string) $d) !== '') {
                    $domande[] = ['num' => (string) $m->dato('num', $m->codice), 'nome' => (string) $m->dato('nome', ''), 'codice' => $m->codice, 'testo' => (string) $d];
                }
            }
        }
        $compiti = $progetto->compiti()->whereIn('stato', ['proposto', 'aperto'])->orderBy('id')->get()
            ->filter(fn (Compito $c) => stripos((string) $c->fonte, 'SCADA visto da dentro') !== false || stripos((string) $c->testo, 'SCADA visto da dentro') !== false)
            ->values();

        return ['domande' => $domande, 'compiti' => $compiti];
    }

    /** "16/10" dalla data della visita (AAAA-MM-GG), o il testo com'e'. */
    public static function giornoMese(string $data): string
    {
        return preg_match('/^\d{4}-(\d{2})-(\d{2})/', $data, $m) ? $m[2].'/'.$m[1] : $data;
    }
}
