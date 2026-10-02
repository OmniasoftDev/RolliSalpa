<div class="ev">
    <div class="meta">
        <span class="chip">{{ $e->tipo ?: 'nota' }}</span>
        <span class="mono">{{ $e->quando() }}</span>
        <span>{{ $e->chi }}</span>
        @if (count($tags))<span class="tag">{{ collect($tags)->map(fn ($t) => '#'.$t)->join(' ') }}</span>@endif
    </div>
    <div class="note">{{ $e->testoMostrato() }}</div>
</div>
