@extends('layouts.app')

@section('titolo', 'Progetto 4.0 · '.$macchina->dato('num', '').' '.$macchina->dato('nome'))
@section('classe', 'p-'.$progetto->slug)

@php
    $stati = \App\Services\MappaProgetto::STATI;
    $st = fn ($s) => \App\Services\MappaProgetto::stato($s);
    $lamp = fn ($s, string $titolo = '') => '<span class="lamp s-'.\App\Services\MappaProgetto::stato($s).'" title="'.e(trim($titolo.' '.$stati[\App\Services\MappaProgetto::stato($s)][1])).'">'.$stati[\App\Services\MappaProgetto::stato($s)][0].'</span>';
    $fasi = collect($progetto->fasi ?? [])->filter(fn ($f) => is_array($f) && count($f) >= 2)->values();
    [$fatti, $tot] = $avanzamento;
    $attori = collect($p['attori'])->keyBy(fn ($a, $i) => (string) ($a['id'] ?? 'a'.$i));
    $tipi = \App\Services\MappaProgetto::TIPI;
    $righe = fn (string $t, int $per = 24) => array_slice(explode("\n", wordwrap($t, $per, "\n", true)), 0, 2);
    $reqChip = fn ($elenco) => collect((array) $elenco)->filter(fn ($r) => is_string($r))->map(fn ($r) => '<span class="req s-'.\App\Services\MappaProgetto::stato($p['requisiti'][$r]['stato'] ?? null).'" title="'.e($r.' '.($requisiti[$r] ?? '')).'">'.e($r).'</span>')->join(' ');
    $mail = fn (?string $c) => $c ? preg_replace_callback('/[\w.+-]+@[\w-]+\.[\w.-]+/', fn ($x) => '<a href="mailto:'.e($x[0]).'">'.e($x[0]).'</a>', e($c)) : '';
    $bidi = $p['bidirezionale'];
    $pos = $macchine->search(fn ($m) => $m->codice === $macchina->codice);
    $prima = $pos !== false && $pos > 0 ? $macchine[$pos - 1] : null;
    $dopo = $pos !== false && $pos < $macchine->count() - 1 ? $macchine[$pos + 1] : null;
@endphp

@section('contenuto')
<div class="wrap progetto-pagina">
    <nav class="pj-nav" aria-label="Macchine">
        <a class="link-azione" href="{{ route('progetti', $progetto->slug) }}">‹ Tutti i progetti</a>
        <a class="link-azione" href="{{ route('certificazione', $progetto->slug) }}#c-{{ $macchina->codice }}">Certificazione</a>
        <span class="pj-salti">
            @if ($prima)<a class="link-azione" href="{{ route('progetti.show', [$progetto->slug, $prima->codice]) }}">‹ {{ $prima->dato('num') }} {{ $prima->dato('nome') }}</a>@endif
            @if ($dopo)<a class="link-azione" href="{{ route('progetti.show', [$progetto->slug, $dopo->codice]) }}">{{ $dopo->dato('num') }} {{ $dopo->dato('nome') }} ›</a>@endif
        </span>
    </nav>

    <header class="head">
        <div>
            <h1><span class="num">{{ $macchina->dato('num', $macchina->codice) }}</span>{{ $macchina->dato('nome') }} · progetto 4.0</h1>
            <p>{{ $macchina->dato('fornitore') }}@if ($p['aggiornato']) · aggiornato il {{ \Illuminate\Support\Carbon::parse($p['aggiornato'])->format('d/m/Y') }}@endif</p>
        </div>
        <div class="gauges">
            <div class="gauge"><b>{{ $fatti }}/{{ $tot }}</b><span>passi fatti</span></div>
            <div class="gauge {{ $copertura['rischio'] ? 'alert' : '' }}"><b>{{ $copertura['ok'] }}/{{ count($requisiti) - $copertura['na'] }}</b><span>requisiti coperti</span></div>
            <div class="gauge"><b>{!! $lamp($bidi['stato'] ?? null) !!}</b><span>bidirezionale</span></div>
        </div>
    </header>

    <div class="phases pj-fasi-grandi">@foreach ($fasi as $f)<div class="phase">{!! $lamp($macchina->fase($f[0])) !!}{{ $f[1] }}</div>@endforeach</div>

    <div class="azioni">
        <a class="btn btn-sec" href="{{ route('pannello', $progetto->slug) }}?m={{ $macchina->codice }}">Scheda nel Quadro</a>
        <a class="btn btn-sec" href="{{ route('workflow', $progetto->slug) }}">Workflow</a>
        <a class="btn btn-sec" href="{{ route('compiti', $progetto->slug) }}">Persone e compiti</a>
        <a class="btn btn-sec" href="{{ route('pannello', $progetto->slug) }}?appunto">+ Appunto</a>
    </div>

    @include('credenziali.blocco', ['oggettoTipo' => 'macchina', 'oggettoCodice' => $macchina->codice])

    @php $scadaScheda = app(\App\Services\Scada::class)->scheda($progetto, $macchina); @endphp
    @if ($scadaScheda)@include('scada.blocco', ['s' => $scadaScheda])@endif

    @unless ($p['impostato'])
        <div class="card"><p class="empty">Il progetto 4.0 di questa macchina non è ancora impostato. Arriva dal PC (campo <span class="mono">progetto</span> della macchina) alla prossima pubblicazione.</p></div>
    @else
        @if ($p['sintesi'] || $p['principi'])
            <div class="brief">
                @if ($p['sintesi'])<b>In sintesi</b><span>{{ $p['sintesi'] }}</span>@endif
                @if ($p['principi'])<b>Principi decisi</b><ul class="pj-principi">@foreach ($p['principi'] as $x)<li>{{ $x }}</li>@endforeach</ul>@endif
            </div>
        @endif

        {{-- Mappa a blocchi e frecce: Oggi (visto sullo SCADA) e Obiettivo 4.0 --}}
        @if ($mappa['nodi'] || $p['oggi'] || $p['nodi'])
            <section class="card pj-mappa-card" id="mappa" aria-labelledby="t-mappa">
                <div class="pj-mappa-testa">
                    <h2 id="t-mappa">Mappa delle connessioni</h2>
                    @include('progetti.viste', ['p' => $p, 'vista' => $vista, 'base' => route('progetti.show', [$progetto->slug, $macchina->codice]), 'ancora' => '#mappa'])
                    <a class="btn btn-sec pj-btn-schermo" href="{{ route('progetti.mappa', [$progetto->slug, $macchina->codice]) }}?vista={{ $vista }}" target="_blank" rel="noopener">Apri a schermo intero ↗</a>
                </div>
                <p class="status-line">
                    @if ($vista === 'oggi')Com'è oggi, visto sullo SCADA{{ $p['oggi']['aggiornato'] ? ' il '.\Illuminate\Support\Carbon::parse($p['oggi']['aggiornato'])->format('d/m') : '' }}{{ $p['oggi']['fonte'] ? ' '.$p['oggi']['fonte'] : '' }}.@else Come deve essere per la perizia 4.0.@endif
                    Tocca un blocco o una freccia per i dettagli.
                </p>
                @if ($mappa['nodi'])
                    @include('progetti.mappa', ['mappa' => $mappa, 'slug' => $progetto->slug, 'codice' => $macchina->codice, 'titolo' => 'Mappa delle connessioni di '.$macchina->dato('nome'), 'impianto' => false])
                    <ol class="pj-collegamenti">
                        @foreach ($mappa['collegamenti'] as $k)
                            <li><button type="button" class="pj-riga-k" data-apri="k-{{ $k['id'] }}">{!! $lamp($k['stato']) !!}<span class="pj-num">{{ $k['numero'] }}</span><span><b>{{ $k['titolo'] ?? '' }}</b> <span class="src">{{ $k['nomeDa'] }} {{ $k['entrambi'] ? '⇄' : '→' }} {{ $k['nomeA'] }}@if (! empty($k['protocollo'])) · {{ $k['protocollo'] }}@endif</span></span></button></li>
                        @endforeach
                    </ol>
                @else
                    <p class="empty">Nessun blocco in questa vista.</p>
                @endif
            </section>
        @endif

        {{-- Chi fa cosa: a tutta larghezza, schede affiancate --}}
        <section class="card" aria-labelledby="t-attori">
            <h2 id="t-attori">Chi fa cosa <span class="conta">{{ $attori->count() }}</span></h2>
            <div class="pj-attori">
                @forelse ($attori as $a)
                    <div class="pj-attore st-{{ $st($a['stato'] ?? null) }}">
                        <div class="pj-attore-testa"><span class="pj-ruolo">{{ $a['ruolo'] ?? '' }}</span>{!! $lamp($a['stato'] ?? null) !!}</div>
                        <b>{{ $a['chi'] ?? 'da definire' }}</b>
                        @foreach ((array) ($a['persone'] ?? []) as $codice)
                            @if (isset($persone[$codice]))
                                @php $pe = $persone[$codice]; @endphp
                                <span class="pj-persona">{{ $pe->nome }}@if ($pe->ruolo) · {{ $pe->ruolo }}@endif @if ($pe->contatti)<span class="src">{!! $mail($pe->contatti) !!}</span>@endif</span>
                            @endif
                        @endforeach
                        @if (! empty($a['nota']))<span class="note">{{ $a['nota'] }}</span>@endif
                        @if (! empty($a['fonte']))<span class="src">{{ $a['fonte'] }}</span>@endif
                    </div>
                @empty
                    <p class="empty">Da definire.</p>
                @endforelse
            </div>
        </section>

        {{-- Anagrafica, a tutta larghezza --}}
            <section class="card" aria-labelledby="t-anagrafica">
                <h2 id="t-anagrafica">La macchina</h2>
                @if ($p['anagrafica'])
                    <dl class="kv pj-kv">@foreach ($p['anagrafica'] as $v)<div><dt>{{ $v['voce'] ?? '' }}</dt><dd>{{ $v['valore'] ?? '' }}@if (! empty($v['fonte'])) <span class="src">{{ $v['fonte'] }}</span>@endif</dd></div>@endforeach</dl>
                @else
                    <dl class="kv">@foreach (['Fornitore' => 'fornitore', 'IP' => 'ip', 'Protocollo' => 'protocollo', 'Requisiti' => 'requisiti'] as $k => $c)@if ($macchina->dato($c))<div><dt>{{ $k }}</dt><dd>{{ $macchina->dato($c) }}</dd></div>@endif @endforeach</dl>
                @endif
                @if ($bidi)
                    <div class="pj-bidi st-{{ $st($bidi['stato'] ?? null) }}">
                        <div class="pj-attore-testa"><span class="pj-ruolo">Bidirezionalità</span>{!! $lamp($bidi['stato'] ?? null) !!}</div>
                        <span>{{ $bidi['testo'] ?? '' }}</span>
                    </div>
                @endif
            </section>

        {{-- Dati in uscita e in ingresso --}}
        <div class="pj-due">
            @foreach (['uscite' => ['Dati in uscita', 'dalla macchina verso lo SCADA'], 'ingressi' => ['Dati in ingresso', 'verso la macchina, mai a mano']] as $chiave => [$titolo, $sotto])
                <section class="card" aria-labelledby="t-{{ $chiave }}">
                    <h2 id="t-{{ $chiave }}">{{ $titolo }} <span class="conta">{{ count($p[$chiave]) }}</span></h2>
                    <span class="status-line">{{ $sotto }}</span>
                    @forelse ($p[$chiave] as $d)
                        <div class="pj-dato">
                            {!! $lamp($d['stato'] ?? null) !!}
                            <div>
                                <b>{{ $d['dato'] ?? '' }}</b>@if (! empty($d['da'])) <span class="chip">da {{ $d['da'] }}</span>@endif
                                @if (! empty($d['dettaglio']))<span class="sub">{{ $d['dettaglio'] }}</span>@endif
                                <span class="pj-dato-meta">@if (! empty($d['variabile']))<span class="mono">{{ $d['variabile'] }}</span>@endif {!! $reqChip($d['req'] ?? []) !!}@if (! empty($d['fonte'])) <span class="src">{{ $d['fonte'] }}</span>@endif</span>
                            </div>
                        </div>
                    @empty
                        <p class="empty">Ancora da definire.</p>
                    @endforelse
                </section>
            @endforeach
        </div>

        {{-- Requisiti R1–R13 --}}
        <section class="card" aria-labelledby="t-req">
            <h2 id="t-req">Requisiti dell'Ing. Romano</h2>
            <div class="pj-copertura" role="img" aria-label="Copertura dei requisiti">
                @foreach ($copertura as $s => $n)@if ($n)<span class="s-{{ $s }}" style="flex: {{ $n }}" title="{{ $stati[$s][1] }}: {{ $n }}">{{ $n }}</span>@endif @endforeach
            </div>
            <div class="pj-requisiti">
                @foreach ($requisiti as $r => $nome)
                    @php $req = $p['requisiti'][$r] ?? []; @endphp
                    <div class="pj-req-box st-{{ $st($req['stato'] ?? null) }}">
                        <div class="pj-attore-testa"><span class="mono">{{ $r }}</span>{!! $lamp($req['stato'] ?? null) !!}</div>
                        <b>{{ $nome }}</b>
                        <span class="note">{{ $req['come'] ?? 'da definire' }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        @if ($p['strade'])
            <section class="card" aria-labelledby="t-strade">
                <h2 id="t-strade">Strade possibili</h2>
                <div class="pj-strade">
                    @foreach ($p['strade'] as $s)
                        <div class="pj-strada stato-{{ $s['stato'] ?? 'valutare' }}">
                            <div class="pj-attore-testa"><span class="pj-ruolo">Strada {{ $s['id'] ?? '' }}</span><span class="chip">{{ ['scelta' => 'scelta', 'scartata' => 'scartata'][$s['stato'] ?? ''] ?? 'da valutare' }}</span></div>
                            <b>{{ $s['titolo'] ?? '' }}</b>
                            <span class="note">{{ $s['testo'] ?? '' }}</span>
                            @if (! empty($s['collegamenti']))
                                <span class="src">Collegamenti: @foreach ($s['collegamenti'] as $kid)@php $k = collect($mappa['collegamenti'])->firstWhere('id', $kid); @endphp @if ($k)<button type="button" class="link-azione" data-apri="k-{{ $k['id'] }}">{{ $k['numero'] }}</button>@endif @endforeach</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endunless

    <div class="pj-due">
        <section class="card" aria-labelledby="t-aperti">
            <h2 id="t-aperti">Da chiarire</h2>
            @php $domande = $macchina->questions->where('fatto', false); @endphp
            @if ($p['aperti'] || $domande->isNotEmpty() || $compiti->isNotEmpty())
                <ul class="plain">
                    @foreach ($p['aperti'] as $x)<li class="note">{{ $x['testo'] ?? '' }} <span class="src">— {{ $x['chi'] ?? '' }}@if (! empty($x['quando'])) · {{ $x['quando'] }}@endif</span></li>@endforeach
                    @foreach ($domande as $q)<li class="note">{{ $q->cosa }} <span class="src">— domanda per {{ $q->chi }}</span></li>@endforeach
                    @foreach ($compiti as $c)<li class="note">{{ $c->testo }} <span class="src">— compito di {{ $persone[$c->persona]->nome ?? 'da assegnare' }}{{ $c->stato === 'proposto' ? ' · da confermare' : '' }}</span></li>@endforeach
                </ul>
            @else
                <p class="empty">Niente di aperto.</p>
            @endif
        </section>

        <section class="card" aria-labelledby="t-avanz">
            <h2 id="t-avanz">Avanzamento <span class="conta">{{ $fatti }}/{{ $tot }}</span></h2>
            <div class="pj-barra"><span style="width: {{ $tot ? round($fatti / $tot * 100) : 0 }}%"></span></div>
            @if ($p['avanzamento'])
                <ul class="plain pj-check">
                    @foreach ($p['avanzamento'] as $v)
                        <li class="{{ ! empty($v['fatto']) ? 'fatto' : '' }}"><span aria-hidden="true">{{ ! empty($v['fatto']) ? '☑' : '☐' }}</span> {{ $v['testo'] ?? '' }}@if (! empty($v['data'])) <span class="src">{{ \Illuminate\Support\Carbon::parse($v['data'])->format('d/m') }}</span>@endif</li>
                    @endforeach
                </ul>
            @else
                <p class="empty">Nessun passo definito.</p>
            @endif
        </section>
    </div>

    @if ($p['documenti'] || $storia->isNotEmpty())
        <div class="pj-due">
            @if ($p['documenti'])
                <section class="card"><h2>Documenti sul PC</h2>
                    <ul class="plain">@foreach ($p['documenti'] as $d)<li class="note">{{ $d['titolo'] ?? '' }} <span class="mono src">{{ $d['percorso'] ?? '' }}</span></li>@endforeach</ul>
                </section>
            @endif
            @if ($storia->isNotEmpty())
                <section class="card"><h2>Ultime novità</h2>@foreach ($storia as $e)@include('pannello.evento', ['e' => $e, 'tags' => []])@endforeach</section>
            @endif
        </div>
    @endif
</div>
@endsection

@push('script')
<script src="{{ asset('js/mappa.js') }}?v={{ filemtime(public_path('js/mappa.js')) }}"></script>
@endpush
