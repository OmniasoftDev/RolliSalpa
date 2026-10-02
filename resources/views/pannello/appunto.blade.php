<div class="ev appunto">
    <div class="meta">
        <span class="chip">{{ $a->etichettaTipo() }}</span>
        <span class="mono">{{ $a->created_at->format('d/m/Y H:i') }}</span>
        @if (count($a->macchine ?? []))
            <span class="tag">{{ collect($a->macchine)->map(fn ($c) => '#'.($numDi[$c] ?? $c))->join(' ') }}</span>
        @else
            <span class="tag">#generale</span>
        @endif
        @if ($a->elaborato_at)
            <span class="stato ok" title="Registrato nei file del progetto il {{ $a->elaborato_at->format('d/m/Y H:i') }}">elaborato</span>
        @else
            <span class="stato attesa">da elaborare</span>
        @endif
    </div>
    <div class="note testo-appunto">{{ $a->testo }}</div>
    @if ($a->allegati->isNotEmpty())
        <div class="miniature">
            @foreach ($a->allegati as $f)
                @if ($f->immagine())
                    <a href="{{ route('allegati.file', $f) }}" target="_blank" rel="noopener"><img src="{{ route('allegati.file', $f) }}" alt="{{ $f->nome }}" loading="lazy"></a>
                @else
                    <a class="file" href="{{ route('allegati.file', $f) }}" target="_blank" rel="noopener">{{ $f->nome }}</a>
                @endif
            @endforeach
        </div>
    @endif
    @unless ($a->elaborato_at)
        <div><button type="button" class="link-elimina" data-elimina="{{ route('appunti.destroy', $a) }}">Elimina</button></div>
    @endunless
</div>
