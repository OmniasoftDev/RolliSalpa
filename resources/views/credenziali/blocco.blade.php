{{--
    Cassaforte delle credenziali di un sistema o di una macchina (Francesco, 14/10/2026).
    Parametri: $progetto, $credenziali (null = tabelle non ancora importate), $oggettoTipo (sistema|macchina),
    $oggettoCodice, $promemoria (opzionale: campo "credenziali" del file del PC, solo cosa esiste, mai valori).
    I valori in chiaro non sono mai nella pagina: arrivano da "Mostra" (public/js/credenziali.js) dopo la password.
--}}
@php
    $promemoria = $promemoria ?? [];
    $azioni = \App\Models\CredenzialeAccesso::AZIONI;
@endphp
<details class="card credenziali" id="credenziali" data-conferma="{{ route('credenziali.conferma') }}">
    <summary><h2>Credenziali ({{ $credenziali?->count() ?? 0 }})</h2></summary>

    @if ($credenziali === null)
        <p class="errore">Cassaforte non ancora attiva: va importato <span class="mono">database/sql/2026-10-14-credenziali.sql</span> in phpMyAdmin.</p>
    @else
        <p class="src">Utente, password e note sono cifrati sul server. "Mostra" chiede la tua password (vale 15 minuti) e ogni accesso resta registrato.</p>

        @forelse ($credenziali as $c)
            <div class="cred" data-cred="{{ $c->id }}" data-mostra="{{ route('credenziali.mostra', $c->id) }}" data-url="{{ route('credenziali.aggiorna', $c->id) }}">
                <div class="cred-testa">
                    <b>{{ $c->etichetta }}</b>
                    @if ($c->indirizzo)<span class="mono">{{ $c->indirizzo }}</span>@endif
                    <span class="src">modificata il {{ $c->updated_at?->format('d/m/Y H:i') }}</span>
                </div>
                <dl class="kv cred-valori">
                    <div><dt>Utente</dt><dd class="mono" data-campo="utente" data-mascherato="{{ $c->utenteMascherato() }}">{{ $c->utenteMascherato() }}</dd></div>
                    <div><dt>Password</dt><dd class="mono" data-campo="segreto" data-mascherato="{{ $c->segreto !== null ? '••••••••' : '—' }}">{{ $c->segreto !== null ? '••••••••' : '—' }}</dd></div>
                    <div><dt>Note</dt><dd data-campo="note" data-mascherato="{{ $c->note !== null ? '(nascoste)' : '—' }}">{{ $c->note !== null ? '(nascoste)' : '—' }}</dd></div>
                    @if ($c->fonte)<div><dt>Fonte</dt><dd>{{ $c->fonte }}</dd></div>@endif
                </dl>
                <div class="azioni">
                    <button type="button" class="btn btn-sec" data-cred-mostra>Mostra</button>
                    <button type="button" class="btn btn-sec" data-cred-copia>Copia password</button>
                    <button type="button" class="link-azione" data-cred-modifica>Modifica</button>
                    <button type="button" class="link-elimina" data-cred-elimina="{{ route('credenziali.elimina', $c->id) }}" data-etichetta="{{ $c->etichetta }}">Elimina</button>
                    <span class="src" data-cred-stato aria-live="polite"></span>
                </div>
                <form class="cred-form" data-modifica hidden>
                    @include('credenziali.campi', ['c' => $c, 'prefisso' => 'cm'.$c->id])
                    <div class="azioni"><button type="submit" class="btn">Salva modifiche</button><button type="button" class="link-azione" data-cred-annulla>Annulla</button></div>
                </form>
                @if ($c->ultimiAccessi->isNotEmpty())
                    <details class="cred-accessi">
                        <summary class="src">Ultimi accessi</summary>
                        <ul class="plain">
                            @foreach ($c->ultimiAccessi as $acc)
                                <li class="src">{{ $acc->created_at?->format('d/m/Y H:i') }} · {{ $azioni[$acc->azione] ?? $acc->azione }} · {{ $acc->user?->name ?? '—' }} · {{ $acc->ip }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        @empty
            <p class="empty">Nessuna credenziale salvata.</p>
        @endforelse

        <details class="cred-nuova">
            <summary class="btn btn-sec">+ Aggiungi credenziale</summary>
            <form class="cred-form" data-nuova data-url="{{ route('credenziali.store', $progetto->slug) }}">
                <input type="hidden" name="oggetto_tipo" value="{{ $oggettoTipo }}">
                <input type="hidden" name="oggetto_codice" value="{{ $oggettoCodice }}">
                @include('credenziali.campi', ['c' => null, 'prefisso' => 'cn'])
                <div class="azioni"><button type="submit" class="btn">Salva credenziale</button></div>
            </form>
        </details>

        <dialog class="cred-dialogo" id="cred-dialogo">
            <form method="dialog" class="cred-form" id="cred-conferma">
                <h3>Conferma la password</h3>
                <p class="src">Per vedere le credenziali serve la password con cui entri nel sito. Vale 15 minuti.</p>
                <label for="cred-password" class="campo">Password <input type="password" id="cred-password" name="password" class="sel" autocomplete="current-password" required></label>
                <p class="errore" id="cred-conferma-errore" aria-live="polite"></p>
                <div class="azioni"><button type="submit" class="btn" value="ok">Conferma</button><button type="button" class="link-azione" data-cred-chiudi>Annulla</button></div>
            </form>
        </dialog>
    @endif

    @if ($promemoria)
        <h3>Note dal PC</h3>
        <ul class="plain">
            @foreach ($promemoria as $x)
                <li class="note">{{ $x['oggetto'] ?? '' }}@if (! empty($x['stato'])) <span class="chip">{{ $x['stato'] }}</span>@endif @if (! empty($x['nota'])) <span class="src">{{ $x['nota'] }}</span>@endif</li>
            @endforeach
        </ul>
    @endif
</details>

@if ($credenziali !== null)
    @push('script')
    <script src="{{ asset('js/credenziali.js') }}?v={{ filemtime(public_path('js/credenziali.js')) }}"></script>
    @endpush
@endif
