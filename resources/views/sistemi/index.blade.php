@extends('layouts.app')

@section('titolo', 'Sistemi · '.$progetto->nome)
@section('classe', 'p-'.$progetto->slug)

@php
    $tipi = \App\Http\Controllers\SistemiController::TIPI;
    $proprieta = \App\Http\Controllers\SistemiController::PROPRIETA;
@endphp

@section('contenuto')
<div class="wrap sistemi-pagina">
    <header class="head">
        <div>
            <h1>Sistemi · {{ $progetto->nome }}</h1>
            <p>Quello che sta in mezzo tra le macchine e il gestionale: SCADA, Data Collector, gestionale e rete. Per ogni sistema chi lo gestisce, quali macchine ci arrivano, cosa contiene, le foto e gli screenshot e le credenziali (cifrate).</p>
        </div>
        <div class="gauges">
            <div class="gauge"><b>{{ count($sistemi) }}</b><span>sistemi</span></div>
            <div class="gauge"><b>{{ collect($sistemi)->sum(fn ($s) => count($s['aperti'])) }}</b><span>da chiarire</span></div>
        </div>
    </header>

    <div class="pj-griglia">
        @forelse ($sistemi as $s)
            <a class="pj-card" href="{{ route('sistemi.show', [$progetto->slug, $s['codice']]) }}">
                <div class="pj-testa">
                    <h2><span class="num">{{ $s['codice'] }}</span>{{ $s['nome'] }}</h2>
                    @if ($s['aggiornato'])<span class="src">{{ \Illuminate\Support\Carbon::parse($s['aggiornato'])->format('d/m') }}</span>@endif
                </div>
                <div class="pj-meta">
                    <span class="chip">{{ $tipi[$s['tipo']] ?? ($s['tipo'] ?: 'Sistema') }}</span>
                    @if ($s['proprieta'])<span class="chip">{{ $proprieta[$s['proprieta']] ?? $s['proprieta'] }}</span>@endif
                </div>
                @if ($s['gestore'])<span class="sub">Gestore: {{ $s['gestore'] }}</span>@endif
                @if ($s['sintesi'])<p class="pj-sintesi">{{ $s['sintesi'] }}</p>@endif
                <div class="pj-meta"><span>{{ count($s['collegamenti']) }} collegamenti</span><span>{{ count($s['aperti']) }} da chiarire</span></div>
            </a>
        @empty
            <p class="empty">Nessun sistema: arrivano dal PC (campo <span class="mono">sistemi</span> del file del progetto) alla prossima pubblicazione.</p>
        @endforelse
    </div>
</div>
@endsection
