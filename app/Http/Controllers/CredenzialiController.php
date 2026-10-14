<?php

namespace App\Http\Controllers;

use App\Models\Credenziale;
use App\Models\CredenzialeAccesso;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Cassaforte delle credenziali (Francesco, 14/10/2026), per sistema o macchina di un progetto.
 * I valori (utente, segreto, note) sono cifrati nel DB e tornano in chiaro solo da "Mostra",
 * se la password di Francesco e' stata confermata negli ultimi 15 minuti. Tutte le risposte sono JSON
 * (le chiama public/js/credenziali.js); ogni lettura o scrittura lascia una riga in credenziali_accessi.
 * Finche' le tabelle non sono importate (database/sql/2026-10-14-credenziali.sql) le scritture rispondono 503.
 */
class CredenzialiController extends Controller
{
    /** Validita' della conferma della password, in secondi. */
    private const CONFERMA_SECONDI = 900;

    /** POST /credenziali/conferma — verifica la password dell'utente e apre la cassaforte per 15 minuti. */
    public function conferma(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']], ['password.required' => 'Scrivi la password.']);
        if (! Hash::check((string) $request->input('password'), $request->user()->password)) {
            return response()->json(['errore' => 'Password errata.'], 422);
        }
        $request->session()->put('auth.password_confirmed_at', time());

        return response()->json(['ok' => true]);
    }

    /** POST /{slug}/credenziali — nuova credenziale di un sistema o di una macchina. */
    public function store(Request $request, string $slug): JsonResponse
    {
        if ($r = $this->nonAttiva()) {
            return $r;
        }
        $progetto = Project::where('slug', $slug)->firstOrFail();
        $macchine = $progetto->machines()->pluck('codice')->all();
        $sistemi = array_column($progetto->sistemi(), 'codice');
        $dati = $request->validate($this->regole() + [
            'oggetto_tipo' => ['required', Rule::in(Credenziale::TIPI_OGGETTO)],
            'oggetto_codice' => ['required', 'string', Rule::in($request->input('oggetto_tipo') === 'sistema' ? $sistemi : $macchine)],
        ], $this->messaggi());

        $c = Credenziale::create($this->valori($dati) + [
            'project_id' => $progetto->id,
            'user_id' => $request->user()->id,
            'oggetto_tipo' => $dati['oggetto_tipo'],
            'oggetto_codice' => $dati['oggetto_codice'],
        ]);
        $this->registra($request, $c->id, 'creazione');

        return response()->json(['ok' => true, 'id' => $c->id]);
    }

    /** PUT /credenziali/{id} — modifica (il modulo si riempie con i valori di "Mostra"). */
    public function aggiorna(Request $request, int $id): JsonResponse
    {
        if ($r = $this->nonAttiva()) {
            return $r;
        }
        $c = Credenziale::findOrFail($id);
        $dati = $request->validate($this->regole(), $this->messaggi());
        $c->update($this->valori($dati));
        $this->registra($request, $c->id, 'modifica');

        return response()->json(['ok' => true]);
    }

    /** DELETE /credenziali/{id} — il registro resta, con l'etichetta nella nota. */
    public function elimina(Request $request, int $id): JsonResponse
    {
        if ($r = $this->nonAttiva()) {
            return $r;
        }
        $c = Credenziale::with('project')->findOrFail($id);
        DB::transaction(function () use ($request, $c) {
            $nota = $c->project->slug.' '.$c->oggetto_tipo.' '.$c->oggetto_codice.': '.$c->etichetta;
            $c->delete();
            $this->registra($request, null, 'eliminazione', $nota);
        });

        return response()->json(['ok' => true]);
    }

    /** POST /credenziali/{id}/mostra — valori in chiaro, solo con la password confermata da meno di 15 minuti (altrimenti 423). */
    public function mostra(Request $request, int $id): JsonResponse
    {
        if ($r = $this->nonAttiva()) {
            return $r;
        }
        $c = Credenziale::findOrFail($id);
        if (time() - (int) $request->session()->get('auth.password_confirmed_at', 0) >= self::CONFERMA_SECONDI) {
            return response()->json(['conferma' => true, 'errore' => 'Conferma la password per vedere le credenziali.'], 423)
                ->header('Cache-Control', 'no-store, private');
        }
        $this->registra($request, $c->id, 'mostra');

        return response()->json(['utente' => $c->utente, 'segreto' => $c->segreto, 'note' => $c->note])
            ->header('Cache-Control', 'no-store, private')->header('Pragma', 'no-cache');
    }

    private function nonAttiva(): ?JsonResponse
    {
        return Credenziale::attiva() ? null : response()->json(['errore' => 'Cassaforte non ancora attiva: va importato database/sql/2026-10-14-credenziali.sql.'], 503);
    }

    private function regole(): array
    {
        return [
            'etichetta' => ['required', 'string', 'max:150'],
            'indirizzo' => ['nullable', 'string', 'max:255'],
            'utente' => ['nullable', 'string', 'max:500'],
            'segreto' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:5000'],
            'fonte' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function messaggi(): array
    {
        return [
            'etichetta.required' => 'Scrivi l\'etichetta (es. "Router ASEM WAN").',
            'etichetta.max' => 'Etichetta troppo lunga (massimo 150 caratteri).',
            'oggetto_tipo.in' => 'Tipo di oggetto non valido (sistema o macchina).',
            'oggetto_codice.in' => 'Sistema o macchina non trovato in questo progetto.',
        ];
    }

    /** Campi modificabili, con le stringhe vuote portate a null (segreto e note non si accorciano). */
    private function valori(array $dati): array
    {
        $v = [];
        foreach (['etichetta', 'indirizzo', 'utente', 'segreto', 'note', 'fonte'] as $k) {
            $x = isset($dati[$k]) ? (string) $dati[$k] : '';
            if (! in_array($k, ['segreto', 'note'], true)) {
                $x = trim($x);
            }
            $v[$k] = $x === '' ? null : $x;
        }

        return $v;
    }

    /** Una riga del registro: mai i valori, solo chi, cosa, quando e da dove. */
    private function registra(Request $request, ?int $id, string $azione, ?string $nota = null): void
    {
        CredenzialeAccesso::create([
            'credenziale_id' => $id,
            'user_id' => $request->user()?->id,
            'azione' => $azione,
            'nota' => $nota !== null ? mb_substr($nota, 0, 250) : null,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
