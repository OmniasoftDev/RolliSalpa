{{-- Mappa a schermo intero (nuova finestra): macchina o impianto. Senza menu. --}}
@extends('layouts.app')

@section('titolo', 'Mappa · '.$titolo)
@section('classe', 'p-'.$progetto->slug.' schermo-mappa')

@section('contenuto')
@php $dataOggi = $p !== null ? ($p['oggi']['aggiornato'] ?? null) : ($dataOggi ?? null); @endphp
<div class="pj-schermo {{ $vista === 'oggi' && \App\Services\Scada::vecchio($dataOggi) ? 'pj-vecchio' : '' }}">
    <header class="pj-schermo-testa">
        <h1>{{ $titolo }}</h1>
        @include('progetti.viste', ['p' => $p, 'vista' => $vista, 'base' => $base, 'ancora' => ''])
        @if ($impianto)
            @include('impianto.filtro', ['macchine' => $macchine])
        @endif
        <span class="status-line">@if ($vista === 'oggi')Com'è oggi, sullo SCADA: @include('scada.eta', ['data' => $dataOggi]).@else Obiettivo 4.0.@endif Tocca un blocco o una freccia.</span>
        <a class="link-azione" href="{{ $indietro }}?vista={{ $vista }}">Torna alla pagina</a>
    </header>
    @if ($mappa['nodi'])
        @include('progetti.mappa', ['mappa' => $mappa, 'slug' => $progetto->slug, 'codice' => $codice, 'titolo' => 'Mappa: '.$titolo, 'impianto' => $impianto])
    @else
        <p class="empty">Nessun blocco in questa vista.</p>
    @endif
</div>
@endsection

@push('script')
<script src="{{ asset('js/mappa.js') }}?v={{ filemtime(public_path('js/mappa.js')) }}"></script>
@endpush
