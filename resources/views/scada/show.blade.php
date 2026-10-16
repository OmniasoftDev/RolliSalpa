@extends('layouts.app')

@section('titolo', 'SCADA · '.$progetto->nome)
@section('classe', 'p-'.$progetto->slug)

@section('contenuto')
<div class="wrap sc-pagina">
    <header class="head">
        <div>
            <h1>Sullo SCADA · {{ $progetto->nome }}</h1>
            <p>Cosa si vede di ogni macchina dentro lo SCADA Edica: connessione, valori letti, storico, pagine e schermate.</p>
        </div>
        <div class="gauges">
            @foreach (\App\Services\Scada::STATI as $k => $st)
                @if ($conteggi[$k])<div class="gauge"><b>{{ $conteggi[$k] }}</b><span>{{ $st[0] }}</span></div>@endif
            @endforeach
        </div>
    </header>

    @foreach ($sistemi as $si)
        <section class="card">
            <h2><a href="{{ route('sistemi.show', [$progetto->slug, $si['codice']]) }}">{{ $si['nome'] }}</a></h2>
            @if ($si['sintesi'])<p>{{ $si['sintesi'] }}</p>@endif
            @if ($si['galleria'])@include('scada.galleria', ['galleria' => $si['galleria']])@else<p class="empty">Nessuna schermata del sistema.</p>@endif
        </section>
    @endforeach

    <div class="sc-elenco">
        @foreach ($righe as $r)
            @php $m = $r['macchina']; $s = $r['s']; @endphp
            <article class="card sc-card">
                <h2><span class="num">{{ $m->dato('num', $m->codice) }}</span><a href="{{ route('progetti.show', [$progetto->slug, $m->codice]) }}#scada">{{ $m->dato('nome') }}</a> <a class="link-azione sc-mappa" href="{{ route('progetti.show', [$progetto->slug, $m->codice]) }}?vista=oggi#mappa">Mappa</a></h2>
                @if ($s)
                    <p><span class="sc-badge {{ $s['classe'] }}">{{ $s['etichetta'] }}</span> @if ($s['connessione'])<span class="mono">{{ $s['connessione'] }}</span>@endif</p>
                    <p class="sub">{{ count($s['variabili']) }} variabili · storico {{ $s['storico'] !== '' ? 'sì' : 'no' }}@if ($s['visto']) · visto il {{ \App\Services\Scada::giornoMese($s['visto']) }}@endif</p>
                    @if ($s['sintesi'])<p>{{ $s['sintesi'] }}</p>@endif
                    @include('scada.galleria', ['galleria' => $s['schermate'], 'max' => 3])
                @else
                    <p class="empty">Nessun dato SCADA per questa macchina.</p>
                @endif
            </article>
        @endforeach
    </div>
</div>
@endsection
