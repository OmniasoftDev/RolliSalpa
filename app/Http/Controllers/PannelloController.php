<?php

namespace App\Http\Controllers;

use App\Models\Appunto;
use App\Models\Project;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PannelloController extends Controller
{
    public function index(): RedirectResponse|View
    {
        $primo = Project::orderBy('ordine')->first();

        return $primo ? redirect()->route('pannello', $primo->slug) : view('pannello.vuoto');
    }

    public function show(string $slug): View
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();
        $progetto->load(['machines.questions']);

        return view('pannello.show', [
            'progetto' => $progetto,
            'progetti' => Project::orderBy('ordine')->get(['slug', 'nome']),
            'eventi' => $progetto->events()->limit(200)->get(),
            'appunti' => Appunto::with('allegati')->where('project_id', $progetto->id)->latest()->limit(200)->get(),
            'statoPc' => Cache::get('stato_pc', []),
            'appuntiElaborati' => Appunto::where('project_id', $progetto->id)->max('elaborato_at'),
            'appuntiInAttesa' => Appunto::where('project_id', $progetto->id)->whereNull('elaborato_at')->count(),
        ]);
    }

    /** POST /domande/{question} — spunta o toglie la spunta dal browser. */
    public function segna(Request $request, Question $question): JsonResponse
    {
        $fatto = $request->boolean('fatto');
        $question->update([
            'fatto' => $fatto,
            'fatto_il' => $fatto ? now()->toDateString() : null,
            'fatto_web' => true,
        ]);

        return response()->json(['ok' => true, 'fatto' => $question->fatto]);
    }
}
