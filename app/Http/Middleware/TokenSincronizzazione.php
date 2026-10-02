<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Protegge le API chiamate dallo script sul PC: header "Authorization: Bearer <token>". */
class TokenSincronizzazione
{
    public function handle(Request $request, Closure $next): Response
    {
        $atteso = (string) config('pannello.token');
        if ($atteso === '' || strlen($atteso) < 32) {
            return response()->json(['errore' => 'PANNELLO_SYNC_TOKEN non impostato (almeno 32 caratteri) nel .env del server.'], 503);
        }
        if (! hash_equals($atteso, (string) $request->bearerToken())) {
            return response()->json(['errore' => 'Token non valido.'], 401);
        }

        return $next($request);
    }
}
