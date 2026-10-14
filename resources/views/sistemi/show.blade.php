@extends('layouts.app')

@section('titolo', 'Sistema · '.$sistema['nome'])
@section('classe', 'p-'.$progetto->slug)

@php
    $stati = \App\Services\MappaProgetto::STATI;
    $lamp = fn ($s) => '<span class="lamp s-'.\App\Services\MappaProgetto::stato($s).'" title="'.e($stati[\App\Services\MappaProgetto::stato($s)][1]).'">'.$stati[\App\Services\MappaProgetto::stato($s)][0].'</span>';
    $st = fn ($s) => \App\Services\MappaProgetto::stato($s);
    $tipi = \App\Http\Controllers\SistemiController::TIPI;
    $proprieta = \App\Http\Controllers\SistemiController::PROPRIETA;
    $codiciSistemi = array_column($sistemi, 'nome', 'codice');
@endphp

@section('contenuto')
<div class="wrap progetto-pagina sistema-pagina">
    <nav class="pj-nav" aria-label="Sistemi">
        <a class="link-azione" href="{{ route('sistemi', $progetto->slug) }}">‹ Tutti i sistemi</a>
        <span class="pj-salti">
            @foreach ($sistemi as $altro)
                @if ($altro['codice'] !== $sistema['codice'])<a class="link-azione" href="{{ route('sistemi.show', [$progetto->slug, $altro['codice']]) }}">{{ $altro['codice'] }} {{ $altro['nome'] }}</a>@endif
            @endforeach
        </span>
    </nav>

    <header class="head">
        <div>
            <h1><span class="num">{{ $sistema['codice'] }}</span>{{ $sistema['nome'] }}</h1>
            <p>{{ $tipi[$sistema['tipo']] ?? ($sistema['tipo'] ?: 'Sistema') }}@if ($sistema['proprieta']) · {{ $proprieta[$sistema['proprieta']] ?? $sistema['proprieta'] }}@endif @if ($sistema['gestore']) · gestito da {{ $sistema['gestore'] }}@endif @if ($sistema['aggiornato']) · aggiornato il {{ \Illuminate\Support\Carbon::parse($sistema['aggiornato'])->format('d/m/Y') }}@endif</p>
        </div>
        <div class="gauges">
            <div class="gauge"><b>{{ count($sistema['collegamenti']) }}</b><span>collegamenti</span></div>
            <div class="gauge"><b>{{ $foto->count() }}</b><span>foto dal sito</span></div>
            <div class="gauge"><b>{{ count($sistema['aperti']) }}</b><span>da chiarire</span></div>
        </div>
    </header>

    <div class="azioni">
        <button type="button" class="btn" data-apri-appunto="{{ $sistema['codice'] }}" data-tipo="screenshot">+ Aggiungi foto/screenshot</button>
        <a class="btn btn-sec" href="{{ route('progetti', $progetto->slug) }}">Progetti 4.0</a>
    </div>
    @include('pannello.appunto-form')

    @if ($sistema['sintesi'])
        <div class="brief"><b>In sintesi</b><span>{{ $sistema['sintesi'] }}</span></div>
    @endif

    @include('credenziali.blocco', ['oggettoTipo' => 'sistema', 'oggettoCodice' => $sistema['codice'], 'promemoria' => $sistema['credenziali']])

    <section class="card" aria-labelledby="t-dati">
        <h2 id="t-dati">Dati del sistema</h2>
        @if ($sistema['anagrafica'])
            <dl class="kv pj-kv">@foreach ($sistema['anagrafica'] as $v)<div><dt>{{ $v['voce'] ?? '' }}</dt><dd>{{ $v['valore'] ?? '' }}@if (! empty($v['fonte'])) <span class="src">{{ $v['fonte'] }}</span>@endif</dd></div>@endforeach</dl>
        @else
            <p class="empty">Ancora da raccogliere.</p>
        @endif
    </section>

    <section class="card" aria-labelledby="t-coll">
        <h2 id="t-coll">Collegamenti con le macchine <span class="conta">{{ count($sistema['collegamenti']) }}</span></h2>
        <div class="pj-attori">
            @forelse ($sistema['collegamenti'] as $k)
                <div class="pj-attore st-{{ $st($k['stato'] ?? null) }}">
                    <div class="pj-attore-testa">
                        <span class="pj-ruolo">
                            @forelse ((array) ($k['macchine'] ?? []) as $codice)
                                @if (is_string($codice) && isset($perCodice[$codice]))
                                    <a href="{{ route('progetti.show', [$progetto->slug, $codice]) }}">{{ $perCodice[$codice]->dato('num', $codice) }} {{ $perCodice[$codice]->dato('nome') }}</a>@if (! $loop->last) · @endif
                                @elseif (is_string($codice) && isset($codiciSistemi[$codice]))
                                    <a href="{{ route('sistemi.show', [$progetto->slug, $codice]) }}">{{ $codice }} {{ $codiciSistemi[$codice] }}</a>@if (! $loop->last) · @endif
                                @elseif (is_string($codice))
                                    {{ $codice }}@if (! $loop->last) · @endif
                                @endif
                            @empty
                                Generale
                            @endforelse
                        </span>
                        {!! $lamp($k['stato'] ?? null) !!}
                    </div>
                    <span class="note">{{ $k['testo'] ?? '' }}</span>
                    @if (! empty($k['fonte']))<span class="src">{{ $k['fonte'] }}</span>@endif
                </div>
            @empty
                <p class="empty">Nessun collegamento registrato.</p>
            @endforelse
        </div>
        <div class="legend">@foreach ($stati as $k => $s)<span>{!! $lamp($k) !!}{{ $s[1] }}</span>@endforeach</div>
    </section>

    @if ($sistema['contenuti'])
        <section class="card" aria-labelledby="t-contenuti">
            <h2 id="t-contenuti">Cosa contiene <span class="conta">{{ count($sistema['contenuti']) }}</span></h2>
            @foreach ($sistema['contenuti'] as $c)
                <div class="pj-dato">
                    <span class="chip">{{ $loop->iteration }}</span>
                    <div>
                        <b>{{ $c['titolo'] ?? '' }}</b>
                        @if (! empty($c['testo']))<span class="sub">{{ $c['testo'] }}</span>@endif
                        @if (! empty($c['fonte']))<span class="src">{{ $c['fonte'] }}</span>@endif
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    <section class="card" aria-labelledby="t-foto">
        <h2 id="t-foto">Foto e screenshot <span class="conta">{{ $foto->count() }}</span></h2>
        @if ($foto->isNotEmpty())
            <div class="si-galleria">
                @foreach ($foto as $x)
                    <figure>
                        <a href="{{ route('allegati.file', $x['file']) }}" target="_blank" rel="noopener"><img src="{{ route('allegati.file', $x['file']) }}" alt="{{ $x['file']->nome }}" loading="lazy"></a>
                        <figcaption><span class="mono">{{ $x['appunto']->created_at->format('d/m/Y H:i') }}</span> · {{ $x['appunto']->etichettaTipo() }}<span class="sub">{{ \Illuminate\Support\Str::limit($x['appunto']->testo, 120) }}</span></figcaption>
                    </figure>
                @endforeach
            </div>
        @else
            <p class="empty">Nessuna foto caricata dal sito per questo sistema. Usa "+ Aggiungi foto/screenshot" (o "+ Appunto" scegliendo il sistema {{ $sistema['codice'] }} nel modulo).</p>
        @endif
        @if ($sistema['foto'])
            <h3>In archivio sul PC</h3>
            <ul class="plain">
                @foreach ($sistema['foto'] as $f)
                    <li class="note">{{ $f['titolo'] ?? '' }}@if (! empty($f['data'])) <span class="src">{{ $f['data'] }}</span>@endif <span class="mono src">in archivio sul PC: {{ $f['percorso'] ?? '' }}</span></li>
                @endforeach
            </ul>
        @endif
    </section>

    <div class="pj-due">
        <section class="card" aria-labelledby="t-aperti">
            <h2 id="t-aperti">Da chiarire <span class="conta">{{ count($sistema['aperti']) }}</span></h2>
            @if ($sistema['aperti'])
                <ul class="plain">
                    @foreach ($sistema['aperti'] as $x)<li class="note">{{ $x['testo'] ?? '' }} <span class="src">— {{ $x['chi'] ?? '' }}@if (! empty($x['quando'])) · {{ $x['quando'] }}@endif</span></li>@endforeach
                </ul>
            @else
                <p class="empty">Niente di aperto.</p>
            @endif
        </section>

        @if ($sistema['documenti'])
            <section class="card"><h2>Documenti sul PC</h2>
                <ul class="plain">@foreach ($sistema['documenti'] as $d)<li class="note">{{ $d['titolo'] ?? '' }} <span class="mono src">{{ $d['percorso'] ?? '' }}</span></li>@endforeach</ul>
            </section>
        @endif
    </div>
</div>
@endsection

@push('script')
<script src="{{ asset('js/appunti.js') }}?v={{ filemtime(public_path('js/appunti.js')) }}"></script>
@endpush
