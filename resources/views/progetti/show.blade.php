@extends('layouts.app')

@section('titolo', 'Progetto 4.0 · '.$macchina->dato('num', '').' '.$macchina->dato('nome'))
@section('classe', 'p-'.$progetto->slug)

@php
    $stati = \App\Services\MappaProgetto::STATI;
    $st = fn ($s) => \App\Services\MappaProgetto::stato($s);
    $lamp = fn ($s, string $titolo = '') => '<span class="lamp s-'.\App\Services\MappaProgetto::stato($s).'" title="'.e(trim($titolo.' '.$stati[\App\Services\MappaProgetto::stato($s)][1])).'">'.$stati[\App\Services\MappaProgetto::stato($s)][0].'</span>';
    $fasi = collect($progetto->fasi ?? [])->filter(fn ($f) => is_array($f) && count($f) >= 2)->values();
    [$fatti, $tot] = $avanzamento;
    $attori = collect($p['attori'])->keyBy(fn ($a, $i) => (string) ($a['id'] ?? 'a'.$i));
    $tipi = ['gestionale' => 'Gestionale', 'scada' => 'SCADA', 'cloud' => 'Cloud', 'plc' => 'PLC', 'hmi' => 'HMI', 'macchina' => 'Macchina', 'sensore' => 'Sensore', 'persona' => 'Persona'];
    $righe = fn (string $t, int $per = 24) => array_slice(explode("\n", wordwrap($t, $per, "\n", true)), 0, 2);
    $reqChip = fn ($elenco) => collect((array) $elenco)->filter(fn ($r) => is_string($r))->map(fn ($r) => '<span class="req s-'.\App\Services\MappaProgetto::stato($p['requisiti'][$r]['stato'] ?? null).'" title="'.e($r.' '.($requisiti[$r] ?? '')).'">'.e($r).'</span>')->join(' ');
    $mail = fn (?string $c) => $c ? preg_replace_callback('/[\w.+-]+@[\w-]+\.[\w.-]+/', fn ($x) => '<a href="mailto:'.e($x[0]).'">'.e($x[0]).'</a>', e($c)) : '';
    $bidi = $p['bidirezionale'];
    $pos = $macchine->search(fn ($m) => $m->codice === $macchina->codice);
    $prima = $pos !== false && $pos > 0 ? $macchine[$pos - 1] : null;
    $dopo = $pos !== false && $pos < $macchine->count() - 1 ? $macchine[$pos + 1] : null;
@endphp

@section('contenuto')
<div class="wrap progetto-pagina">
    <nav class="pj-nav" aria-label="Macchine">
        <a class="link-azione" href="{{ route('progetti', $progetto->slug) }}">‹ Tutti i progetti</a>
        <span class="pj-salti">
            @if ($prima)<a class="link-azione" href="{{ route('progetti.show', [$progetto->slug, $prima->codice]) }}">‹ {{ $prima->dato('num') }} {{ $prima->dato('nome') }}</a>@endif
            @if ($dopo)<a class="link-azione" href="{{ route('progetti.show', [$progetto->slug, $dopo->codice]) }}">{{ $dopo->dato('num') }} {{ $dopo->dato('nome') }} ›</a>@endif
        </span>
    </nav>

    <header class="head">
        <div>
            <h1><span class="num">{{ $macchina->dato('num', $macchina->codice) }}</span>{{ $macchina->dato('nome') }} · progetto 4.0</h1>
            <p>{{ $macchina->dato('fornitore') }}@if ($p['aggiornato']) · aggiornato il {{ \Illuminate\Support\Carbon::parse($p['aggiornato'])->format('d/m/Y') }}@endif</p>
        </div>
        <div class="gauges">
            <div class="gauge"><b>{{ $fatti }}/{{ $tot }}</b><span>passi fatti</span></div>
            <div class="gauge {{ $copertura['rischio'] ? 'alert' : '' }}"><b>{{ $copertura['ok'] }}/{{ count($requisiti) - $copertura['na'] }}</b><span>requisiti coperti</span></div>
            <div class="gauge"><b>{!! $lamp($bidi['stato'] ?? null) !!}</b><span>bidirezionale</span></div>
        </div>
    </header>

    <div class="phases pj-fasi-grandi">@foreach ($fasi as $f)<div class="phase">{!! $lamp($macchina->fase($f[0])) !!}{{ $f[1] }}</div>@endforeach</div>

    <div class="azioni">
        <a class="btn btn-sec" href="{{ route('pannello', $progetto->slug) }}?m={{ $macchina->codice }}">Scheda nel Quadro</a>
        <a class="btn btn-sec" href="{{ route('workflow', $progetto->slug) }}">Workflow</a>
        <a class="btn btn-sec" href="{{ route('compiti', $progetto->slug) }}">Persone e compiti</a>
        <a class="btn btn-sec" href="{{ route('pannello', $progetto->slug) }}?appunto">+ Appunto</a>
    </div>

    @include('credenziali.blocco', ['oggettoTipo' => 'macchina', 'oggettoCodice' => $macchina->codice])

    @unless ($p['impostato'])
        <div class="card"><p class="empty">Il progetto 4.0 di questa macchina non è ancora impostato. Arriva dal PC (campo <span class="mono">progetto</span> della macchina) alla prossima pubblicazione.</p></div>
    @else
        @if ($p['sintesi'] || $p['principi'])
            <div class="brief">
                @if ($p['sintesi'])<b>In sintesi</b><span>{{ $p['sintesi'] }}</span>@endif
                @if ($p['principi'])<b>Principi decisi</b><ul class="pj-principi">@foreach ($p['principi'] as $x)<li>{{ $x }}</li>@endforeach</ul>@endif
            </div>
        @endif

        {{-- Mappa concettuale --}}
        @if ($mappa['nodi'])
            <section class="card pj-mappa-card" aria-labelledby="t-mappa">
                <div class="pj-mappa-testa">
                    <h2 id="t-mappa">Mappa delle connessioni</h2>
                    <span class="status-line">Tocca un sistema o un collegamento numerato per i dettagli.</span>
                </div>
                <div class="pj-mappa-griglia">
                    <div class="pj-mappa-scroll">
                        <svg class="pj-mappa" viewBox="0 0 {{ \App\Services\MappaProgetto::LARGHEZZA }} {{ $mappa['altezza'] }}" role="group" aria-label="Mappa delle connessioni di {{ $macchina->dato('nome') }}">
                            <defs>
                                @foreach (array_keys($stati) as $s)
                                    <marker id="fr-{{ $s }}" class="mk s-{{ $s }}" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z"/></marker>
                                @endforeach
                            </defs>
                            @foreach ($mappa['livelli'] as $l)
                                <rect class="pj-fascia" x="0" y="{{ $l['y'] - 26 }}" width="{{ \App\Services\MappaProgetto::LARGHEZZA }}" height="{{ 58 + 40 }}" rx="6"/>
                                <text class="pj-fascia-nome" x="10" y="{{ $l['y'] - 11 }}">{{ $l['nome'] }}</text>
                            @endforeach
                            @foreach ($mappa['collegamenti'] as $k)
                                <g class="pj-k st-{{ $k['stato'] }}" tabindex="0" role="button" data-dett="k-{{ $k['id'] }}" data-da="{{ $k['da'] }}" data-a="{{ $k['a'] }}" aria-label="Collegamento {{ $k['numero'] }}: {{ $k['nomeDa'] }} verso {{ $k['nomeA'] }}, {{ $k['titolo'] ?? '' }}">
                                    <path class="pj-filo-tocco" d="{{ $k['path'] }}"/>
                                    <path class="pj-filo" d="{{ $k['path'] }}" marker-end="url(#fr-{{ $k['stato'] }})" @if ($k['entrambi']) marker-start="url(#fr-{{ $k['stato'] }})" @endif/>
                                </g>
                            @endforeach
                            {{-- i numeri dopo tutti i fili: un filo che incrocia non li copre --}}
                            @foreach ($mappa['collegamenti'] as $k)
                                <g class="pj-k st-{{ $k['stato'] }}" data-dett="k-{{ $k['id'] }}" data-da="{{ $k['da'] }}" data-a="{{ $k['a'] }}" aria-hidden="true">
                                    <circle cx="{{ $k['lx'] }}" cy="{{ $k['ly'] }}" r="12"/>
                                    <text x="{{ $k['lx'] }}" y="{{ $k['ly'] + 4 }}" text-anchor="middle">{{ $k['numero'] }}</text>
                                </g>
                            @endforeach
                            @foreach ($mappa['nodi'] as $n)
                                <g class="pj-nodo t-{{ $n['tipo'] ?? 'altro' }} st-{{ $n['stato'] }}" tabindex="0" role="button" data-dett="n-{{ $n['id'] }}" data-nodo="{{ $n['id'] }}" aria-label="{{ $n['nome'] ?? $n['id'] }}: {{ $stati[$n['stato']][1] }}">
                                    <rect x="{{ $n['x'] }}" y="{{ $n['y'] }}" width="{{ $n['w'] }}" height="{{ $n['h'] }}" rx="8"/>
                                    <text class="pj-tipo" x="{{ $n['x'] + 12 }}" y="{{ $n['y'] + 16 }}">{{ $tipi[$n['tipo'] ?? ''] ?? 'Sistema' }}</text>
                                    @foreach ($righe((string) ($n['nome'] ?? $n['id'])) as $i => $riga)
                                        <text class="pj-nome" x="{{ $n['x'] + 12 }}" y="{{ $n['y'] + 33 + $i * 16 }}">{{ $riga }}</text>
                                    @endforeach
                                    <circle class="pj-pallino" cx="{{ $n['x'] + $n['w'] - 14 }}" cy="{{ $n['y'] + 14 }}" r="7"/>
                                    <text class="pj-pallino-t" x="{{ $n['x'] + $n['w'] - 14 }}" y="{{ $n['y'] + 18 }}" text-anchor="middle">{{ $stati[$n['stato']][0] }}</text>
                                </g>
                            @endforeach
                        </svg>
                    </div>
                    <aside class="pj-dettagli" aria-live="polite">
                        <div class="pj-dett" id="pj-vuoto"><p class="empty">Tocca un sistema per vedere chi lo gestisce e i suoi collegamenti, o un numero per i dati che passano.</p></div>
                        @foreach ($mappa['nodi'] as $n)
                            @php $att = isset($n['attore']) ? $attori[$n['attore']] ?? null : null; @endphp
                            <div class="pj-dett" id="n-{{ $n['id'] }}" hidden>
                                <span class="chip">{{ $tipi[$n['tipo'] ?? ''] ?? 'Sistema' }}</span>
                                <h3>{{ $n['nome'] ?? $n['id'] }}</h3>
                                <p>{!! $lamp($n['stato']) !!} {{ $stati[$n['stato']][1] }}</p>
                                @if (! empty($n['dettaglio']))<p>{{ $n['dettaglio'] }}</p>@endif
                                @if ($att)<p><b>Chi:</b> {{ $att['chi'] ?? '' }} <span class="src">({{ $att['ruolo'] ?? '' }})</span></p>@endif
                                @php $suoi = collect($mappa['collegamenti'])->filter(fn ($k) => $k['da'] === $n['id'] || $k['a'] === $n['id']); @endphp
                                @if ($suoi->isNotEmpty())
                                    <ul class="plain pj-lista-k">@foreach ($suoi as $k)<li><button type="button" class="link-azione" data-apri="k-{{ $k['id'] }}">{{ $k['numero'] }}. {{ $k['titolo'] ?? '' }}</button> <span class="src">{{ $k['da'] === $n['id'] ? '→ '.$k['nomeA'] : '← '.$k['nomeDa'] }}</span></li>@endforeach</ul>
                                @endif
                                @if (! empty($n['fonte']))<p class="src">Fonte: {{ $n['fonte'] }}</p>@endif
                            </div>
                        @endforeach
                        @foreach ($mappa['collegamenti'] as $k)
                            <div class="pj-dett" id="k-{{ $k['id'] }}" hidden>
                                <span class="chip">Collegamento {{ $k['numero'] }}</span>
                                <h3>{{ $k['titolo'] ?? '' }}</h3>
                                <p>{{ $k['nomeDa'] }} {{ $k['entrambi'] ? '⇄' : '→' }} {{ $k['nomeA'] }}</p>
                                <p>{!! $lamp($k['stato']) !!} {{ $stati[$k['stato']][1] }}@if (! empty($k['protocollo'])) · <span class="mono">{{ $k['protocollo'] }}</span>@endif</p>
                                @if ($k['dati'])<ul class="pj-dati">@foreach ($k['dati'] as $d)<li>{{ $d }}</li>@endforeach</ul>@endif
                                @if (! empty($k['nota']))<p>{{ $k['nota'] }}</p>@endif
                                @if (! empty($k['fonte']))<p class="src">Fonte: {{ $k['fonte'] }}</p>@endif
                            </div>
                        @endforeach
                    </aside>
                </div>
                <ol class="pj-collegamenti">
                    @foreach ($mappa['collegamenti'] as $k)
                        <li><button type="button" class="pj-riga-k" data-apri="k-{{ $k['id'] }}">{!! $lamp($k['stato']) !!}<span class="pj-num">{{ $k['numero'] }}</span><span><b>{{ $k['titolo'] ?? '' }}</b> <span class="src">{{ $k['nomeDa'] }} {{ $k['entrambi'] ? '⇄' : '→' }} {{ $k['nomeA'] }}@if (! empty($k['protocollo'])) · {{ $k['protocollo'] }}@endif</span></span></button></li>
                    @endforeach
                </ol>
                <div class="legend">@foreach ($stati as $k => $s)<span>{!! $lamp($k) !!}{{ $s[1] }}</span>@endforeach<span>⇄ nei due sensi</span></div>
            </section>
        @endif

        {{-- Chi fa cosa: a tutta larghezza, schede affiancate --}}
        <section class="card" aria-labelledby="t-attori">
            <h2 id="t-attori">Chi fa cosa <span class="conta">{{ $attori->count() }}</span></h2>
            <div class="pj-attori">
                @forelse ($attori as $a)
                    <div class="pj-attore st-{{ $st($a['stato'] ?? null) }}">
                        <div class="pj-attore-testa"><span class="pj-ruolo">{{ $a['ruolo'] ?? '' }}</span>{!! $lamp($a['stato'] ?? null) !!}</div>
                        <b>{{ $a['chi'] ?? 'da definire' }}</b>
                        @foreach ((array) ($a['persone'] ?? []) as $codice)
                            @if (isset($persone[$codice]))
                                @php $pe = $persone[$codice]; @endphp
                                <span class="pj-persona">{{ $pe->nome }}@if ($pe->ruolo) · {{ $pe->ruolo }}@endif @if ($pe->contatti)<span class="src">{!! $mail($pe->contatti) !!}</span>@endif</span>
                            @endif
                        @endforeach
                        @if (! empty($a['nota']))<span class="note">{{ $a['nota'] }}</span>@endif
                        @if (! empty($a['fonte']))<span class="src">{{ $a['fonte'] }}</span>@endif
                    </div>
                @empty
                    <p class="empty">Da definire.</p>
                @endforelse
            </div>
        </section>

        {{-- Anagrafica, a tutta larghezza --}}
            <section class="card" aria-labelledby="t-anagrafica">
                <h2 id="t-anagrafica">La macchina</h2>
                @if ($p['anagrafica'])
                    <dl class="kv pj-kv">@foreach ($p['anagrafica'] as $v)<div><dt>{{ $v['voce'] ?? '' }}</dt><dd>{{ $v['valore'] ?? '' }}@if (! empty($v['fonte'])) <span class="src">{{ $v['fonte'] }}</span>@endif</dd></div>@endforeach</dl>
                @else
                    <dl class="kv">@foreach (['Fornitore' => 'fornitore', 'IP' => 'ip', 'Protocollo' => 'protocollo', 'Requisiti' => 'requisiti'] as $k => $c)@if ($macchina->dato($c))<div><dt>{{ $k }}</dt><dd>{{ $macchina->dato($c) }}</dd></div>@endif @endforeach</dl>
                @endif
                @if ($bidi)
                    <div class="pj-bidi st-{{ $st($bidi['stato'] ?? null) }}">
                        <div class="pj-attore-testa"><span class="pj-ruolo">Bidirezionalità</span>{!! $lamp($bidi['stato'] ?? null) !!}</div>
                        <span>{{ $bidi['testo'] ?? '' }}</span>
                    </div>
                @endif
            </section>

        {{-- Dati in uscita e in ingresso --}}
        <div class="pj-due">
            @foreach (['uscite' => ['Dati in uscita', 'dalla macchina verso lo SCADA'], 'ingressi' => ['Dati in ingresso', 'verso la macchina, mai a mano']] as $chiave => [$titolo, $sotto])
                <section class="card" aria-labelledby="t-{{ $chiave }}">
                    <h2 id="t-{{ $chiave }}">{{ $titolo }} <span class="conta">{{ count($p[$chiave]) }}</span></h2>
                    <span class="status-line">{{ $sotto }}</span>
                    @forelse ($p[$chiave] as $d)
                        <div class="pj-dato">
                            {!! $lamp($d['stato'] ?? null) !!}
                            <div>
                                <b>{{ $d['dato'] ?? '' }}</b>@if (! empty($d['da'])) <span class="chip">da {{ $d['da'] }}</span>@endif
                                @if (! empty($d['dettaglio']))<span class="sub">{{ $d['dettaglio'] }}</span>@endif
                                <span class="pj-dato-meta">@if (! empty($d['variabile']))<span class="mono">{{ $d['variabile'] }}</span>@endif {!! $reqChip($d['req'] ?? []) !!}@if (! empty($d['fonte'])) <span class="src">{{ $d['fonte'] }}</span>@endif</span>
                            </div>
                        </div>
                    @empty
                        <p class="empty">Ancora da definire.</p>
                    @endforelse
                </section>
            @endforeach
        </div>

        {{-- Requisiti R1–R13 --}}
        <section class="card" aria-labelledby="t-req">
            <h2 id="t-req">Requisiti dell'Ing. Romano</h2>
            <div class="pj-copertura" role="img" aria-label="Copertura dei requisiti">
                @foreach ($copertura as $s => $n)@if ($n)<span class="s-{{ $s }}" style="flex: {{ $n }}" title="{{ $stati[$s][1] }}: {{ $n }}">{{ $n }}</span>@endif @endforeach
            </div>
            <div class="pj-requisiti">
                @foreach ($requisiti as $r => $nome)
                    @php $req = $p['requisiti'][$r] ?? []; @endphp
                    <div class="pj-req-box st-{{ $st($req['stato'] ?? null) }}">
                        <div class="pj-attore-testa"><span class="mono">{{ $r }}</span>{!! $lamp($req['stato'] ?? null) !!}</div>
                        <b>{{ $nome }}</b>
                        <span class="note">{{ $req['come'] ?? 'da definire' }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        @if ($p['strade'])
            <section class="card" aria-labelledby="t-strade">
                <h2 id="t-strade">Strade possibili</h2>
                <div class="pj-strade">
                    @foreach ($p['strade'] as $s)
                        <div class="pj-strada stato-{{ $s['stato'] ?? 'valutare' }}">
                            <div class="pj-attore-testa"><span class="pj-ruolo">Strada {{ $s['id'] ?? '' }}</span><span class="chip">{{ ['scelta' => 'scelta', 'scartata' => 'scartata'][$s['stato'] ?? ''] ?? 'da valutare' }}</span></div>
                            <b>{{ $s['titolo'] ?? '' }}</b>
                            <span class="note">{{ $s['testo'] ?? '' }}</span>
                            @if (! empty($s['collegamenti']))
                                <span class="src">Collegamenti: @foreach ($s['collegamenti'] as $kid)@php $k = collect($mappa['collegamenti'])->firstWhere('id', $kid); @endphp @if ($k)<button type="button" class="link-azione" data-apri="k-{{ $k['id'] }}">{{ $k['numero'] }}</button>@endif @endforeach</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endunless

    <div class="pj-due">
        <section class="card" aria-labelledby="t-aperti">
            <h2 id="t-aperti">Da chiarire</h2>
            @php $domande = $macchina->questions->where('fatto', false); @endphp
            @if ($p['aperti'] || $domande->isNotEmpty() || $compiti->isNotEmpty())
                <ul class="plain">
                    @foreach ($p['aperti'] as $x)<li class="note">{{ $x['testo'] ?? '' }} <span class="src">— {{ $x['chi'] ?? '' }}@if (! empty($x['quando'])) · {{ $x['quando'] }}@endif</span></li>@endforeach
                    @foreach ($domande as $q)<li class="note">{{ $q->cosa }} <span class="src">— domanda per {{ $q->chi }}</span></li>@endforeach
                    @foreach ($compiti as $c)<li class="note">{{ $c->testo }} <span class="src">— compito di {{ $persone[$c->persona]->nome ?? 'da assegnare' }}{{ $c->stato === 'proposto' ? ' · da confermare' : '' }}</span></li>@endforeach
                </ul>
            @else
                <p class="empty">Niente di aperto.</p>
            @endif
        </section>

        <section class="card" aria-labelledby="t-avanz">
            <h2 id="t-avanz">Avanzamento <span class="conta">{{ $fatti }}/{{ $tot }}</span></h2>
            <div class="pj-barra"><span style="width: {{ $tot ? round($fatti / $tot * 100) : 0 }}%"></span></div>
            @if ($p['avanzamento'])
                <ul class="plain pj-check">
                    @foreach ($p['avanzamento'] as $v)
                        <li class="{{ ! empty($v['fatto']) ? 'fatto' : '' }}"><span aria-hidden="true">{{ ! empty($v['fatto']) ? '☑' : '☐' }}</span> {{ $v['testo'] ?? '' }}@if (! empty($v['data'])) <span class="src">{{ \Illuminate\Support\Carbon::parse($v['data'])->format('d/m') }}</span>@endif</li>
                    @endforeach
                </ul>
            @else
                <p class="empty">Nessun passo definito.</p>
            @endif
        </section>
    </div>

    @if ($p['documenti'] || $storia->isNotEmpty())
        <div class="pj-due">
            @if ($p['documenti'])
                <section class="card"><h2>Documenti sul PC</h2>
                    <ul class="plain">@foreach ($p['documenti'] as $d)<li class="note">{{ $d['titolo'] ?? '' }} <span class="mono src">{{ $d['percorso'] ?? '' }}</span></li>@endforeach</ul>
                </section>
            @endif
            @if ($storia->isNotEmpty())
                <section class="card"><h2>Ultime novità</h2>@foreach ($storia as $e)@include('pannello.evento', ['e' => $e, 'tags' => []])@endforeach</section>
            @endif
        </div>
    @endif
</div>
@endsection

@push('script')
<script>
(() => {
    const pannelli = document.querySelectorAll('.pj-dett');
    const elementi = document.querySelectorAll('.pj-mappa [data-dett]');
    const mostra = (id, scorri) => {
        if (!document.getElementById(id)) return;
        pannelli.forEach(p => p.hidden = p.id !== id);
        elementi.forEach(e => e.classList.toggle('sel', e.dataset.dett === id));
        // un sistema scelto accende i suoi collegamenti e spegne gli altri
        const nodo = id.startsWith('n-') ? id.slice(2) : null;
        document.querySelectorAll('.pj-k').forEach(k => {
            k.classList.toggle('spento', nodo !== null && k.dataset.da !== nodo && k.dataset.a !== nodo);
        });
        document.querySelectorAll('.pj-nodo').forEach(n => n.classList.remove('spento'));
        if (scorri) document.querySelector('.pj-dettagli').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };
    elementi.forEach(e => {
        e.addEventListener('click', () => mostra(e.dataset.dett, matchMedia('(max-width: 900px)').matches));
        e.addEventListener('keydown', ev => { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); mostra(e.dataset.dett, false); } });
    });
    document.addEventListener('click', ev => {
        const b = ev.target.closest('[data-apri]');
        if (b) mostra(b.dataset.apri, true);
    });
})();
</script>
@endpush
