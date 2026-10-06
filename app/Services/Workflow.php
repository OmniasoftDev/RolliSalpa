<?php

namespace App\Services;

use App\Models\Appuntamento;
use App\Models\Compito;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * "Workflow": tutto il lavoro del progetto su una linea del tempo, per il calendario a mese e la timeline per macchina.
 * Voci: fasi del planning (file, campo "planning"), appuntamenti (tabella), eventi del calendario Google Salpa-Rolli
 * (letti dal PC con l'indirizzo iCal e mandati in /api/stato), scadenze dei compiti, mail registrate (eventi "mail").
 */
class Workflow
{
    public const TIPI = [
        'fase' => 'Fasi del planning',
        'appuntamento' => 'Appuntamenti',
        'google' => 'Google Calendar',
        'scadenza' => 'Scadenze compiti',
        'mail' => 'Mail',
    ];

    public const STATI_FASE = ['previsto' => 'Prevista', 'fisso' => 'Data fissata', 'corso' => 'In corso', 'fatto' => 'Fatta', 'rischio' => 'A rischio'];

    /** @return Collection<int, array> voci ordinate per inizio */
    public function voci(Project $progetto): Collection
    {
        $persone = $progetto->persone()->get()->keyBy('codice');
        $voci = collect();

        foreach ((array) $progetto->info('planning', []) as $i => $f) {
            $dal = $this->giorno($f['dal'] ?? null);
            $al = $this->giorno($f['al'] ?? null) ?? $dal;
            if (! $dal || trim((string) ($f['titolo'] ?? '')) === '' || $al->lt($dal)) {
                continue;
            }
            $stato = array_key_exists($f['stato'] ?? '', self::STATI_FASE) ? $f['stato'] : 'previsto';
            $voci->push($this->voce('fase', 'f-'.preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($f['id'] ?? $i)), $dal, $al->copy()->endOfDay(), [
                'titolo' => (string) $f['titolo'],
                'dettaglio' => $f['dettaglio'] ?? null,
                'fonte' => $f['fonte'] ?? null,
                'macchine' => $this->codici($f['macchine'] ?? []),
                'stato' => $stato,
                'etichetta' => self::STATI_FASE[$stato],
                'tuttoIlGiorno' => true,
            ]));
        }

        $appuntamenti = $progetto->appuntamenti()->where('stato', '!=', 'rifiutato')->get();
        foreach ($appuntamenti as $a) {
            $voci->push($this->voce('appuntamento', 'a-'.$a->id, $a->inizio, $a->fine, [
                'titolo' => $a->titolo,
                'dettaglio' => $a->dettaglio,
                'luogo' => $a->luogo,
                'fonte' => $a->fonte,
                'macchine' => $a->macchine ?? [],
                'stato' => $a->stato,
                'etichetta' => $a->stato === 'proposto' ? 'Da accettare' : 'Accettato',
                'link' => [['Accetta o cambia in Il mio lavoro', route('lavoro', $progetto->slug)]],
            ]));
        }

        // eventi del calendario Google: quelli gia' presenti come appuntamento (stesso inizio e fine) non si ripetono
        $giaDentro = $appuntamenti->where('stato', 'accettato')->map(fn (Appuntamento $a) => $a->inizio->format('YmdHi').$a->fine->format('YmdHi'))->flip();
        foreach ($this->google($progetto->slug) as $g) {
            $inizio = $this->dataOra($g['inizio'] ?? null);
            $fine = $this->dataOra($g['fine'] ?? null) ?? $inizio;
            if (! $inizio || isset($giaDentro[$inizio->format('YmdHi').$fine->format('YmdHi')])) {
                continue;
            }
            $tutto = (bool) ($g['tuttoIlGiorno'] ?? false);
            $voci->push($this->voce('google', 'g-'.substr(sha1((string) ($g['uid'] ?? '').$inizio->format('YmdHi')), 0, 12), $inizio, $tutto ? $fine->copy()->subSecond() : $fine, [
                'titolo' => (string) ($g['titolo'] ?? '(senza titolo)'),
                'dettaglio' => $g['descrizione'] ?? null,
                'luogo' => $g['luogo'] ?? null,
                'fonte' => 'Calendario Google Salpa-Rolli',
                'etichetta' => 'In Google Calendar',
                'tuttoIlGiorno' => $tutto,
            ]));
        }

        $compiti = $progetto->compiti()->whereIn('stato', ['aperto', 'proposto'])->whereNotNull('scadenza')->get();
        foreach ($compiti as $c) {
            $chi = $c->persona ? ($persone[$c->persona]->nome ?? $c->persona) : 'da assegnare';
            $voci->push($this->voce('scadenza', 'c-'.$c->id, $c->scadenza, $c->scadenza->copy()->endOfDay(), [
                'titolo' => $chi.': '.$c->testo,
                'fonte' => $c->fonte,
                'macchine' => $c->macchine ?? [],
                'stato' => $c->scaduto() ? 'scaduto' : $c->stato,
                'etichetta' => $c->scaduto() ? 'Scaduto' : Compito::STATI[$c->stato],
                'tuttoIlGiorno' => true,
                'link' => [['Persone e compiti', route('compiti', $progetto->slug)]],
            ]));
        }

        foreach ($progetto->events()->where('tipo', 'mail')->get() as $e) {
            $quando = $this->dataOra(str_replace(' ', 'T', $e->chiave)) ?? $this->giorno(substr($e->chiave, 0, 10));
            if (! $quando) {
                continue;
            }
            $testo = $e->testoMostrato();
            $voci->push($this->voce('mail', 'm-'.$e->id, $quando, $quando, [
                'titolo' => trim(($e->chi ? $e->chi.': ' : '').mb_strimwidth($testo, 0, 90, '…')),
                'dettaglio' => $testo,
                'fonte' => 'Mail '.$e->quando(),
                'macchine' => $e->macchine ?? [],
                'etichetta' => 'Mail',
                'tuttoIlGiorno' => strlen($e->chiave) <= 10,
                'link' => [['Ultimi fatti nel Quadro', route('pannello', $progetto->slug)], ['Registro mail', route('registro')]],
            ]));
        }

        return $voci->sortBy(fn ($v) => [$v['inizio']->format('YmdHi'), array_search($v['tipo'], array_keys(self::TIPI))])->values();
    }

    /** Eventi del calendario Google del progetto, dall'ultimo invio del PC. */
    public function google(string $slug): array
    {
        return array_values(array_filter((array) (Cache::get('calendario_google', [])['eventi'] ?? []), fn ($g) => is_array($g) && ($g['progetto'] ?? '') === $slug));
    }

    /** Quando il PC ha letto il calendario Google l'ultima volta ("2026-10-06 10:05"), o null. */
    public function googleLetto(): ?string
    {
        return Cache::get('calendario_google', [])['letto'] ?? null;
    }

    /** Lunedi' della prima settimana e domenica dell'ultima per la timeline: da una settimana fa alla scadenza del progetto (o all'ultima voce). */
    public function intervallo(Project $progetto, Collection $voci): array
    {
        $dal = today()->subWeek();
        $fasi = $voci->where('tipo', 'fase');
        if ($fasi->isNotEmpty() && $fasi->min('inizio')->lt($dal)) {
            $dal = $fasi->min('inizio')->copy()->max(today()->subWeeks(4));
        }
        $al = $this->giorno($progetto->info('scadenza')) ?? today()->addWeeks(8);
        $ultima = $voci->whereIn('tipo', ['fase', 'appuntamento', 'scadenza'])->max('fine');
        if ($ultima && $ultima->gt($al)) {
            $al = $ultima->copy()->min(today()->addMonths(6));
        }

        return [$dal->copy()->startOfWeek(), $al->copy()->endOfWeek()->startOfDay()];
    }

    private function voce(string $tipo, string $chiave, Carbon $inizio, ?Carbon $fine, array $altro): array
    {
        $fine = $fine && $fine->gte($inizio) ? $fine : $inizio->copy();
        $tutto = $altro['tuttoIlGiorno'] ?? false;
        $ora = $tutto ? null : ($inizio->eq($fine) ? $inizio->format('G:i') : $inizio->format('G:i').'–'.$fine->format('G:i'));

        return $altro + [
            'tipo' => $tipo, 'chiave' => $chiave, 'inizio' => $inizio->copy(), 'fine' => $fine->copy(), 'ora' => $ora,
            'titolo' => '', 'dettaglio' => null, 'luogo' => null, 'fonte' => null, 'macchine' => [], 'stato' => null,
            'etichetta' => self::TIPI[$tipo], 'link' => [], 'tuttoIlGiorno' => false,
        ];
    }

    private function codici(mixed $macchine): array
    {
        return array_values(array_filter((array) $macchine, 'is_string'));
    }

    private function giorno(mixed $valore): ?Carbon
    {
        return is_string($valore) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valore) ? Carbon::createFromFormat('!Y-m-d', $valore) : null;
    }

    /** "2026-10-14T10:00:00+02:00" o "2026-10-02T10:56" nell'ora di Roma. */
    private function dataOra(mixed $valore): ?Carbon
    {
        if (! is_string($valore) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $valore)) {
            return null;
        }
        try {
            return Carbon::parse($valore)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
