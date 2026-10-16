{{--
    Mappa a blocchi e frecce (Progetti 4.0, schermo intero, Mappa impianto).
    Parametri: $mappa (MappaProgetto::mappa o ::impianto), $slug, $codice (macchina delle schermate; nell'impianto
    ogni nodo porta il suo), $titolo (per l'accessibilita'), $impianto (bool: link alla pagina della macchina).
--}}
@php
    $stati = \App\Services\MappaProgetto::STATI;
    $tipi = \App\Services\MappaProgetto::TIPI;
    $legenda = \App\Services\MappaProgetto::LEGENDA_FILI;
    $impianto = $impianto ?? false;
    $righe = fn (string $t, int $per = 24) => array_slice(explode("\n", wordwrap($t, $per, "\n", true)), 0, 2);
    $lamp = fn ($s) => '<span class="lamp s-'.\App\Services\MappaProgetto::stato($s).'">'.$stati[\App\Services\MappaProgetto::stato($s)][0].'</span>';
    $frase = fn ($s) => $legenda[$s] ?? $legenda['na'];
    $sch = function (array $x) use ($slug, $codice) {
        $f = is_string($x['schermata'] ?? null) ? \App\Services\Scada::nomeFile($x['schermata']) : null;
        return $f ? route('schermate.file', [$slug, $x['codice'] ?? $codice, $f]) : null;
    };
    $lista = fn ($v) => array_values(array_filter((array) ($v ?? []), 'is_string'));
    $W = $mappa['larghezza'] ?? \App\Services\MappaProgetto::LARGHEZZA;
@endphp
<div class="pj-mappa-griglia" data-mappa>
    <div class="pj-mappa-scroll">
        <svg class="pj-mappa {{ $impianto ? 'pj-mappa-impianto' : '' }}" viewBox="0 0 {{ $W }} {{ $mappa['altezza'] }}" style="min-width: {{ $impianto ? min($W, 2400) : 720 }}px" role="group" aria-label="{{ $titolo }}">
            <defs>
                @foreach (array_keys($stati) as $s)
                    <marker id="fr-{{ $s }}" class="mk s-{{ $s }}" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z"/></marker>
                @endforeach
            </defs>
            @foreach ($mappa['livelli'] as $l)
                <rect class="pj-fascia" x="0" y="{{ $l['y'] - 26 }}" width="{{ $W }}" height="{{ $l['h'] ?? 98 }}" rx="6"/>
                <text class="pj-fascia-nome" x="10" y="{{ $l['y'] - 11 }}">{{ $l['nome'] }}</text>
            @endforeach
            @foreach ($mappa['colonne'] ?? [] as $c)
                <text class="pj-colonna" x="{{ $c['x'] + 98 }}" y="{{ $mappa['altezza'] - 8 }}" text-anchor="middle">{{ $c['num'] }} {{ \Illuminate\Support\Str::limit($c['nome'], 26) }}</text>
            @endforeach
            @foreach ($mappa['collegamenti'] as $k)
                <g class="pj-k st-{{ $k['stato'] }}" tabindex="0" role="button" @if ($impianto) data-macchine="{{ $k['codice'] ?? '' }}" @endif data-dett="k-{{ $k['id'] }}" data-da="{{ $k['da'] }}" data-a="{{ $k['a'] }}" aria-label="Collegamento {{ $k['numero'] }}: {{ $k['nomeDa'] }} verso {{ $k['nomeA'] }}, {{ $k['titolo'] ?? '' }}, {{ $frase($k['stato']) }}">
                    <path class="pj-filo-tocco" d="{{ $k['path'] }}"/>
                    <path class="pj-filo" d="{{ $k['path'] }}" marker-end="url(#fr-{{ $k['stato'] }})" @if ($k['entrambi']) marker-start="url(#fr-{{ $k['stato'] }})" @endif/>
                </g>
            @endforeach
            @foreach ($mappa['collegamenti'] as $k)
                <g class="pj-k st-{{ $k['stato'] }}" @if ($impianto) data-macchine="{{ $k['codice'] ?? '' }}" @endif data-dett="k-{{ $k['id'] }}" data-da="{{ $k['da'] }}" data-a="{{ $k['a'] }}" aria-hidden="true">
                    <circle cx="{{ $k['lx'] }}" cy="{{ $k['ly'] }}" r="12"/>
                    <text x="{{ $k['lx'] }}" y="{{ $k['ly'] + 4 }}" text-anchor="middle">{{ $k['stato'] === 'verificare' ? '?' : $k['numero'] }}</text>
                </g>
            @endforeach
            @foreach ($mappa['nodi'] as $n)
                @php $sotto = trim(implode(' · ', array_filter([$n['modello'] ?? null, $n['ip'] ?? null], 'is_string'))); @endphp
                <g class="pj-nodo t-{{ $n['tipo'] ?? 'altro' }} st-{{ $n['stato'] }} {{ ! empty($n['condiviso']) ? 'condiviso' : '' }}" @if ($impianto) data-macchine="{{ implode(' ', $n['macchine'] ?? []) }}" @endif tabindex="0" role="button" data-dett="n-{{ $n['id'] }}" data-nodo="{{ $n['id'] }}" aria-label="{{ $n['nome'] ?? $n['id'] }}: {{ $stati[$n['stato']][1] }}">
                    <rect x="{{ $n['x'] }}" y="{{ $n['y'] }}" width="{{ $n['w'] }}" height="{{ $n['h'] }}" rx="8"/>
                    <text class="pj-tipo" x="{{ $n['x'] + 12 }}" y="{{ $n['y'] + 16 }}">{{ $tipi[$n['tipo'] ?? ''] ?? 'Sistema' }}@if ($sotto) · {{ \Illuminate\Support\Str::limit($sotto, 22) }}@endif</text>
                    @foreach ($righe((string) ($n['nome'] ?? $n['id'])) as $i => $riga)
                        <text class="pj-nome" x="{{ $n['x'] + 12 }}" y="{{ $n['y'] + 33 + $i * 16 }}">{{ $riga }}</text>
                    @endforeach
                    <circle class="pj-pallino" cx="{{ $n['x'] + $n['w'] - 14 }}" cy="{{ $n['y'] + 14 }}" r="7"/>
                    <text class="pj-pallino-t" x="{{ $n['x'] + $n['w'] - 14 }}" y="{{ $n['y'] + 18 }}" text-anchor="middle">{{ $stati[$n['stato']][0] }}</text>
                </g>
            @endforeach
        </svg>
    </div>
    <aside class="pj-dettagli" aria-live="polite">
        <div class="pj-dett pj-vuoto"><p class="empty">Tocca un blocco per vedere cos'è e i suoi collegamenti, o una freccia per i dati che passano.</p></div>
        @foreach ($mappa['nodi'] as $n)
            @php $url = $sch($n); $vars = $lista($n['variabili'] ?? null); @endphp
            <div class="pj-dett" id="n-{{ $n['id'] }}" hidden>
                <span class="chip">{{ $tipi[$n['tipo'] ?? ''] ?? 'Sistema' }}</span>
                <h3>{{ $n['nome'] ?? $n['id'] }}</h3>
                <p>{!! $lamp($n['stato']) !!} {{ $stati[$n['stato']][1] }}</p>
                @if (! empty($n['modello']) || ! empty($n['ip']))<p class="mono">{{ implode(' · ', array_filter([$n['modello'] ?? null, $n['ip'] ?? null], 'is_string')) }}</p>@endif
                @if (! empty($n['dettaglio']))<p>{{ $n['dettaglio'] }}</p>@endif
                @if ($vars)<details class="pj-vars"><summary>{{ count($vars) }} variabili</summary><ul>@foreach ($vars as $v)<li class="mono">{{ $v }}</li>@endforeach</ul></details>@endif
                @if ($url)<a href="{{ $url }}" target="_blank" rel="noopener"><img class="pj-sch" src="{{ $url }}" alt="Schermata {{ $n['schermata'] }}" loading="lazy"></a>@endif
                @php $suoi = collect($mappa['collegamenti'])->filter(fn ($k) => $k['da'] === $n['id'] || $k['a'] === $n['id']); @endphp
                @if ($suoi->isNotEmpty())
                    <ul class="plain pj-lista-k">@foreach ($suoi as $k)<li><button type="button" class="link-azione" data-apri="k-{{ $k['id'] }}">{{ $k['numero'] }}. {{ $k['titolo'] ?? '' }}</button> <span class="src">{{ $k['da'] === $n['id'] ? '→ '.$k['nomeA'] : '← '.$k['nomeDa'] }}</span></li>@endforeach</ul>
                @endif
                @if ($impianto && empty($n['condiviso']) && ! empty($n['codice']))<p><a class="link-azione" href="{{ route('progetti.show', [$slug, $n['codice']]) }}?vista={{ request('vista', 'oggi') }}#mappa">Pagina della macchina ›</a></p>@endif
                @if ($impianto && ! empty($n['condiviso']))<p class="src">Usato da {{ count($n['macchine'] ?? []) }} macchine</p>@endif
                @if (! empty($n['fonte']))<p class="src">Fonte: {{ $n['fonte'] }}</p>@endif
            </div>
        @endforeach
        @foreach ($mappa['collegamenti'] as $k)
            @php $url = $sch($k); @endphp
            <div class="pj-dett" id="k-{{ $k['id'] }}" hidden>
                <span class="chip">Collegamento {{ $k['numero'] }}@if ($impianto && ! empty($k['macchinaNum'])) · macchina {{ $k['macchinaNum'] }}@endif</span>
                <h3>{{ $k['titolo'] ?? '' }}</h3>
                <p>{{ $k['nomeDa'] }} {{ $k['entrambi'] ? '⇄' : '→' }} {{ $k['nomeA'] }}</p>
                <p>{!! $lamp($k['stato']) !!} {{ ucfirst($frase($k['stato'])) }}</p>
                @if (! empty($k['protocollo']) || isset($k['verso']))<p><span class="mono">{{ $k['protocollo'] ?? '' }}</span>@if (! empty($k['protocollo'])) · @endif{{ $k['entrambi'] ? 'nei due sensi (lettura e scrittura)' : 'in un senso' }}</p>@endif
                @if ($k['dati'])<ul class="pj-dati">@foreach ($k['dati'] as $d)<li>{{ $d }}</li>@endforeach</ul>@endif
                @if ($k['variabili'])<details class="pj-vars"><summary>{{ count($k['variabili']) }} variabili</summary><ul>@foreach ($k['variabili'] as $v)<li class="mono">{{ $v }}</li>@endforeach</ul></details>@endif
                @if (! empty($k['dettaglio']))<p>{{ $k['dettaglio'] }}</p>@endif
                @if (! empty($k['nota']))<p>{{ $k['nota'] }}</p>@endif
                @if ($url)<a href="{{ $url }}" target="_blank" rel="noopener"><img class="pj-sch" src="{{ $url }}" alt="Schermata {{ $k['schermata'] }}" loading="lazy"></a>@endif
                @if ($impianto && ! empty($k['codice']))<p><a class="link-azione" href="{{ route('progetti.show', [$slug, $k['codice']]) }}?vista={{ request('vista', 'oggi') }}#mappa">Pagina della macchina ›</a></p>@endif
                @if (! empty($k['fonte']))<p class="src">Fonte: {{ $k['fonte'] }}</p>@endif
            </div>
        @endforeach
    </aside>
</div>
<div class="pj-legenda-fili" aria-label="Legenda delle frecce">
    @foreach ($legenda as $s => $testo)
        <span class="pj-k st-{{ $s }}"><svg width="44" height="12" aria-hidden="true"><path class="pj-filo" d="M2 6 H40"/></svg>{{ $testo }}</span>
    @endforeach
    <span>⇄ nei due sensi · ? da verificare</span>
</div>
