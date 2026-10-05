<?php

namespace App\Services;

use App\Models\Compito;
use App\Models\Decisione;
use App\Models\Persona;
use App\Models\Project;
use App\Models\Question;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * "Il mio lavoro": tutto quello che aspetta Francesco in un progetto (suoi compiti, decisioni,
 * risposte attese dagli altri, domande da fare) e il calendario proposto in base alle scadenze.
 */
class LavoroFrancesco
{
    /** Codice di Francesco nella rubrica di ogni progetto (pannello/<slug>.json, campo persone). */
    public const IO = 'p0';

    /** Giorni dopo l'assegnazione per verificare un compito altrui senza scadenza. */
    private const GIORNI_VERIFICA = 7;

    public function per(Project $progetto): array
    {
        $persone = $progetto->persone()->get()->keyBy('codice');
        $aperti = $progetto->compiti()->where('stato', 'aperto')->orderByRaw('scadenza is null')->orderBy('scadenza')->orderBy('id')->get();
        $miei = $aperti->where('persona', self::IO)->values();
        $attesi = $aperti->reject(fn (Compito $c) => $c->persona === self::IO)->values();
        $decisioni = $progetto->decisioni()->where('fatta', false)->get();
        $domande = Question::whereHas('machine', fn ($q) => $q->where('project_id', $progetto->id))
            ->where('fatto', false)->with('machine')->orderBy('ordine')->get();

        // domande raggruppate per destinatario, con la persona della rubrica quando si riconosce
        $perChi = $domande->groupBy(fn (Question $q) => trim($q->chi) ?: 'Senza destinatario')
            ->map(fn (Collection $qs, string $chi) => ['chi' => $chi, 'persona' => $this->personaDaChi($chi, $persone), 'domande' => $qs])
            ->sortByDesc(fn ($g) => $g['domande']->count())->values();

        return [
            'persone' => $persone,
            'miei' => $miei,
            'proposti' => $progetto->compiti()->where('stato', 'proposto')->where('persona', self::IO)->get(),
            'attesi' => $attesi,
            'decisioni' => $decisioni,
            'domande' => $perChi,
            'calendario' => $this->calendario($miei, $attesi, $decisioni, $persone),
        ];
    }

    /**
     * Proposte di calendario: una per compito o decisione, alla scadenza; se la scadenza e' passata o manca,
     * al primo giorno lavorativo utile. Francesco la accetta (o cambia data) e la manda a Google Calendar.
     */
    public function calendario(Collection $miei, Collection $attesi, Collection $decisioni, Collection $persone): Collection
    {
        $oggi = $this->lavorativo(today());
        $voci = collect();

        foreach ($miei as $c) {
            [$data, $motivo] = $this->dataDa($c->scadenza, $oggi, null);
            $voci->push(['chiave' => 'c'.$c->id, 'tipo' => 'mio', 'data' => $data, 'motivo' => $motivo,
                'titolo' => $c->testo, 'dettaglio' => $c->fonte, 'scaduto' => $c->scaduto()]);
        }
        foreach ($decisioni as $d) {
            $voci->push(['chiave' => 'd'.$d->id, 'tipo' => 'decisione', 'data' => $oggi, 'motivo' => 'decisione in sospeso',
                'titolo' => 'Decidere: '.$d->testo, 'dettaglio' => $d->fonte, 'scaduto' => false]);
        }
        foreach ($attesi as $c) {
            $chi = $c->persona ? ($persone[$c->persona]->nome ?? $c->persona) : 'da assegnare';
            $verifica = $c->assegnato_il?->copy()->addDays(self::GIORNI_VERIFICA);
            [$data, $motivo] = $this->dataDa($c->scadenza, $oggi, $verifica);
            $voci->push(['chiave' => 'c'.$c->id, 'tipo' => 'attesa', 'data' => $data, 'motivo' => $motivo,
                'titolo' => ($c->scaduto() ? 'Sollecitare ' : 'Verificare ').$chi.': '.$c->testo, 'dettaglio' => $c->fonte, 'scaduto' => $c->scaduto()]);
        }

        return $voci->sortBy(fn ($v) => [$v['data']->format('Y-m-d'), $v['tipo'] === 'mio' ? 0 : ($v['tipo'] === 'decisione' ? 1 : 2)])->values();
    }

    /** [data proposta, perche'] partendo dalla scadenza, o da una data di verifica quando la scadenza manca. */
    private function dataDa(?Carbon $scadenza, Carbon $oggi, ?Carbon $verifica): array
    {
        if ($scadenza) {
            return $scadenza->lt($oggi)
                ? [$oggi, 'scaduto il '.$scadenza->format('d/m')]
                : [$this->lavorativo($scadenza), 'scadenza '.$scadenza->format('d/m')];
        }
        if ($verifica && $verifica->gt($oggi)) {
            return [$this->lavorativo($verifica), 'senza scadenza: verifica a '.self::GIORNI_VERIFICA.' giorni dall\'assegnazione'];
        }

        return [$oggi, 'senza scadenza'];
    }

    /** Stesso giorno se feriale, altrimenti il lunedi' dopo. */
    private function lavorativo(Carbon $giorno): Carbon
    {
        $g = $giorno->copy()->startOfDay();
        while ($g->isWeekend()) {
            $g->addDay();
        }

        return $g;
    }

    /**
     * Persona della rubrica a cui va una domanda, dal testo libero "chi" del file
     * ("Merlotti", "Andrea Uberti (JBT)", "CFT (tramite Merlotti)"): chi fa da tramite riceve la mail.
     */
    public function personaDaChi(string $chi, Collection $persone): ?Persona
    {
        if (preg_match('/tramite\s+([\p{L}.\' ]+)/u', $chi, $m)) {
            $chi = $m[1];
        }
        $parole = collect(preg_split('/[^\p{L}]+/u', mb_strtolower($chi)))->filter(fn ($p) => mb_strlen($p) > 3);
        $conta = fn (string $testo) => $parole->filter(fn ($w) => preg_match('/\b'.preg_quote($w, '/').'\b/u', mb_strtolower($testo)))->count();

        // vince chi ha piu' parole in comune nel nome ("Andrea Uberti" batte "Andrea Calabretta"), poi nell'azienda ("Edica")
        $migliore = null;
        $punti = [0, 0];
        foreach ($persone as $p) {
            if ($p->codice === self::IO) {
                continue;
            }
            $suoi = [$conta($p->nome), $conta((string) $p->azienda)];
            if ($suoi > $punti) {
                [$migliore, $punti] = [$p, $suoi];
            }
        }

        return $migliore;
    }

    /** Primo indirizzo mail tra i contatti della persona (i contatti sono testo libero). */
    public static function mailDi(?Persona $p): ?string
    {
        return $p && preg_match('/[\w.+-]+@[\w-]+(\.[\w-]+)+/u', (string) $p->contatti, $m) ? $m[0] : null;
    }

    /** Link "aggiungi evento" di Google Calendar per un evento di tutto il giorno. */
    public static function linkCalendario(string $titolo, Carbon $data, ?string $dettaglio): string
    {
        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => mb_strimwidth($titolo, 0, 200, '…'),
            'dates' => $data->format('Ymd').'/'.$data->copy()->addDay()->format('Ymd'),
            'details' => (string) $dettaglio,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
