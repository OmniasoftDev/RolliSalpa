@extends('layouts.app')

@section('titolo', 'Requisiti · '.$progetto->nome)
@section('classe', 'p-'.$progetto->slug)

@php
    $stati = \App\Services\MappaProgetto::STATI;
    $nomiR = fn (array $rr) => count($rr) > 1 ? implode(', ', array_slice($rr, 0, -1)).' e '.end($rr) : ($rr[0] ?? '');
@endphp

@section('contenuto')
<div class="wrap rq-pagina">
    <header class="head">
        <div>
            <h1>Requisiti R1–R13 · {{ $progetto->nome }}</h1>
            <p>Per ogni macchina lo stato dei requisiti dell'Ing. Romano, con come si soddisfano e la prova. Tocca una casella per i dettagli.</p>
            @if ($scoperti)
                <p class="rq-scoperti"><b>Più scoperti:</b> {{ $nomiR($scoperti) }}: {{ $minimo }} {{ $minimo === 1 ? 'macchina ok' : 'macchine ok' }}.</p>
            @endif
        </div>
    </header>

    <div class="rq-scroll">
        <table class="rq-tab">
            <thead>
                <tr>
                    <th class="rq-macchina" scope="col">Macchina</th>
                    @foreach ($requisiti as $r => $nome)<th scope="col" title="{{ $nome }}"><span class="rq-r">{{ $r }}</span><span class="rq-nome">{{ $nome }}</span></th>@endforeach
                    <th scope="col">% ok</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($righe as $riga)
                    @php $m = $riga['macchina']; @endphp
                    <tr>
                        <th class="rq-macchina" scope="row"><a href="{{ route('progetti.show', [$progetto->slug, $m->codice]) }}"><span class="num">{{ $m->dato('num', $m->codice) }}</span>{{ $m->dato('nome') }}</a></th>
                        @foreach ($requisiti as $r => $nome)
                            @php $c = $riga['celle'][$r]; @endphp
                            <td class="rq-cella s-{{ $c['stato'] }}">
                                <details>
                                    <summary aria-label="{{ $r }} {{ $nome }}: {{ $stati[$c['stato']][1] }}">{{ $stati[$c['stato']][0] }}@if ($c['evidenza'])<span class="rq-prova" aria-hidden="true">📎</span>@endif</summary>
                                    <div class="rq-pop">
                                        <b>{{ $r }} · {{ $nome }}</b>
                                        <span class="src">{{ $stati[$c['stato']][1] }}</span>
                                        @if ($c['come'])<p>{{ $c['come'] }}</p>@else<p class="empty">Nessuna nota.</p>@endif
                                        @if ($c['evidenza'])
                                            <p class="rq-evidenza">📎 Prova:
                                                @if ($c['evidenza']['url'])<a href="{{ $c['evidenza']['url'] }}" target="_blank" rel="noopener">{{ $c['evidenza']['file'] }}</a>@endif
                                                @if ($c['evidenza']['testo']) {{ $c['evidenza']['testo'] }}@endif
                                            </p>
                                        @endif
                                    </div>
                                </details>
                            </td>
                        @endforeach
                        <td class="rq-perc">{{ $riga['perc'] === null ? '–' : $riga['perc'].'%' }}</td>
                    </tr>
                @empty
                    <tr><td class="empty" colspan="{{ count($requisiti) + 2 }}">Nessuna macchina.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <th class="rq-macchina" scope="row">Macchine ok</th>
                    @foreach ($requisiti as $r => $nome)<td class="rq-tot {{ $colonne[$r]['applicabili'] && $colonne[$r]['ok'] === 0 ? 'rq-zero' : '' }}">{{ $colonne[$r]['ok'] }}/{{ $colonne[$r]['applicabili'] }}</td>@endforeach
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="legend rq-legenda">
        @foreach (['ok' => 'fatto / funziona', 'corso' => 'in lavorazione', 'attesa' => 'aspettiamo altri', 'rischio' => 'bloccato / a rischio', 'no' => 'manca', 'verificare' => 'da verificare', 'na' => 'non applicabile'] as $k => $t)
            <span><span class="rq-cella-leg s-{{ $k }}">{{ $stati[$k][0] }}</span> {{ $t }}</span>
        @endforeach
        <span><span aria-hidden="true">📎</span> c'è una prova</span>
    </div>
</div>
@endsection

@push('script')
<script>
// Una sola casella aperta alla volta; tocco fuori la chiude
document.addEventListener('toggle', e => {
    const d = e.target;
    if (d.matches?.('.rq-cella details') && d.open) document.querySelectorAll('.rq-cella details[open]').forEach(x => { if (x !== d) x.open = false; });
}, true);
document.addEventListener('click', e => { if (!e.target.closest('.rq-cella')) document.querySelectorAll('.rq-cella details[open]').forEach(x => x.open = false); });
</script>
@endpush
