@extends('layouts.app')

@section('titolo', 'Mappa impianto · '.$progetto->nome)
@section('classe', 'p-'.$progetto->slug)

@section('contenuto')
<div class="wrap">
    <section class="card pj-mappa-card {{ $vista === 'oggi' && \App\Services\Scada::vecchio($dataOggi) ? 'pj-vecchio' : '' }}" aria-labelledby="t-impianto">
        <div class="pj-mappa-testa">
            <h2 id="t-impianto">Mappa impianto · {{ $progetto->nome }}</h2>
            @include('progetti.viste', ['p' => null, 'vista' => $vista, 'base' => route('impianto', $progetto->slug), 'ancora' => ''])
            @include('impianto.filtro', ['macchine' => $macchine])
            <a class="btn btn-sec pj-btn-schermo" href="{{ route('impianto.mappa', $progetto->slug) }}?vista={{ $vista }}" target="_blank" rel="noopener">Apri a schermo intero ↗</a>
        </div>
        <p class="status-line">
            Tutte le macchine insieme: in alto i sistemi condivisi (gestionale, Data Collector, SCADA, storico, rete), sotto una colonna per macchina.
            @if ($vista === 'oggi') Vista "Oggi": com'è sullo SCADA, @include('scada.eta', ['data' => $dataOggi]) (la visita più vecchia tra le macchine). @else Vista "Obiettivo 4.0": come deve essere. @endif
        </p>
        @if ($mappa['nodi'])
            @include('progetti.mappa', ['mappa' => $mappa, 'slug' => $progetto->slug, 'codice' => '', 'titolo' => 'Mappa impianto '.$progetto->nome, 'impianto' => true])
        @else
            <p class="empty">Nessuna macchina ha ancora blocchi in questa vista.</p>
        @endif
        @if ($senzaVista->isNotEmpty())
            <p class="src">Senza mappa in questa vista: @foreach ($senzaVista as $m)<a href="{{ route('progetti.show', [$progetto->slug, $m['codice']]) }}">{{ $m['num'] }} {{ $m['nome'] }}</a>@if (! $loop->last), @endif @endforeach</p>
        @endif
    </section>
</div>
@endsection

@push('script')
<script src="{{ asset('js/mappa.js') }}?v={{ filemtime(public_path('js/mappa.js')) }}"></script>
@endpush
