{{-- "Dati tecnici" della card pubblica: $tc da Pubblico::tecnici(), gia' filtrato e anonimizzato
     (niente note, fonti, dettaglio, credenziali, schermate; persone -> azienda). --}}
@php $st = \App\Services\MappaProgetto::STATI; @endphp
<details class="pb-tecnici">
    <summary>Dati tecnici</summary>
    <div class="pb-tecnici-corpo">
        @if ($tc['macchina'] || $tc['anagrafica'])
            <dl class="pb-dl">
                @foreach ($tc['macchina'] as $kv)<dt>{{ $kv[0] }}</dt><dd class="{{ $kv[0] === 'IP' ? 'mono' : '' }}">{{ $kv[1] }}</dd>@endforeach
                @foreach ($tc['anagrafica'] as $kv)<dt>{{ $kv[0] }}</dt><dd>{{ $kv[1] }}</dd>@endforeach
            </dl>
        @endif

        @if ($tc['scada'])
            <h3>Sullo SCADA <span class="sc-badge {{ $tc['scada']['classe'] }}">{{ $tc['scada']['etichetta'] }}</span>@if ($tc['scada']['visto'] !== '') <small>visto il {{ $tc['scada']['visto'] }}</small>@endif</h3>
            <dl class="pb-dl">
                @if ($tc['scada']['connessione'] !== '')<dt>Connessione</dt><dd>{{ $tc['scada']['connessione'] }}</dd>@endif
                @if ($tc['scada']['storico'] !== '')<dt>Storico</dt><dd>{{ $tc['scada']['storico'] }}</dd>@endif
                @if ($tc['scada']['pagine'])
                    <dt>Pagine</dt>
                    <dd>@foreach ($tc['scada']['pagine'] as $pg)<span class="pb-pagina-scada"><span class="mono">{{ $pg['nome'] }}</span>@if ($pg['data'] !== '') <small>{{ $pg['data'] }}</small>@endif</span>@endforeach</dd>
                @endif
            </dl>
            @if ($tc['scada']['variabili'])
                <div class="pb-tab-scroll">
                    <table class="pb-tab">
                        <caption>Variabili ({{ count($tc['scada']['variabili']) }})</caption>
                        <thead><tr><th>Nome</th><th>Valore</th><th>Tipo</th><th>Accesso</th><th>Qualità</th></tr></thead>
                        <tbody>
                            @foreach ($tc['scada']['variabili'] as $v)
                                <tr><td class="mono">{{ $v['nome'] }}</td><td class="mono">{{ $v['valore'] }}</td><td>{{ $v['tipo'] }}</td><td>{{ $v['accesso'] }}</td><td>{{ $v['qualita'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

        @foreach (['uscite' => 'Dati in uscita (dalla macchina)', 'ingressi' => 'Dati in ingresso (alla macchina)'] as $chiave => $titolo)
            @if ($tc[$chiave])
                <div class="pb-tab-scroll">
                    <table class="pb-tab">
                        <caption>{{ $titolo }}</caption>
                        <thead><tr><th>Dato</th>@if ($chiave === 'ingressi')<th>Da</th>@endif<th>Variabile</th><th>Req.</th><th>Stato</th></tr></thead>
                        <tbody>
                            @foreach ($tc[$chiave] as $u)
                                <tr><td>{{ $u['dato'] }}</td>@if ($chiave === 'ingressi')<td>{{ $u['da'] }}</td>@endif<td class="mono">{{ $u['variabile'] }}</td><td class="mono">{{ $u['req'] }}</td><td><span class="pb-stato-chip s-{{ $u['stato'] }}">{{ $st[$u['stato']][1] }}</span></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endforeach

        @if ($tc['nodi'])
            <div class="pb-tab-scroll">
                <table class="pb-tab">
                    <caption>Sistemi e dispositivi</caption>
                    <thead><tr><th>Nome</th><th>Tipo</th><th>IP</th><th>Modello</th><th>Stato</th></tr></thead>
                    <tbody>
                        @foreach ($tc['nodi'] as $n)
                            <tr><td>{{ $n['nome'] }}</td><td>{{ $n['tipo'] }}</td><td class="mono">{{ $n['ip'] }}</td><td>{{ $n['modello'] }}</td><td><span class="pb-stato-chip s-{{ $n['stato'] }}">{{ $st[$n['stato']][1] }}</span></td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($tc['collegamenti'])
            <h3>Collegamenti</h3>
            <ul class="pb-collegamenti">
                @foreach ($tc['collegamenti'] as $k)
                    <li>
                        <span class="pb-stato-chip s-{{ $k['stato'] }}">{{ $st[$k['stato']][1] }}</span>
                        <b>{{ $k['titolo'] }}</b>
                        @if ($k['da'] !== '' || $k['a'] !== '')<span class="pb-verso">{{ $k['da'] }} {{ $k['entrambi'] ? '↔' : '→' }} {{ $k['a'] }}</span>@endif
                        @if ($k['protocollo'] !== '')<span class="pb-chi">Protocollo: {{ $k['protocollo'] }}</span>@endif
                        @if ($k['dati'] !== '')<span class="pb-chi">Dati: {{ $k['dati'] }}</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</details>
