<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\MappaProgetto;
use App\Services\Pubblico;
use Illuminate\Http\Response;

/**
 * Area pubblica "stato avanzamento" (Francesco, 23/10/2026): sola lettura, senza login, con il
 * link segreto /{slug}/avanzamento/{chiave}. Chiave sbagliata o area non attiva: 404.
 */
class PubblicoController extends Controller
{
    /** GET /{slug}/avanzamento/{chiave} */
    public function show(string $slug, string $chiave, Pubblico $pubblico): Response
    {
        $progetto = Project::where('slug', $slug)->first();
        if (! $progetto || ! Pubblico::attivo($progetto) || ! Pubblico::chiaveValida($progetto, $chiave)) {
            abort(404);
        }

        return response()
            ->view('pubblico.show', $pubblico->pagina($progetto) + ['slug' => $progetto->slug, 'stati' => MappaProgetto::STATI])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
