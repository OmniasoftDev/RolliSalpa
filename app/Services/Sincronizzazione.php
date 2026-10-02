<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Machine;
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
                    ],
                );
            }
            $progetto->events()->whereNotIn('codice', $codiciEventi)->delete();

            return ['progetto' => $slug, 'macchine' => count($codiciMacchine), 'domande' => $domande, 'eventi' => count($codiciEventi)];
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

    private function codice(mixed $valore, string $cosa): string
    {
        $valore = (string) $valore;
        if (! preg_match('/^[A-Za-z0-9_.-]{1,40}$/', $valore)) {
            throw new InvalidArgumentException("Codice $cosa mancante o non valido: \"$valore\".");
        }

        return $valore;
    }
}
