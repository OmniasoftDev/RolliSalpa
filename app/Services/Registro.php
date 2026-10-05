<?php

namespace App\Services;

use App\Models\Controllo;
use App\Models\MailRegistro;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Registro dei controlli orari e delle mail di progetto, con la quadratura PC <-> server.
 *
 * A ogni controllo il PC manda: i controlli non ancora confermati, le mail nuove o cambiate
 * e l'elenco completo dei codici delle mail nella finestra (ultimi 14 giorni) con la sua impronta.
 * Il server registra, ricalcola l'impronta sui codici che ha nella stessa finestra e risponde
 * se i due insiemi coincidono: impronta = SHA-256 dei codici ordinati, uniti da "\n".
 */
class Registro
{
    /** Ore in cui l'attivita' pianificata gira (lun-ven, ogni 5 minuti dalle 8 alle 20): ogni ora deve avere controlli. */
    public const ORARI = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00'];

    /** Minuti tra un controllo e l'altro: un'ora senza controlli dopo questo margine e' "mancante". */
    public const MARGINE_MINUTI = 15;

    /**
     * Il PC dovrebbe aver appena controllato? Lun-ven tra il primo controllo delle 8:00 e l'ultimo delle 20:00,
     * piu' il margine: fuori da qui (sera, notte, fine settimana) il silenzio e' normale, non un "fermo".
     */
    public static function inOrario(\Carbon\CarbonInterface $t): bool
    {
        $primo = $t->copy()->setTimeFromTimeString(self::ORARI[0])->addMinutes(self::MARGINE_MINUTI);
        $ultimo = $t->copy()->setTimeFromTimeString(self::ORARI[count(self::ORARI) - 1])->addMinutes(self::MARGINE_MINUTI);

        return $t->isWeekday() && $t->gte($primo) && $t->lte($ultimo);
    }

    public static function impronta(array $codici): string
    {
        $codici = array_values(array_unique(array_map('strval', $codici)));
        sort($codici, SORT_STRING);

        return hash('sha256', implode("\n", $codici));
    }

    public function registra(array $dati): array
    {
        $controlli = $dati['controlli'] ?? [];
        $mail = $dati['mail'] ?? [];
        $finestra = $dati['finestra'] ?? null;
        if (! is_array($controlli) || ! is_array($mail) || ! is_array($finestra)
            || ! is_string($finestra['dal'] ?? null) || ! is_array($finestra['codici'] ?? null)) {
            throw new InvalidArgumentException('Servono "controlli", "mail" e "finestra" {dal, codici, firma}.');
        }
        $dal = $this->data($finestra['dal']);
        $codiciPc = array_values(array_unique(array_map('strval', $finestra['codici'])));
        $firmaPc = (string) ($finestra['firma'] ?? '');
        // l'impronta del PC deve corrispondere ai codici che ha mandato: altrimenti il messaggio e' arrivato rovinato
        if (! hash_equals(self::impronta($codiciPc), $firmaPc)) {
            throw new InvalidArgumentException('Impronta della finestra non coerente con i codici inviati.');
        }

        return DB::transaction(function () use ($controlli, $mail, $dal, $codiciPc, $firmaPc) {
            foreach ($mail as $m) {
                if (! is_array($m) || ! is_string($m['codice'] ?? null) || ! is_string($m['ricevuta'] ?? null)) {
                    throw new InvalidArgumentException('Ogni mail deve avere "codice" e "ricevuta".');
                }
                $campi = [
                    'ricevuta' => $this->data($m['ricevuta']),
                    'cartella' => $this->corto($m['cartella'] ?? null, 120),
                    'da' => $this->corto($m['da'] ?? null, 255),
                    'a' => $this->corto($m['a'] ?? null, 5000),
                    'cc' => $this->corto($m['cc'] ?? null, 5000),
                    'oggetto' => $this->corto($m['oggetto'] ?? null, 500),
                    'allegati' => array_values(array_map('strval', (array) ($m['allegati'] ?? []))),
                    'inviata' => (bool) ($m['inviata'] ?? false),
                    'elaborata_at' => isset($m['elaborata']) && is_string($m['elaborata']) ? $this->data($m['elaborata']) : null,
                ];
                // il testo si manda solo la prima volta: gli aggiornamenti successivi (es. "elaborata") non lo cancellano
                if (array_key_exists('testo', $m)) {
                    $campi['testo'] = $this->corto($m['testo'], 60000);
                }
                $esistente = MailRegistro::where('codice', $m['codice'])->first();
                if ($esistente) {
                    $esistente->update($campi);
                } else {
                    // vista_at nasce sul web e non si tocca
                    MailRegistro::create($campi + ['codice' => $m['codice'], 'trovata_controllo' => $this->corto($m['trovata'] ?? null, 19)]);
                }
            }

            $codiciServer = MailRegistro::where('ricevuta', '>=', $dal)->pluck('codice')->all();
            $mancanti = array_values(array_diff($codiciPc, $codiciServer));
            $soloServer = array_values(array_diff($codiciServer, $codiciPc));
            $firmaServer = self::impronta($codiciServer);
            $quadra = $mancanti === [] && $soloServer === [] && hash_equals($firmaServer, $firmaPc);

            foreach ($controlli as $c) {
                if (! is_array($c) || ! is_string($c['codice'] ?? null) || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $c['codice'])) {
                    throw new InvalidArgumentException('Ogni controllo deve avere "codice" nel formato aaaa-mm-gg hh:mm:ss.');
                }
                Controllo::updateOrCreate(['codice' => $c['codice']], [
                    'inizio' => $this->data($c['inizio'] ?? $c['codice']),
                    'fine' => isset($c['fine']) ? $this->data($c['fine']) : null,
                    'esito' => in_array($c['esito'] ?? null, ['ok', 'avvisi', 'errore'], true) ? $c['esito'] : 'avvisi',
                    'outlook' => $this->corto($c['outlook'] ?? null, 200),
                    'mail_finestra' => (int) ($c['mailFinestra'] ?? 0),
                    'mail_nuove' => (int) ($c['mailNuove'] ?? 0),
                    'appunti' => (int) ($c['appunti'] ?? 0),
                    'passi' => array_values(array_filter((array) ($c['passi'] ?? []), 'is_array')),
                    'firma_pc' => $this->corto($c['firma'] ?? null, 64),
                ]);
            }
            // la quadratura appena fatta vale per il controllo piu' recente del pacchetto
            $ultimo = Controllo::whereIn('codice', array_column(array_filter($controlli, 'is_array'), 'codice'))->orderByDesc('codice')->first();
            $ultimo?->update(['firma_server' => $firmaServer, 'quadra' => $quadra]);

            return [
                'controlli' => count($controlli),
                'mail' => count($mail),
                'conteggio' => count($codiciServer),
                'firma' => $firmaServer,
                'quadra' => $quadra,
                'mancanti' => $mancanti,
                'soloServer' => $soloServer,
            ];
        });
    }

    /**
     * Griglia delle ore attese negli ultimi $giorni giorni lavorativi, con i controlli partiti in ciascuna.
     * Stato dell'ora: decide l'ultimo controllo dell'ora (errore o quadratura non riuscita = "errore");
     * se era a posto ma prima nell'ora qualcosa e' andato storto, "avvisi".
     *
     * @return Collection<int, array{giorno: Carbon, orari: array}>
     */
    public function griglia(int $giorni = 7, ?Carbon $adesso = null): Collection
    {
        $adesso ??= now();
        $primo = Controllo::min('inizio');
        $inizioRegistro = $primo ? Carbon::parse($primo) : null;
        $controlli = Controllo::where('inizio', '>=', $adesso->copy()->subDays($giorni + 1)->startOfDay())->orderBy('inizio')->get();

        $righe = collect();
        for ($d = 0; $righe->count() < $giorni && $d < $giorni * 2; $d++) {
            $giorno = $adesso->copy()->subDays($d)->startOfDay();
            if (! $giorno->isWeekday()) {
                continue;
            }
            $orari = [];
            foreach (self::ORARI as $i => $hhmm) {
                $da = $giorno->copy()->setTimeFromTimeString($hhmm);
                $a = isset(self::ORARI[$i + 1]) ? $giorno->copy()->setTimeFromTimeString(self::ORARI[$i + 1]) : $giorno->copy()->endOfDay();
                $nellOra = $controlli->filter(fn ($c) => $c->inizio->gte($da) && $c->inizio->lt($a))->values();
                $male = fn ($c) => $c->esito === 'errore' || $c->quadra === false;
                $c = $nellOra->last();
                if ($c) {
                    $stato = $male($c) ? 'errore' : ($nellOra->contains($male) || $c->esito === 'avvisi' ? 'avvisi' : 'ok');
                } elseif ($da->gt($adesso)) {
                    $stato = 'futuro';
                } elseif (! $inizioRegistro || $da->lt($inizioRegistro->copy()->startOfHour())) {
                    $stato = 'prima';
                } elseif ($da->gt($adesso->copy()->subMinutes(self::MARGINE_MINUTI))) {
                    $stato = 'atteso';
                } else {
                    $stato = 'manca';
                }
                $orari[] = ['ora' => $hhmm, 'stato' => $stato, 'controllo' => $c, 'numero' => $nellOra->count()];
            }
            $righe->push(['giorno' => $giorno, 'orari' => $orari]);
        }

        return $righe;
    }

    /** Problemi da mostrare in cima: vuoto = tutto in regola. */
    public function problemi(?Carbon $adesso = null): array
    {
        $adesso ??= now();
        $problemi = [];
        $ultimo = Controllo::orderByDesc('inizio')->first();
        if (! $ultimo) {
            return ['Nessun controllo registrato: il PC non ha ancora mandato il registro.'];
        }
        $mancanti = $this->griglia(7, $adesso)->flatMap(fn ($r) => collect($r['orari'])->where('stato', 'manca')
            ->map(fn ($o) => $r['giorno']->format('d/m').' '.$o['ora']));
        if ($mancanti->isNotEmpty()) {
            $problemi[] = 'Ore senza nessun controllo negli ultimi 7 giorni lavorativi ('.$mancanti->count().'): '.$mancanti->take(8)->join(', ')
                .($mancanti->count() > 8 ? '…' : '').'. PC spento o attività pianificata ferma: le mail di quelle ore sono state lette al controllo successivo.';
        }
        if ($ultimo->inizio->lt($adesso->copy()->subMinutes(self::MARGINE_MINUTI)) && self::inOrario($adesso)) {
            $problemi[] = 'Nessun controllo da '.$ultimo->inizio->diffForHumans($adesso, true).' (ultimo '.$ultimo->inizio->format('d/m H:i').'): il sito non è aggiornato. PC spento o Outlook chiuso?';
        }
        if ($ultimo->quadra === false) {
            $problemi[] = 'L\'ultimo controllo ('.$ultimo->inizio->format('d/m H:i').') non quadra: mail del PC e del sito non coincidono.';
        } elseif ($ultimo->quadra === null) {
            $problemi[] = 'L\'ultimo controllo ('.$ultimo->inizio->format('d/m H:i').') non ha la quadratura delle mail.';
        }
        if ($ultimo->esito === 'errore') {
            $problemi[] = 'L\'ultimo controllo ('.$ultimo->inizio->format('d/m H:i').') è finito con un errore: vedi i passi qui sotto.';
        }
        if ($ultimo->outlook && preg_match('/offline|non trovat|errore/i', $ultimo->outlook)) {
            $problemi[] = 'Outlook all\'ultimo controllo: '.$ultimo->outlook.'.';
        }

        return $problemi;
    }

    private function data(string $s): string
    {
        try {
            return Carbon::parse($s)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            throw new InvalidArgumentException("Data non valida: {$s}");
        }
    }

    private function corto(mixed $v, int $max): ?string
    {
        return $v === null ? null : mb_substr((string) $v, 0, $max);
    }
}
