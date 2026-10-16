{{-- Eta' dei dati SCADA: "visto il 16/10 · 2 giorni fa"; oltre GIORNI_VALIDI giorni avviso "da rivedere". Parametri: $data (AAAA-MM-GG o null), $fonte (facoltativa) --}}
@php $g = \App\Services\Scada::giorni($data ?? null); @endphp
@if ($g !== null)
    <span class="sc-eta {{ $g > \App\Services\Scada::GIORNI_VALIDI ? 'sc-eta-vecchia' : '' }}">visto il {{ \App\Services\Scada::giornoMese($data) }}{{ ! empty($fonte) ? ' '.$fonte : '' }} · {{ \App\Services\Scada::fa($g) }}</span>
    @if ($g > \App\Services\Scada::GIORNI_VALIDI)<span class="sc-avviso">dati da rivedere alla prossima visita</span>@endif
@elseif (! empty($data))
    <span class="sc-eta">visto il {{ $data }}</span>
@endif
