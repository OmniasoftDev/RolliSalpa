{{-- Compito assegnato dentro il box di una persona: scadenza, macchine, fonte e modulo per chiuderlo o modificarlo. --}}
<div class="compito {{ $c->scaduto() ? 'scaduto' : '' }}" data-compito="{{ route('compiti.aggiorna', $c) }}">
    <div class="t">{{ $c->testo }}</div>
    <div class="meta">
        @if ($c->scadenza)<span class="chip {{ $c->scaduto() ? 'chip-rischio' : '' }}">{{ $c->scaduto() ? 'scaduto il' : 'entro il' }} {{ $c->scadenza->format('d/m') }}</span>@endif
        @if ($tagMacchine($c->macchine))<span class="tag">{{ $tagMacchine($c->macchine) }}</span>@endif
        @if ($c->fonte)<span class="src">{{ $c->fonte }}</span>@endif
        @if ($c->assegnato_il)<span class="src">assegnato il {{ $c->assegnato_il->format('d/m') }}</span>@endif
    </div>
    <details class="chiudi">
        <summary class="link-azione">Chiudi o modifica</summary>
        <div class="riga-campi">
            <label class="campo" for="ca-{{ $c->id }}">A chi
                <select id="ca-{{ $c->id }}" class="sel" data-campo="persona">{!! $opzioniPersone($c->persona) !!}</select>
            </label>
            <label class="campo" for="cd-{{ $c->id }}">Entro il <input type="date" id="cd-{{ $c->id }}" class="sel" data-campo="scadenza" value="{{ $c->scadenza?->format('Y-m-d') }}"></label>
        </div>
        <label class="campo" for="ce-{{ $c->id }}">Esito (facoltativo)
            <textarea id="ce-{{ $c->id }}" rows="2" data-campo="esito" placeholder="Es.: tabella arrivata con mail del 07/10.">{{ $c->esito }}</textarea>
        </label>
        <div class="azioni">
            <button type="button" class="btn" data-azione="fatto">Fatto</button>
            <button type="button" class="btn btn-sec" data-azione="aperto">Salva modifiche</button>
            <button type="button" class="link-elimina" data-azione="annullato">Annulla compito</button>
            <span class="err" aria-live="polite"></span>
        </div>
    </details>
</div>
