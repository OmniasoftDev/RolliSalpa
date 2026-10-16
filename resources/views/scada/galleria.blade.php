{{-- Galleria di schermate: $galleria = [{file,titolo,data,url,presente}], $max opzionale (miniature senza didascalia) --}}
@php $voci = isset($max) ? array_slice($galleria, 0, $max) : $galleria; @endphp
@if ($voci)
    <div class="sc-galleria {{ isset($max) ? 'sc-mini' : '' }}">
        @foreach ($voci as $g)
            <figure>
                @if ($g['presente'])
                    <a href="{{ $g['url'] }}" target="_blank" rel="noopener"><img src="{{ $g['url'] }}" alt="{{ $g['titolo'] }}" loading="lazy"></a>
                @else
                    <div class="sc-manca">schermata non ancora caricata</div>
                @endif
                @unless (isset($max))
                    <figcaption>{{ $g['titolo'] }}@if ($g['data'])<span class="src">{{ $g['data'] }}</span>@endif</figcaption>
                @endunless
            </figure>
        @endforeach
    </div>
@endif
