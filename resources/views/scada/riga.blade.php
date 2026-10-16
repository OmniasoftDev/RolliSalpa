{{-- Riga breve "SCADA gg/mm: stato — connessione" con 1–3 miniature: $s (scheda Scada), $link --}}
@if ($s)
    <div class="sc-riga">
        <a href="{{ $link }}"><b>SCADA{{ $s['visto'] ? ' '.\App\Services\Scada::giornoMese($s['visto']) : '' }}:</b>
            <span class="sc-badge {{ $s['classe'] }}">{{ $s['etichetta'] }}</span>@if ($s['connessione']) — {{ $s['connessione'] }}@endif</a>
        @include('scada.galleria', ['galleria' => $s['schermate'], 'max' => 3])
    </div>
@endif
