<label class="ask {{ $q->fatto ? 'done' : '' }}" for="q{{ $q->id }}-{{ $dove }}">
    <input type="checkbox" id="q{{ $q->id }}-{{ $dove }}" data-q="{{ $q->id }}" data-url="{{ route('domande.segna', $q) }}" @checked($q->fatto)>
    <span><span class="t">{{ $q->cosa }}</span> <span class="tag">{{ $tag }}</span>
        @if ($q->risposta)<span class="sub">{{ $q->risposta }}</span>@endif
        <span class="err" aria-live="polite"></span>
    </span>
</label>
