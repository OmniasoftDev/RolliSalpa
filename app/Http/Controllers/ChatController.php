<?php

namespace App\Http\Controllers;

use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\ChatMessaggio;
use App\Models\Project;
use App\Services\ChatClaude;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ChatController extends Controller
{
    public function show(string $slug): View
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();

        return view('chat.show', [
            'progetto' => $progetto,
            'progetti' => Project::orderBy('ordine')->get(['slug', 'nome']),
            'messaggi' => ChatMessaggio::where('project_id', $progetto->id)->orderByDesc('id')->limit(100)->get()->reverse()->values(),
            'macchine' => $progetto->machines,
            'configurata' => config('services.anthropic.key') !== '',
        ]);
    }

    /** POST /{slug}/chat — salva la domanda, chiede a Claude, restituisce la risposta. */
    public function invia(Request $request, string $slug, ChatClaude $chat): JsonResponse
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();
        $dati = $request->validate(['testo' => ['required', 'string', 'max:8000']], ['testo.required' => 'Scrivi un messaggio.']);

        $domanda = ChatMessaggio::create([
            'project_id' => $progetto->id,
            'user_id' => $request->user()->id,
            'ruolo' => 'user',
            'testo' => $dati['testo'],
        ]);

        @set_time_limit(300); // una risposta ragionata puo' richiedere un minuto o piu'
        try {
            $risposta = $chat->rispondi($progetto, $request->user()->id);
        } catch (RuntimeException $e) {
            return response()->json(['errore' => $e->getMessage(), 'domanda' => $this->json($domanda)], 503);
        } catch (APIStatusException $e) {
            Log::warning('Chat Claude: errore API', ['tipo' => $e->type?->value, 'messaggio' => $e->getMessage()]);
            $msg = match ($e->type?->value) {
                'rate_limit_error', 'overloaded_error' => 'Claude è momentaneamente sovraccarico: riprova tra qualche secondo.',
                'authentication_error', 'permission_error' => 'La chiave ANTHROPIC_API_KEY sul server non è valida.',
                default => 'Errore dell\'API di Claude: riprova. Se continua, guarda storage/logs sul server.',
            };

            return response()->json(['errore' => $msg, 'domanda' => $this->json($domanda)], 502);
        } catch (Throwable $e) {
            Log::error('Chat Claude: errore', ['eccezione' => $e]);

            return response()->json(['errore' => 'Risposta non arrivata (connessione o tempo scaduto): riprova.', 'domanda' => $this->json($domanda)], 502);
        }

        return response()->json(['domanda' => $this->json($domanda), 'risposta' => $this->json($risposta)]);
    }

    private function json(ChatMessaggio $m): array
    {
        return ['id' => $m->id, 'ruolo' => $m->ruolo, 'testo' => $m->testo, 'quando' => $m->created_at->format('d/m H:i')];
    }
}
