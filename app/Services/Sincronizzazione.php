<?php

namespace App\Services;

use App\Models\Compito;
use App\Models\Decisione;
use App\Models\Event;
use App\Models\Machine;
use App\Models\Persona;
use App\Models\Project;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Applica un file di progetto (pannello/<slug>.json sul PC di Francesco) al database.
 *
 * Il file e' la fotografia completa del progetto: macchine, domande ed eventi che non
 * compaiono piu' vengono tolti. Unica eccezione: una domanda spuntata o tolta dal
 * browser (fatto_web) mantiene lo stato scelto sul web, finche' il PC non lo riprende
 * tramite /api/spunte.
 */
class Sincronizzazione
{
    public function applica(array $dati): array
    {
        $slug = (string) ($dati['progetto'] ?? '');
        if (! preg_match('/^[a-z0-9-]{2,30}$/', $slug)) {
            throw new InvalidArgumentException('Campo "progetto" mancante o non valido.');
        }
        $macchine = $dati['macchine'] ?? [];
        $eventi = $dati['eventi'] ?? [];
        if (! is_array($macchine) || ! is_array($eventi)) {
            throw new InvalidArgumentException('"macchine" ed "eventi" devono essere elenchi.');
        }

        return DB::transaction(function () use ($dati, $slug, $macchine, $eventi) {
            $progetto = Project::updateOrCreate(['slug' => $slug], [
                'nome' => (string) ($dati['nome'] ?? ucfirst($slug)),
                'ordine' => (int) ($dati['ordine'] ?? 0),
                'info' => is_array($dati['info'] ?? null) ? $dati['info'] : [],
                'fasi' => is_array($dati['fasi'] ?? null) ? array_values($dati['fasi']) : [],
                'synced_at' => now(),
            ]);

            $codiciMacchine = [];
            $domande = 0;
            foreach (array_values($macchine) as $i => $m) {
                $codice = $this->codice($m['codice'] ?? null, 'macchina');
                $codiciMacchine[] = $codice;
                $chiedere = is_array($m['chiedere'] ?? null) ? $m['chiedere'] : [];
                unset($m['codice'], $m['chiedere']);

                $macchina = Machine::updateOrCreate(
                    ['project_id' => $progetto->id, 'codice' => $codice],
                    ['ordine' => (int) ($m['ordine'] ?? $i), 'dati' => $m],
                );
                $domande += $this->domande($macchina, $chiedere);
            }
            $progetto->machines()->whereNotIn('codice', $codiciMacchine)->delete();

            $codiciEventi = [];
            foreach ($eventi as $e) {
                $codice = $this->codice($e['id'] ?? null, 'evento');
                $codiciEventi[] = $codice;
                $chiave = (string) ($e['chiave'] ?? trim(($e['data'] ?? '').' '.($e['ora'] ?? '')));
                Event::updateOrCreate(
                    ['project_id' => $progetto->id, 'codice' => $codice],
                    [
                        'chiave' => substr($chiave, 0, 20),
                        'tipo' => $e['tipo'] ?? null,
                        'chi' => $e['chi'] ?? null,
                        'testo' => (string) ($e['testo'] ?? ''),
                        'macchine' => array_values(array_filter((array) ($e['macchine'] ?? []), 'is_string')),
                        // visto_at nasce sul web e non si tocca: il file dice solo se l'evento va segnalato
                        'da_vedere' => (bool) ($e['daVedere'] ?? false),
                    ],
                );
            }
            $progetto->events()->whereNotIn('codice', $codiciEventi)->delete();

            $decisioni = $this->decisioni($progetto, is_array($dati['decisioni'] ?? null) ? $dati['decisioni'] : []);
            $persone = $this->persone($progetto, is_array($dati['persone'] ?? null) ? $dati['persone'] : []);
            $proposti = $this->compitiProposti($progetto, is_array($dati['compitiProposti'] ?? null) ? $dati['compitiProposti'] : []);

            return ['progetto' => $slug, 'macchine' => count($codiciMacchine), 'domande' => $domande, 'eventi' => count($codiciEventi), 'decisioni' => $decisioni, 'persone' => $persone, 'compitiProposti' => $proposti];
        });
    }

    private function domande(Machine $macchina, array $chiedere): int
    {
        $chiavi = [];
        foreach (array_values($chiedere) as $i => $q) {
            $cosa = trim((string) ($q['cosa'] ?? ''));
            if ($cosa === '') {
                continue;
            }
            $chiave = isset($q['id']) && $q['id'] !== '' ? substr((string) $q['id'], 0, 64) : substr(sha1($cosa), 0, 16);
            $chiavi[] = $chiave;

            $domanda = Question::firstOrNew(['machine_id' => $macchina->id, 'chiave' => $chiave]);
            $domanda->fill([
                'ordine' => $i,
                'chi' => $q['chi'] ?? null,
                'cosa' => $cosa,
                'risposta' => $q['risposta'] ?? null,
            ]);
            if (! $domanda->fatto_web) {
                $domanda->fatto = (bool) ($q['fatto'] ?? false);
                $il = (string) ($q['fattoIl'] ?? '');
                $domanda->fatto_il = $domanda->fatto && preg_match('/^\d{4}-\d{2}-\d{2}$/', $il) ? $il : null;
            }
            $domanda->save();
        }
        $macchina->questions()->whereNotIn('chiave', $chiavi)->delete();

        return count($chiavi);
    }

    /** Decisioni che aspettano Francesco. Come per le domande, la spunta data dal web vince sul file. */
    private function decisioni(Project $progetto, array $elenco): int
    {
        $codici = [];
        foreach (array_values($elenco) as $i => $d) {
            $testo = trim((string) ($d['testo'] ?? ''));
            if ($testo === '') {
                continue;
            }
            $codice = $this->codice($d['id'] ?? null, 'decisione');
            $codici[] = $codice;
            $decisione = Decisione::firstOrNew(['project_id' => $progetto->id, 'codice' => $codice]);
            $data = (string) ($d['data'] ?? '');
            $decisione->fill([
                'ordine' => $i,
                'testo' => $testo,
                'fonte' => isset($d['fonte']) ? mb_substr((string) $d['fonte'], 0, 250) : null,
                'macchine' => array_values(array_filter((array) ($d['macchine'] ?? []), 'is_string')),
                'data' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) ? $data : null,
            ]);
            if (! $decisione->fatta_web) {
                $decisione->fatta = (bool) ($d['fatta'] ?? false);
                $il = (string) ($d['fattaIl'] ?? '');
                $decisione->fatta_il = $decisione->fatta && preg_match('/^\d{4}-\d{2}-\d{2}$/', $il) ? $il : null;
            }
            $decisione->save();
        }
        $progetto->decisioni()->whereNotIn('codice', $codici)->delete();

        return count($codici);
    }

    /** Rubrica del progetto: fotografia completa, come le macchine. */
    private function persone(Project $progetto, array $elenco): int
    {
        $codici = [];
        foreach (array_values($elenco) as $i => $p) {
            $nome = trim((string) ($p['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $codice = $this->codice($p['id'] ?? null, 'persona');
            $codici[] = $codice;
            $corto = fn ($k) => isset($p[$k]) && $p[$k] !== '' ? mb_substr((string) $p[$k], 0, 250) : null;
            Persona::updateOrCreate(['project_id' => $progetto->id, 'codice' => $codice], [
                'ordine' => $i,
                'nome' => mb_substr($nome, 0, 250),
                'azienda' => $corto('azienda'),
                'gruppo' => isset($p['gruppo']) ? mb_substr((string) $p['gruppo'], 0, 60) : null,
                'ruolo' => isset($p['ruolo']) ? (string) $p['ruolo'] : null,
                'macchine' => array_values(array_filter((array) ($p['macchine'] ?? []), 'is_string')),
                'contatti' => $corto('contatti'),
                'fonte' => $corto('fonte'),
            ]);
        }
        $progetto->persone()->whereNotIn('codice', $codici)->delete();

        return count($codici);
    }

    /**
     * Compiti proposti dal PC (da mail, appunti, visite). Si creano come "proposto"; finche'
     * Francesco non li tocca il file li aggiorna o li toglie. Confermati o scartati, sono suoi:
     * il file non li cambia piu'.
     */
    private function compitiProposti(Project $progetto, array $elenco): int
    {
        $codici = [];
        foreach ($elenco as $c) {
            $testo = trim((string) ($c['testo'] ?? ''));
            if ($testo === '') {
                continue;
            }
            $codice = $this->codice($c['id'] ?? null, 'compito');
            $codici[] = $codice;
            $compito = Compito::firstOrNew(['project_id' => $progetto->id, 'codice' => $codice]);
            if ($compito->exists && $compito->stato !== 'proposto') {
                continue;
            }
            $scadenza = (string) ($c['scadenza'] ?? '');
            $compito->fill([
                'stato' => 'proposto',
                'persona' => isset($c['persona']) && $c['persona'] !== '' ? mb_substr((string) $c['persona'], 0, 40) : null,
                'testo' => $testo,
                'macchine' => array_values(array_filter((array) ($c['macchine'] ?? []), 'is_string')),
                'fonte' => isset($c['fonte']) ? mb_substr((string) $c['fonte'], 0, 250) : null,
                'scadenza' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $scadenza) ? $scadenza : null,
            ])->save();
        }
        $progetto->compiti()->where('stato', 'proposto')->whereNotNull('codice')->whereNotIn('codice', $codici)->delete();

        return count($codici);
    }

    private function codice(mixed $valore, string $cosa): string
    {
        $valore = (string) $valore;
        if (! preg_match('/^[A-Za-z0-9_.-]{1,40}$/', $valore)) {
            throw new InvalidArgumentException("Codice $cosa mancante o non valido: \"$valore\".");
        }

        return $valore;
    }
}
