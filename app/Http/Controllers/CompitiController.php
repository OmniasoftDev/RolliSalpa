<?php

namespace App\Http\Controllers;

use App\Models\Compito;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Persone e compiti: Francesco assegna i compiti dal sito e li chiude quando sono fatti. */
class CompitiController extends Controller
{
    /** GET /{slug}/compiti */
    public function show(string $slug): View
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();

        return view('compiti.show', [
            'progetto' => $progetto,
            'progetti' => Project::orderBy('ordine')->get(['slug', 'nome']),
            'macchine' => $progetto->machines,
            'persone' => $progetto->persone()->get(),
            'compiti' => $progetto->compiti()->orderByRaw('scadenza is null')->orderBy('scadenza')->orderByDesc('id')->get(),
        ]);
    }

    /** POST /{slug}/compiti — nuovo compito assegnato da Francesco. */
    public function store(Request $request, string $slug): JsonResponse
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();
        $dati = $request->validate($this->regole($progetto) + ['testo' => ['required', 'string', 'max:4000']], $this->messaggi());

        $compito = Compito::create([
            'project_id' => $progetto->id,
            'persona' => $dati['persona'] ?? null,
            'testo' => $dati['testo'],
            'macchine' => array_values($dati['macchine'] ?? []),
            'fonte' => $dati['fonte'] ?? null,
            'scadenza' => $dati['scadenza'] ?? null,
            'stato' => 'aperto',
            'assegnato_il' => today(),
        ]);

        return response()->json(['ok' => true, 'id' => $compito->id]);
    }

    /** POST /compiti/{compito} — conferma, cambia persona/scadenza, chiude o riapre. */
    public function aggiorna(Request $request, Compito $compito): JsonResponse
    {
        $dati = $request->validate($this->regole($compito->project) + [
            'testo' => ['sometimes', 'string', 'max:4000'],
            'stato' => ['sometimes', Rule::in(['aperto', 'fatto', 'annullato'])],
            'esito' => ['nullable', 'string', 'max:4000'],
        ], $this->messaggi());

        if (isset($dati['stato']) && $dati['stato'] !== $compito->stato) {
            if ($dati['stato'] === 'aperto') {
                $compito->assegnato_il ??= today();
                $compito->chiuso_il = null;
            } else {
                $compito->chiuso_il = today();
            }
        }
        $compito->fill(collect($dati)->only(['persona', 'testo', 'macchine', 'fonte', 'scadenza', 'stato', 'esito'])->all())->save();

        return response()->json(['ok' => true]);
    }

    private function regole(Project $progetto): array
    {
        return [
            'persona' => ['nullable', Rule::in($progetto->persone()->pluck('codice')->all())],
            'macchine' => ['array'],
            'macchine.*' => [Rule::in($progetto->machines()->pluck('codice')->all())],
            'fonte' => ['nullable', 'string', 'max:250'],
            'scadenza' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    private function messaggi(): array
    {
        return [
            'testo.required' => 'Scrivi cosa deve fare.',
            'persona.in' => 'Persona non trovata nella rubrica del progetto.',
            'scadenza.date_format' => 'Scadenza non valida.',
        ];
    }
}
