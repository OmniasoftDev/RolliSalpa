@extends('layouts.app')

@section('titolo', 'Registro controlli e mail · Rolli Salpa')

@php
    $nonViste = $mail->whereNull('vista_at');
    $viste = $mail->whereNotNull('vista_at');
    $segni = ['ok' => '✓', 'avvisi' => '!', 'errore' => '✗', 'manca' => '✗', 'atteso' => '…', 'futuro' => '', 'prima' => '–'];
    $titoli = ['ok' => 'Fatto', 'avvisi' => 'Fatto con avvisi', 'errore' => 'Errore o quadratura non riuscita', 'manca' => 'Controllo mancante', 'atteso' => 'In arrivo', 'futuro' => 'Da fare', 'prima' => 'Prima dell\'avvio del registro'];
    $passi = fn ($c) => collect($c->passi ?? [])->map(fn ($p) => ($p['passo'] ?? '').': '.($p['esito'] ?? ''))->join("\n");
@endphp

@section('contenuto')
<div class="wrap registro">
    <section class="verdetto {{ $problemi ? 'ko' : 'ok' }}" role="status">
        @if ($problemi)
            <h1>Da controllare</h1>
            <ul>@foreach ($problemi as $p)<li>{{ $p }}</li>@endforeach</ul>
        @else
            <h1>Tutto in regola</h1>
            <p>Nessun controllo mancante negli ultimi 7 giorni lavorativi e l'ultimo controllo quadra: le mail di progetto in Outlook e quelle qui sotto coincidono.</p>
        @endif
        @if ($ultimo)
            <p class="src">Ultimo controllo {{ $ultimo->inizio->format('d/m H:i') }} · {{ $ultimo->mail_finestra }} mail negli ultimi 14 giorni ({{ $ultimo->mail_nuove }} nuove) · Outlook: {{ $ultimo->outlook ?: '–' }}
                @if ($ultimo->firma_pc) · impronta PC <span class="mono">{{ substr($ultimo->firma_pc, 0, 12) }}</span> / sito <span class="mono">{{ substr((string) $ultimo->firma_server, 0, 12) }}</span>@endif</p>
        @endif
    </section>

    <section class="blocco" aria-labelledby="t-mail">
        <h2 id="t-mail">Mail da leggere <span class="conta">{{ $nonViste->count() }}</span>
            @if ($nonViste->count() > 1)<button type="button" class="link-azione" data-tutte-viste="{{ route('mail.viste') }}">Segna tutte lette</button>@endif
        </h2>
        @forelse ($nonViste as $m)
            @include('registro.mail', ['m' => $m])
        @empty
            <p class="empty">Hai letto tutte le mail di progetto arrivate su info@omniasoft.it.</p>
        @endforelse
        @if ($viste->isNotEmpty())
            <details><summary class="src">Già lette ({{ $viste->count() }})</summary>
                @foreach ($viste as $m) @include('registro.mail', ['m' => $m]) @endforeach
            </details>
        @endif
    </section>

    <section class="blocco" aria-labelledby="t-griglia">
        <h2 id="t-griglia">Controlli per ora</h2>
        <p class="src">Il PC controlla ogni 5 minuti, lun–ven dalle 8 alle 20: ogni casella è un'ora con il numero di controlli fatti. Verde tutto a posto · giallo qualche avviso · rosso nessun controllo, errore o quadratura non riuscita · – prima dell'avvio del registro.</p>
        <div class="griglia-scroll">
            <table class="griglia">
                <thead><tr><th scope="col">Giorno</th>@foreach (\App\Services\Registro::ORARI as $o)<th scope="col" class="mono">{{ $o }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach ($griglia as $r)
                    <tr>
                        <th scope="row">{{ ['dom', 'lun', 'mar', 'mer', 'gio', 'ven', 'sab'][$r['giorno']->dayOfWeek] }} {{ $r['giorno']->format('d/m') }}</th>
                        @foreach ($r['orari'] as $o)
                            <td class="g-{{ $o['stato'] }}" title="{{ $titoli[$o['stato']] }}@if ($o['controllo']) — {{ $o['numero'] }} controlli, ultimo {{ $o['controllo']->inizio->format('H:i') }}&#10;{{ $passi($o['controllo']) }}@endif">{{ $o['numero'] ?: $segni[$o['stato']] }}</td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="blocco" aria-labelledby="t-controlli">
        <h2 id="t-controlli">Ultimi controlli</h2>
        @forelse ($controlli as $c)
            <details class="ev">
                <summary class="meta">
                    <span class="chip esito-{{ $c->quadra === false ? 'errore' : $c->esito }}">{{ $c->quadra === false ? 'non quadra' : $c->esito }}</span>
                    <span class="mono">{{ $c->inizio->format('d/m H:i') }}</span>
                    <span>{{ $c->mail_nuove }} mail nuove · {{ $c->appunti }} appunti · {{ $c->mail_finestra }} in finestra</span>
                    <span>{{ $c->quadra === true ? 'quadra' : ($c->quadra === false ? 'NON quadra' : 'quadratura non fatta') }}</span>
                </summary>
                <ul class="plain passi">@foreach ($c->passi ?? [] as $p)<li><b>{{ $p['passo'] ?? '' }}</b> {{ $p['esito'] ?? '' }}</li>@endforeach</ul>
                <p class="src">Outlook: {{ $c->outlook ?: '–' }}@if ($c->fine) · finito {{ $c->fine->format('H:i:s') }}@endif</p>
            </details>
        @empty
            <p class="empty">Nessun controllo registrato.</p>
        @endforelse
    </section>
</div>
@endsection

@push('script')
<script>
(() => {
    const token = document.querySelector('meta[name=csrf-token]').content;
    const posta = (url, corpo) => fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(corpo || {}) });
    document.addEventListener('click', async e => {
        const v = e.target.closest('[data-vista]');
        if (v) {
            v.disabled = true;
            const vista = v.dataset.stato !== '1';
            const r = await posta(v.dataset.vista, { vista });
            if (r.ok) location.reload(); else { v.disabled = false; v.textContent = 'Non salvato, riprova'; }
            return;
        }
        const t = e.target.closest('[data-tutte-viste]');
        if (t) { t.disabled = true; const r = await posta(t.dataset.tutteViste); if (r.ok) location.reload(); else t.disabled = false; }
    });
})();
</script>
@endpush
