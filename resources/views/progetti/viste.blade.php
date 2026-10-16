{{-- Selettore Oggi | Obiettivo 4.0 della mappa. Parametri: $p (progetto della macchina, o null nell'impianto), $vista, $base (url senza query), $ancora. --}}
<span class="pj-viste" role="group" aria-label="Vista della mappa">
    @foreach (\App\Services\MappaProgetto::VISTE as $v => $nome)
        @if ($v === 'oggi' && $p !== null && $p['oggi'] === null)
            <span class="pj-vista off" title="Vista non ancora compilata">{{ $nome }}</span>
        @else
            <a class="pj-vista" href="{{ $base }}?vista={{ $v }}{{ $ancora ?? '' }}" @if ($vista === $v) aria-current="true" @endif>{{ $nome }}</a>
        @endif
    @endforeach
</span>
