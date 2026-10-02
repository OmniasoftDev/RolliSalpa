<?php

namespace App\Http\Controllers;

use App\Models\Controllo;
use App\Models\MailRegistro;
use App\Models\Project;
use App\Services\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistroController extends Controller
{
    /** GET /registro — controlli orari, quadratura e tutte le mail di progetto da leggere. */
    public function show(Registro $registro): View
    {
        return view('registro.show', [
            'progetti' => Project::orderBy('ordine')->get(['slug', 'nome']),
            'problemi' => $registro->problemi(),
            'griglia' => $registro->griglia(7),
            'ultimo' => Controllo::orderByDesc('inizio')->first(),
            'controlli' => Controllo::orderByDesc('inizio')->limit(40)->get(),
            'mail' => MailRegistro::orderByDesc('ricevuta')->limit(300)->get(),
        ]);
    }

    /** POST /mail/{mail}/vista — Francesco ha letto la mail (o la rimette tra le non lette). */
    public function vista(Request $request, MailRegistro $mail): JsonResponse
    {
        $mail->update(['vista_at' => $request->boolean('vista', true) ? now() : null]);

        return response()->json(['ok' => true]);
    }

    /** POST /mail/viste — segna come lette tutte le mail. */
    public function tutteViste(): JsonResponse
    {
        return response()->json(['ok' => true, 'viste' => MailRegistro::whereNull('vista_at')->update(['vista_at' => now()])]);
    }
}
