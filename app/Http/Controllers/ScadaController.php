<?php

namespace App\Http\Controllers;

use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\Persona;
use App\Models\Project;
use App\Services\BozzaMail;
use App\Services\LavoroFrancesco;
use App\Services\Scada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Throwable;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "SCADA" (Francesco, 16/10/2026): per ogni macchina del progetto cosa si vede nello SCADA Edica
 * (campo `scada` del file del PC) e le schermate caricate dal PC; in cima le schermate del sistema SCADA.
 */
class ScadaController extends Controller
{
    /** GET /{slug}/scada */
    public function show(string $slug, Scada $scada): View
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();
        $righe = $progetto->machines->reject(fn ($m) => $m->dato('escludiDaiConteggi'))->values()
            ->map(fn ($m) => ['macchina' => $m, 's' => $scada->scheda($progetto, $m)]);

        $sistemi = collect($progetto->sistemi())->where('tipo', 'scada')->values()
            ->map(fn ($s) => $s + ['galleria' => $scada->schermate($progetto, $s['codice'], $s['schermate'])]);

        return view('scada.show', [
            'progetto' => $progetto,
            'progetti' => Project::orderBy('ordine')->get(['slug', 'nome']),
            'righe' => $righe,
            'sistemi' => $sistemi,
            'raccolta' => $scada->domande($progetto),
            'destinatario' => $this->destinatario($progetto),
            'conteggi' => collect(Scada::STATI)->map(fn ($x, $k) => $righe->filter(fn ($r) => ($r['s']['stato'] ?? 'assente') === $k)->count()),
        ]);
    }

    /**
     * Destinatario delle domande dallo SCADA: solo la persona indicata in info.destinatarioScada
     * (nessun default: per Rolli Edica e' esclusa dalle mail, Francesco 08/10/2026).
     */
    private function destinatario(Project $progetto): ?Persona
    {
        $id = $progetto->info('destinatarioScada');

        return is_string($id) && $id !== '' ? $progetto->persone()->where('codice', $id)->first() : null;
    }

    /** POST /{slug}/scada/bozza — Claude scrive la mail con le domande nate dallo SCADA; non la invia. */
    public function bozza(Request $request, string $slug, Scada $scada, BozzaMail $bozze): JsonResponse
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();
        $dati = $request->validate(['nota' => ['nullable', 'string', 'max:2000']]);
        $persona = $this->destinatario($progetto);
        if ($persona === null) {
            return response()->json(['errore' => 'Manca il destinatario: info.destinatarioScada nel file del progetto.'], 422);
        }
        $raccolta = $scada->domande($progetto);
        $voci = [];
        foreach ($raccolta['domande'] as $d) {
            $voci[] = ['tipo' => 'domanda', 'testo' => $d['testo'], 'macchine' => trim($d['num'].' '.$d['nome'])];
        }
        $numDi = $progetto->machines->mapWithKeys(fn ($m) => [$m->codice => trim($m->dato('num', '').' '.$m->dato('nome', ''))]);
        foreach ($raccolta['compiti'] as $c) {
            $voci[] = array_filter(['tipo' => 'compito', 'testo' => $c->testo, 'macchine' => collect($c->macchine ?? [])->map(fn ($x) => $numDi[$x] ?? $x)->join(', '),
                'scadenza' => $c->scadenza?->format('d/m/Y')]);
        }
        if ($voci === []) {
            return response()->json(['errore' => 'Nessuna domanda dallo SCADA da mandare.'], 422);
        }

        @set_time_limit(180);
        try {
            $mail = $bozze->scrivi($progetto, $persona, 'richiesta', $voci, [], $dati['nota'] ?? 'Domande nate dalla visita dentro lo SCADA Edica.');
        } catch (RuntimeException $e) {
            return response()->json(['errore' => $e->getMessage()], 503);
        } catch (APIStatusException $e) {
            Log::warning('Bozza SCADA: errore API', ['messaggio' => $e->getMessage()]);

            return response()->json(['errore' => 'Errore dell'API di Claude: riprova.'], 502);
        } catch (Throwable $e) {
            Log::error('Bozza SCADA: errore', ['eccezione' => $e]);

            return response()->json(['errore' => 'Bozza non arrivata (connessione o tempo scaduto): riprova.'], 502);
        }

        return response()->json($mail + ['a' => LavoroFrancesco::mailDi($persona)]);
    }

    /** GET /{slug}/schermate/{codice}/{file} — la schermata (solo utenti autenticati). */
    public function file(string $slug, string $codice, string $file): StreamedResponse
    {
        $nome = Scada::nomeFile($file);
        abort_unless($nome !== null && $nome === $file, 404);
        $percorso = Scada::cartella($slug, $codice).'/'.$nome;
        abort_unless(Storage::disk('local')->exists($percorso), 404);

        return Storage::disk('local')->response($percorso, $nome, ['Cache-Control' => 'private, max-age=86400']);
    }
}
