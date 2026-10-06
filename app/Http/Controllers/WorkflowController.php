<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Workflow;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** "Workflow": calendario a mese e timeline per macchina con fasi, appuntamenti, Google Calendar, scadenze e mail. */
class WorkflowController extends Controller
{
    /** GET /{slug}/workflow?mese=2026-10 */
    public function show(Request $request, string $slug, Workflow $workflow): View
    {
        $progetto = Project::where('slug', $slug)->firstOrFail();
        $mese = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('mese')) ? Carbon::createFromFormat('!Y-m', $request->query('mese')) : today()->startOfMonth();
        $voci = $workflow->voci($progetto);

        return view('workflow.show', [
            'progetto' => $progetto,
            'progetti' => Project::orderBy('ordine')->get(['slug', 'nome']),
            'macchine' => $progetto->machines,
            'voci' => $voci,
            'mese' => $mese,
            'intervallo' => $workflow->intervallo($progetto, $voci),
            'googleLetto' => $workflow->googleLetto(),
            'tipi' => Workflow::TIPI,
        ]);
    }
}
