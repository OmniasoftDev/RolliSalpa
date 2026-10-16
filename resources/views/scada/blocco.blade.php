{{-- Blocco "Sullo SCADA" della pagina progetto: $s (scheda Scada) --}}
<section class="card sc-blocco" id="scada" aria-labelledby="t-scada">
    <h2 id="t-scada">Sullo SCADA <span class="sc-badge {{ $s['classe'] }}">{{ $s['etichetta'] }}</span></h2>
    <p class="sc-meta">
        @if ($s['connessione'])<b>{{ $s['connessione'] }}</b>@endif
        @if ($s['visto'])<span class="src">visto il {{ \App\Services\Scada::giornoMese($s['visto']) }}{{ $s['fonte'] ? ' '.$s['fonte'] : '' }}</span>@endif
    </p>
    @if ($s['sintesi'])<p>{{ $s['sintesi'] }}</p>@endif

    @if ($s['variabili'])
        @php $tante = count($s['variabili']) > 15; @endphp
        <h3>Variabili lette <span class="conta">{{ count($s['variabili']) }}</span></h3>
        @foreach ($tante ? [array_slice($s['variabili'], 0, 15), array_slice($s['variabili'], 15)] : [$s['variabili']] as $i => $blocco)
            @if ($i === 1)<details class="sc-altre"><summary>mostra tutte ({{ count($s['variabili']) }})</summary>@endif
            <div class="tabella-scroll"><table class="sc-tab">
                @if ($i === 0)<thead><tr><th>Nome</th><th>Valore</th><th>Tipo</th><th>Accesso</th><th>Qualità</th><th>Nota</th></tr></thead>@endif
                <tbody>
                    @foreach ($blocco as $v)
                        <tr><td class="mono">{{ $v['nome'] }}</td><td class="mono">{{ $v['valore'] }}</td><td>{{ $v['tipo'] }}</td><td>{{ $v['accesso'] }}</td>
                            <td class="{{ $v['buona'] ? '' : 'sc-cattiva' }}">{{ $v['qualita'] }}</td><td>{{ $v['nota'] }}</td></tr>
                    @endforeach
                </tbody>
            </table></div>
            @if ($i === 1)</details>@endif
        @endforeach
    @endif

    @if ($s['storico'])<h3>Storico</h3><p>{{ $s['storico'] }}</p>@endif

    @if ($s['pagine'])
        <h3>Pagine dello SCADA</h3>
        <ul class="plain">@foreach ($s['pagine'] as $pg)<li class="note"><span class="mono">{{ $pg['nome'] }}</span>@if ($pg['data']) <span class="src">{{ $pg['data'] }}</span>@endif @if ($pg['nota'])— {{ $pg['nota'] }}@endif</li>@endforeach</ul>
    @endif

    @if ($s['schermate'])
        <h3>Schermate <span class="conta">{{ count($s['schermate']) }}</span></h3>
        @include('scada.galleria', ['galleria' => $s['schermate']])
    @endif

    @if ($s['domande'])
        <h3>Da chiedere a Edica</h3>
        <ul>@foreach ($s['domande'] as $d)<li>{{ $d }}</li>@endforeach</ul>
    @endif
</section>
