@extends('layouts.app')

@section('titolo', 'Progetti 4.0 · '.$progetto->nome)
@section('classe', 'p-'.$progetto->slug)

@php
    $stati = \App\Services\MappaProgetto::STATI;
    $lamp = fn (string $s, string $titolo = '') => '<span class="lamp s-'.$s.'" title="'.e(trim($titolo.' '.$stati[$s][1])).'">'.$stati[$s][0].'</span>';
    $fasi = collect($progetto->fasi ?? [])->filter(fn ($f) => is_array($f) && count($f) >= 2)->values();
    $impostati = $schede->where('p.impostato', true)->count();
@endphp

@section('contenuto')
<div class="wrap progetti-pagina">
    <header class="head">
        <div>
            <h1>Progetti 4.0 · {{ $progetto->nome }}</h1>
            <p>Ogni macchina è un progetto a sé: chi l'ha venduta e chi la gestisce, chi fa il software, quali dati riceve e quali restituisce, se è bidirezionale, come copre i requisiti dell'Ing. Romano. Apri una macchina per la mappa delle connessioni.</p>
        </div>
        <div class="gauges">
            <div class="gauge"><b>{{ $impostati }}/{{ $schede->count() }}</b><span>progetti impostati</span></div>
            <div class="gauge"><b>{{ $schede->sum(fn ($s) => $s['avanzamento'][0]) }}/{{ $schede->sum(fn ($s) => $s['avanzamento'][1]) }}</b><span>passi fatti</span></div>
            <div class="gauge {{ $schede->sum(fn ($s) => $s['copertura']['rischio']) > 0 ? 'alert' : '' }}"><b>{{ $schede->sum(fn ($s) => $s['copertura']['ok']) }}/{{ $schede->count() * count($requisiti) }}</b><span>requisiti coperti</span></div>
        </div>
    </header>

    <div class="legend">@foreach ($stati as $k => $s)<span>{!! $lamp($k) !!}{{ $s[1] }}</span>@endforeach</div>

    <div class="pj-griglia">
        @forelse ($schede as $s)
            @php
                $m = $s['macchina'];
                $p = $s['p'];
                [$fatti, $tot] = $s['avanzamento'];
                $bidi = \App\Services\MappaProgetto::stato($p['bidirezionale']['stato'] ?? null);
            @endphp
            <a class="pj-card {{ $p['impostato'] ? '' : 'vuota' }}" href="{{ route('progetti.show', [$progetto->slug, $m->codice]) }}">
                <div class="pj-testa">
                    <h2><span class="num">{{ $m->dato('num', $m->codice) }}</span>{{ $m->dato('nome') }}</h2>
                    @if ($p['aggiornato'])<span class="src">{{ \Illuminate\Support\Carbon::parse($p['aggiornato'])->format('d/m') }}</span>@endif
                </div>
                <span class="sub">{{ $m->dato('fornitore') }}</span>
                @if ($p['impostato'])
                    @if ($p['sintesi'])<p class="pj-sintesi">{{ $p['sintesi'] }}</p>@endif
                    <div class="pj-barra" role="img" aria-label="Avanzamento {{ $fatti }} su {{ $tot }}"><span style="width: {{ $tot ? round($fatti / $tot * 100) : 0 }}%"></span></div>
                    <div class="pj-meta"><span>{{ $fatti }}/{{ $tot }} passi</span><span>{{ count($p['nodi']) }} sistemi · {{ count($p['collegamenti']) }} collegamenti</span><span>Bidirezionale {!! $lamp($bidi) !!}</span></div>
                    <div class="pj-req" aria-label="Requisiti">
                        @foreach ($requisiti as $r => $nome)
                            @php $st = \App\Services\MappaProgetto::stato($p['requisiti'][$r]['stato'] ?? null); @endphp
                            <span class="req s-{{ $st }}" title="{{ $r }} {{ $nome }}: {{ $stati[$st][1] }}">{{ $r }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="pj-sintesi">Progetto non ancora impostato: per ora c'è solo lo stato delle fasi.</p>
                @endif
                <div class="pj-fasi">@foreach ($fasi as $f){!! $lamp($m->fase($f[0]), $f[1].':') !!}@endforeach</div>
            </a>
        @empty
            <p class="empty">Nessuna macchina: i dati arrivano alla prossima sincronizzazione dal PC.</p>
        @endforelse
    </div>
</div>
@endsection
