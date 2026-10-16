@extends('layouts.app')

@section('titolo', 'Certificazione · '.$progetto->nome)
@section('classe', 'p-'.$progetto->slug)

@php
    $stati = \App\Services\MappaProgetto::STATI;
    $gm = fn ($d) => $d ? $d->format('d/m') : '';
    $nMacchine = $tutte->count();
    $nRischio = $tutte->where('rischio', true)->count();
    $nSenzaDati = $tutte->where('senzaDati', true)->count();
    $mediaPct = $nMacchine ? (int) round($tutte->avg(fn ($r) => $r['scheda']['percentuale'] ?? 0)) : 0;
@endphp

@section('contenuto')
<div class="wrap cert-pagina">
    <header class="head">
        <div>
            <h1>Certificazione 4.0 · {{ $progetto->nome }}</h1>
            <p>Il cammino di ogni macchina verso la perizia dell'Ing. Romano, a tappe con le date: cosa è fatto, cosa è previsto, cosa manca e chi lo deve fare.</p>
        </div>
        <div class="gauges">
            @if ($giorni !== null)
                <div class="gauge {{ $giorni < 15 ? 'alert' : '' }}"><b>{{ $giorni }}</b><span>giorni al {{ \Illuminate\Support\Carbon::parse($scadenza)->format('d/m') }}</span></div>
            @endif
            <div class="gauge"><b>{{ $mediaPct }}%</b><span>avanzamento medio</span></div>
            <div class="gauge {{ $nRischio ? 'alert' : '' }}"><b>{{ $nRischio }}/{{ $nMacchine }}</b><span>a rischio</span></div>
        </div>
    </header>

    @if ($riepilogo)
        <section class="cert-riepilogo" aria-label="Macchine che hanno superato ogni tappa">
            @foreach ($riepilogo as $t)
                <div class="cert-colonna" title="{{ $t['titolo'] }}: {{ $t['ok'] }} macchine su {{ $t['totale'] }}">
                    <div class="cert-colonna-barra"><span style="height: {{ $t['totale'] ? round($t['ok'] / $t['totale'] * 100) : 0 }}%"></span></div>
                    <b>{{ $t['ok'] }}<small>/{{ $t['totale'] }}</small></b>
                    <span>{{ $t['titolo'] }}</span>
                </div>
            @endforeach
        </section>
    @endif

    <div class="cert-barra-strumenti">
        <nav class="filters" aria-label="Filtro">
            <a class="btn {{ $filtro === '' ? '' : 'btn-sec' }}" href="{{ route('certificazione', $progetto->slug) }}">Tutte ({{ $nMacchine }})</a>
            <a class="btn {{ $filtro === 'rischio' ? '' : 'btn-sec' }}" href="{{ route('certificazione', $progetto->slug) }}?filtro=rischio">A rischio ({{ $nRischio }})</a>
            <a class="btn {{ $filtro === 'senzadati' ? '' : 'btn-sec' }}" href="{{ route('certificazione', $progetto->slug) }}?filtro=senzadati">Senza dati vivi ({{ $nSenzaDati }})</a>
        </nav>
        <div class="legend">@foreach (['ok', 'corso', 'verificare', 'attesa', 'rischio', 'no', 'na'] as $k)<span><i class="cert-pallino s-{{ $k }}"></i>{{ $stati[$k][1] }}</span>@endforeach</div>
    </div>

    <div class="cert-elenco">
        @forelse ($righe as $r)
            @php
                $m = $r['macchina'];
                $s = $r['scheda'];
                $p = $r['p'];
            @endphp
            <article class="cert-card {{ $r['rischio'] ? 'a-rischio' : '' }} {{ $s ? '' : 'vuota' }}" id="c-{{ $m->codice }}">
                <div class="cert-testa">
                    <div>
                        <h2><span class="num">{{ $m->dato('num', $m->codice) }}</span>{{ $m->dato('nome') }}</h2>
                        <span class="sub">{{ $m->dato('fornitore') }}</span>
                    </div>
                    @if ($s)
                        <div class="cert-pct">
                            <b>{{ $s['percentuale'] }}%</b>
                            <div class="pj-barra" role="img" aria-label="Avanzamento {{ $s['percentuale'] }} per cento"><span style="width: {{ $s['percentuale'] }}%"></span></div>
                            @if ($s['fineprevista'])<span class="sub">fine prevista {{ $s['fineprevista']->format('d/m/Y') }}</span>@endif
                        </div>
                    @endif
                </div>

                @if ($s)
                    @if ($s['sintesi'])<p class="cert-sintesi">{{ $s['sintesi'] }}</p>@endif
                    @include('scada.riga', ['s' => app(\App\Services\Scada::class)->scheda($progetto, $m), 'link' => route('progetti.show', [$progetto->slug, $m->codice]).'#scada'])
                    @if ($s['rischio'])<p class="cert-rischio"><b>Rischio</b> {{ $s['rischio'] }}</p>@endif

                    @if ($s['tappe'])
                        <ol class="cert-tappe" style="--n: {{ count($s['tappe']) }}">
                            @foreach ($s['tappe'] as $i => $t)
                                <li class="cert-tappa {{ $i <= $s['ultimaOk'] ? 'fatta' : '' }} {{ $i < $s['ultimaOk'] ? 'linea-piena' : '' }}">
                                    <details>
                                        <summary title="{{ $t['titolo'] }}: {{ $stati[$t['stato']][1] }}">
                                            <i class="cert-pallino s-{{ $t['stato'] }}">{{ $stati[$t['stato']][0] }}</i>
                                            <span class="cert-tappa-titolo">{{ $t['titolo'] }}</span>
                                            @if ($t['data'])
                                                <span class="cert-data">{{ $gm($t['data']) }}</span>
                                            @elseif ($t['previsto'])
                                                <span class="cert-data prevista">prev. {{ $gm($t['previsto']) }}</span>
                                            @else
                                                <span class="cert-data prevista">—</span>
                                            @endif
                                        </summary>
                                        <div class="cert-tappa-dettaglio">
                                            <b>{{ $stati[$t['stato']][1] }}</b>
                                            @if ($t['data'])<span>{{ $t['stato'] === 'ok' ? 'Fatto il' : 'Dal' }} {{ $t['data']->format('d/m/Y') }}</span>@endif
                                            @if ($t['previsto'])<span>Previsto {{ $t['previsto']->format('d/m/Y') }}</span>@endif
                                            @if ($t['nota'])<span>{{ $t['nota'] }}</span>@endif
                                            @if ($t['fonte'])<span class="src">{{ $t['fonte'] }}</span>@endif
                                        </div>
                                    </details>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                @else
                    <p class="cert-sintesi">Scheda certificazione non ancora compilata.</p>
                @endif

                <div class="pj-req" aria-label="Requisiti R1–R13">
                    @foreach ($requisiti as $rq => $nome)
                        @php $st = \App\Services\MappaProgetto::stato($p['requisiti'][$rq]['stato'] ?? null); @endphp
                        <span class="req s-{{ $st }}" title="{{ $rq }} {{ $nome }}: {{ $stati[$st][1] }}{{ ($p['requisiti'][$rq]['come'] ?? '') !== '' ? ' — '.$p['requisiti'][$rq]['come'] : '' }}">{{ $rq }}</span>
                    @endforeach
                </div>

                @if ($s && ($s['prossimo'] || $s['manca']))
                    <div class="cert-piede">
                        @if ($s['prossimo'])
                            <div class="next"><b>Prossimo passo</b>{{ $s['prossimo']['testo'] }}
                                @if ($s['prossimo']['chi'] || $s['prossimo']['entro'])
                                    <span class="cert-chi">{{ $s['prossimo']['chi'] }}@if ($s['prossimo']['entro']) · <span class="{{ $s['prossimo']['scaduto'] ? 'scaduto' : '' }}">entro {{ $s['prossimo']['entro']->format('d/m') }}</span>@endif</span>
                                @endif
                            </div>
                        @endif
                        @if ($s['manca'])
                            <div class="cert-manca">
                                <b>Cosa manca</b>
                                <ul>
                                    @foreach ($s['manca'] as $x)
                                        <li class="{{ $x['scaduto'] ? 'scaduto' : '' }}">{{ $x['testo'] }}
                                            @if ($x['chi'] || $x['entro'])<span class="cert-chi">{{ $x['chi'] }}@if ($x['entro']) · entro {{ $x['entro']->format('d/m') }}@endif</span>@endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="cert-link">
                    <a class="link-azione" href="{{ route('progetti.show', [$progetto->slug, $m->codice]) }}?vista=oggi#mappa">Mappa</a>
                    <a class="link-azione" href="{{ route('progetti.show', [$progetto->slug, $m->codice]) }}">Progetto 4.0</a>
                    <a class="link-azione" href="{{ route('pannello', $progetto->slug) }}?m={{ $m->codice }}">Scheda nel quadro</a>
                    @if ($s && $s['aggiornato'])<span class="src">aggiornata il {{ $s['aggiornato']->format('d/m/Y') }}</span>@endif
                </div>
            </article>
        @empty
            <p class="empty">Nessuna macchina {{ $filtro === 'rischio' ? 'a rischio' : ($filtro === 'senzadati' ? 'senza dati vivi' : '') }}.</p>
        @endforelse
    </div>
</div>
@endsection
