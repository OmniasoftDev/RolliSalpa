<details class="ev mail {{ $m->vista_at ? '' : 'nuovo' }}">
    <summary class="meta">
        <span class="chip">{{ $m->inviata ? 'inviata' : 'ricevuta' }}</span>
        <span class="mono">{{ $m->ricevuta->format('d/m H:i') }}</span>
        <span>{{ $m->da }}</span>
        <span class="oggetto">{{ $m->oggetto }}</span>
        @unless ($m->elaborata_at)<span class="tag">non ancora registrata dal PC</span>@endunless
    </summary>
    <p class="src">A: {{ $m->a }}@if ($m->cc) · Cc: {{ $m->cc }}@endif · {{ $m->cartella }}
        @if ($m->allegati) · Allegati: {{ implode(', ', $m->allegati) }}@endif
        @if ($m->elaborata_at) · registrata nei file del progetto {{ $m->elaborata_at->format('d/m H:i') }}@endif</p>
    <div class="note testo-mail">{{ $m->testo }}</div>
    <div><button type="button" class="link-azione" data-vista="{{ route('mail.vista', $m) }}" data-stato="{{ $m->vista_at ? '1' : '0' }}">{{ $m->vista_at ? 'Rimetti tra le da leggere' : 'Letta' }}</button></div>
</details>
