@php
    $daDecidere = $decisioni->where('fatta', false);
    $fatte = $decisioni->where('fatta', true);
    $novita = $eventi->filter(fn ($e) => $e->nonVisto())->sortByDesc('chiave')->values();
    $escluse = collect($statoPc['escluse'] ?? []);
    $nomiMacchine = fn ($codici) => collect($codici ?? [])->map(fn ($c) => '#'.($numDi[$c] ?? $c))->reject(fn ($t) => $t === '#—')->join(' ');
@endphp
<section class="da-guardare" aria-label="Da guardare">
    <div class="dg-col {{ $daDecidere->isNotEmpty() ? 'attivo' : '' }}">
        <h2>Decisioni da prendere <span class="conta">{{ $daDecidere->count() }}</span></h2>
        @forelse ($daDecidere as $d)
            <label class="ask" for="dec-{{ $d->id }}">
                <input type="checkbox" id="dec-{{ $d->id }}" data-decisione="{{ route('decisioni.segna', $d) }}">
                <span><span class="t">{{ $d->testo }}</span>
                    @if ($nomiMacchine($d->macchine))<span class="tag">{{ $nomiMacchine($d->macchine) }}</span>@endif
                    @if ($d->fonte)<span class="sub">{{ $d->fonte }}@if ($d->data) · {{ $d->data->format('d/m') }}@endif</span>@endif
                    <span class="err" aria-live="polite"></span>
                </span>
            </label>
        @empty
            <p class="empty">Nessuna decisione in sospeso.</p>
        @endforelse
        @if ($fatte->isNotEmpty())
            <details><summary class="src">Prese ({{ $fatte->count() }})</summary>
                <ul class="plain">@foreach ($fatte as $d)<li class="note fatta">{{ $d->testo }} <span class="src">— {{ $d->fatta_il?->format('d/m') }}</span></li>@endforeach</ul>
            </details>
        @endif
    </div>

    <div class="dg-col {{ $novita->isNotEmpty() ? 'attivo' : '' }}">
        <h2>Novità non viste <span class="conta">{{ $novita->count() }}</span>
            @if ($novita->count() > 1)<button type="button" class="link-azione" data-tutti-visti="{{ route('eventi.visti', $progetto->slug) }}">Segna tutte viste</button>@endif
        </h2>
        @forelse ($novita as $e)
            <div class="ev nuovo {{ $e->tipo === 'rolling' ? 'rolling' : '' }}">
                <div class="meta">
                    <span class="chip">{{ $e->tipo === 'rolling' ? 'rolling cambiato' : ($e->tipo ?: 'nota') }}</span>
                    <span class="mono">{{ $e->quando() }}</span><span>{{ $e->chi }}</span>
                    @if ($nomiMacchine($e->macchine))<span class="tag">{{ $nomiMacchine($e->macchine) }}</span>@endif
                </div>
                <div class="note">{{ $e->testoMostrato() }}</div>
                <div><button type="button" class="link-azione" data-visto="{{ route('eventi.visto', $e) }}">Visto</button></div>
            </div>
        @empty
            <p class="empty">Hai visto tutto: mail, rolling e appunti elaborati sono qui finché non premi "Visto".</p>
        @endforelse
    </div>

    <div class="dg-col {{ $escluse->isNotEmpty() ? 'attivo' : '' }}">
        <h2>Mail escluse dal filtro <span class="conta">{{ $escluse->count() }}</span></h2>
        @forelse ($escluse as $m)
            <div class="ev">
                <div class="meta"><span class="mono">{{ $m['quando'] ?? '' }}</span><span>{{ $m['da'] ?? '' }}</span></div>
                <div class="note">{{ $m['oggetto'] ?? '' }}</div>
            </div>
        @empty
            <p class="empty">Nessuna mail sul progetto arrivata da mittenti fuori elenco negli ultimi 7 giorni.</p>
        @endforelse
        @if ($escluse->isNotEmpty())<p class="src">Parlano di Salpa/Rolli/4.0 ma il mittente non è in mail-mittenti.txt: non vengono elaborate. Dimmi in terminale se aggiungerlo.</p>@endif
    </div>
</section>
