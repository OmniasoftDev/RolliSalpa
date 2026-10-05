<?php

namespace App\Http\Controllers;

use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\Compito;
use App\Models\Event;
use App\Models\Machine;
use App\Models\Project;
use App\Models\Question;
use App\Services\BozzaMail;
use App\Services\LavoroFrancesco;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/** "Il mio lavoro": l'area di Francesco con compiti, decisioni, risposte attese, bozze di mail e calendario. */
class LavoroController extends Controller
{
    /** GET /{slug}/lavoro */
    public function show(string $slug, LavoroFrancesco $lavoro): View
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();

        return view('lavoro.show', [
            'progetto' => $progetto,
            'progetti' => Project::orderBy('ordine')->get(['slug', 'nome']),
            'macchine' => $progetto->machines,
            'io' => LavoroFrancesco::IO,
        ] + $lavoro->per($progetto));
    }

    /** POST /{slug}/lavoro/bozza — Claude scrive la mail per le voci scelte; non la invia. */
    public function bozza(Request $request, string $slug, BozzaMail $bozze): JsonResponse
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();
        $dati = $request->validate([
            'persona' => ['nullable', Rule::in($progetto->persone()->pluck('codice')->all())],
            'scopo' => ['required', Rule::in(['sollecito', 'richiesta', 'risposta', 'aggiornamento'])],
            'compiti' => ['array'], 'compiti.*' => ['integer'],
            'domande' => ['array'], 'domande.*' => ['integer'],
            'nota' => ['nullable', 'string', 'max:2000'],
        ], ['persona.in' => 'Persona non trovata nella rubrica del progetto.']);

        $numDi = $progetto->machines->mapWithKeys(fn (Machine $m) => [$m->codice => trim($m->dato('num', '').' '.$m->dato('nome', ''))]);
        $macchine = fn (?array $codici) => collect($codici ?? [])->map(fn ($c) => $numDi[$c] ?? $c)->join(', ');

        $voci = [];
        foreach ($progetto->compiti()->whereIn('id', $dati['compiti'] ?? [])->get() as $c) {
            $voci[] = array_filter(['tipo' => 'compito', 'testo' => $c->testo, 'macchine' => $macchine($c->macchine), 'fonte' => $c->fonte,
                'scadenza' => $c->scadenza?->format('d/m/Y'), 'assegnato_il' => $c->assegnato_il?->format('d/m/Y'), 'scaduto' => $c->scaduto() ?: null]);
        }
        $domande = Question::whereIn('id', $dati['domande'] ?? [])->whereHas('machine', fn ($q) => $q->where('project_id', $progetto->id))->with('machine')->get();
        foreach ($domande as $q) {
            $voci[] = ['tipo' => 'domanda', 'testo' => $q->cosa, 'macchine' => $numDi[$q->machine->codice] ?? $q->machine->codice];
        }
        if (! $voci && blank($dati['nota'] ?? null)) {
            return response()->json(['errore' => 'Scegli almeno un punto o scrivi cosa vuoi dire.'], 422);
        }

        $persona = isset($dati['persona']) ? $progetto->persone()->where('codice', $dati['persona'])->first() : null;
        $eventi = $persona ? $this->eventiSu($progetto, $persona->nome) : [];

        @set_time_limit(180);
        try {
            $mail = $bozze->scrivi($progetto, $persona, $dati['scopo'], $voci, $eventi, $dati['nota'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['errore' => $e->getMessage()], 503);
        } catch (APIStatusException $e) {
            Log::warning('Bozza mail: errore API', ['tipo' => $e->type?->value, 'messaggio' => $e->getMessage()]);
            $dettaglio = is_array($e->body ?? null) ? ($e->body['error']['message'] ?? null) : null;

            return response()->json(['errore' => 'Errore dell\'API di Claude'.($dettaglio ? ': '.$dettaglio : ': riprova.')], 502);
        } catch (Throwable $e) {
            Log::error('Bozza mail: errore', ['eccezione' => $e]);

            return response()->json(['errore' => 'Bozza non arrivata (connessione o tempo scaduto): riprova.'], 502);
        }

        return response()->json($mail + ['a' => LavoroFrancesco::mailDi($persona)]);
    }

    /** Ultimi fatti del progetto che citano la persona (per cognome), da dare a Claude come contesto. */
    private function eventiSu(Project $progetto, string $nome): array
    {
        $parole = collect(preg_split('/[^\p{L}]+/u', $nome))->filter(fn ($p) => mb_strlen($p) > 3)->values();
        if ($parole->isEmpty()) {
            return [];
        }

        return $progetto->events()->limit(200)->get()
            ->filter(fn (Event $e) => $parole->contains(fn ($w) => mb_stripos($e->chi.' '.$e->testo, $w) !== false))
            ->take(10)
            ->map(fn (Event $e) => ['quando' => $e->quando(), 'chi' => $e->chi, 'testo' => $e->testoMostrato()])
            ->values()->all();
    }
}
