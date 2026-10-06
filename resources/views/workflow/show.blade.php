@extends('layouts.app')

@section('titolo', 'Workflow · '.$progetto->nome)
@section('classe', 'p-'.$progetto->slug)

@php
    $perCodice = $macchine->keyBy('codice');
    $nomeMacchina = fn ($c) => isset($perCodice[$c]) ? trim($perCodice[$c]->dato('num', '').' '.$perCodice[$c]->dato('nome', '')) : $c;
    $tag = fn ($codici) => collect($codici)->map(fn ($c) => '#'.(isset($perCodice[$c]) ? $perCodice[$c]->dato('num', $c) : $c))->join(' ');
    $oggi = today();
    $giorni = ['lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom'];
    $mesi = ['', 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
    $fasi = $voci->where('tipo', 'fase');
    $puntuali = $voci->where('tipo', '!=', 'fase');
    $perGiorno = $puntuali->groupBy(fn ($v) => $v['inizio']->format('Y-m-d'));
    $classe = fn ($v) => 'wf-'.$v['tipo'].($v['stato'] ? ' st-'.$v['stato'] : '');

    // calendario a mese: settimane intere da lunedi' a domenica
    $primo = $mese->copy()->startOfWeek();
    $ultimo = $mese->copy()->endOfMonth()->endOfWeek()->startOfDay();
    $settimane = [];
    for ($l = $primo->copy(); $l->lte($ultimo); $l->addWeek()) {
        $settimane[] = $l->copy();
    }

    // timeline: posizione in % tra il primo lunedi' e l'ultima domenica
    [$tDal, $tAl] = $intervallo;
    $tGiorni = (int) round(($tAl->getTimestamp() - $tDal->getTimestamp()) / 86400) + 1;
    $pos = fn ($d) => max(0, min(100, round(($d->copy()->startOfDay()->getTimestamp() - $tDal->getTimestamp()) / 86400) / $tGiorni * 100));
    $largo = fn ($dal, $al) => max(100 / $tGiorni, $pos($al) - $pos($dal) + 100 / $tGiorni);
    $dentro = fn ($v) => $v['fine']->gte($tDal) && $v['inizio']->lte($tAl->copy()->endOfDay());
    $righe = collect([['codice' => null, 'nome' => 'Generale', 'num' => '']])
        ->concat($macchine->map(fn ($m) => ['codice' => $m->codice, 'nome' => $m->dato('nome', $m->codice), 'num' => $m->dato('num', '')]));
    $diRiga = fn ($v, $codice) => $codice === null ? empty($v['macchine']) : in_array($codice, $v['macchine'], true);
    $scadenza = $progetto->info('scadenza');
@endphp

@section('contenuto')
<div class="wrap workflow-pagina">
    <header class="head">
        <div>
            <h1>Workflow · {{ $progetto->nome }}</h1>
            <p>Il lavoro del progetto nel tempo: fasi del planning, appuntamenti, eventi del calendario Google Salpa-Rolli, scadenze dei compiti e mail. Tocca una voce per vedere dettagli e collegamenti.</p>
        </div>
        <div class="gauges">
            <div class="gauge"><b>{{ $fasi->count() }}</b><span>fasi</span></div>
            <div class="gauge"><b>{{ $voci->whereIn('tipo', ['appuntamento', 'google'])->filter(fn ($v) => $v['fine']->gte(now()))->count() }}</b><span>appuntamenti in arrivo</span></div>
            <div class="gauge {{ $voci->where('stato', 'scaduto')->isNotEmpty() ? 'alert' : '' }}"><b>{{ $voci->where('tipo', 'scadenza')->count() }}</b><span>scadenze</span></div>
            @if ($scadenza)
                <div class="gauge"><b>{{ max(0, (int) round((\Illuminate\Support\Carbon::parse($scadenza)->getTimestamp() - $oggi->getTimestamp()) / 86400)) }}</b><span>giorni al {{ \Illuminate\Support\Carbon::parse($scadenza)->format('d/m') }}</span></div>
            @endif
        </div>
    </header>

    <div class="wf-barra">
        <div class="filters" role="group" aria-label="Vista">
            <button type="button" data-vista="calendario" aria-pressed="true">Calendario</button>
            <button type="button" data-vista="timeline" aria-pressed="false">Timeline per macchina</button>
        </div>
        <fieldset class="wf-tipi">
            <legend class="sr">Cosa mostrare</legend>
            @foreach ($tipi as $t => $etichetta)
                <label for="wt-{{ $t }}"><input type="checkbox" id="wt-{{ $t }}" data-tipo-filtro="{{ $t }}" checked> <span class="wf-pallino wf-{{ $t }}"></span>{{ $etichetta }}</label>
            @endforeach
        </fieldset>
        <span class="status-line">Google Calendar: {{ $googleLetto ? 'letto dal PC il '.\Illuminate\Support\Carbon::parse($googleLetto)->format('d/m H:i') : 'non ancora letto dal PC' }}</span>
    </div>

    <div class="wf-griglia">
        <div class="wf-viste">
            {{-- Calendario a mese --}}
            <section class="card wf-vista" id="vista-calendario" aria-label="Calendario">
                <div class="wf-mese-testa">
                    <a class="btn btn-sec" href="{{ route('workflow', [$progetto->slug, 'mese' => $mese->copy()->subMonth()->format('Y-m')]) }}" aria-label="Mese prima">‹</a>
                    <h2>{{ ucfirst($mesi[$mese->month]) }} {{ $mese->year }}</h2>
                    <a class="btn btn-sec" href="{{ route('workflow', [$progetto->slug, 'mese' => $mese->copy()->addMonth()->format('Y-m')]) }}" aria-label="Mese dopo">›</a>
                    @unless ($mese->isSameMonth($oggi))<a class="link-azione" href="{{ route('workflow', $progetto->slug) }}">oggi</a>@endunless
                </div>
                <div class="wf-mese">
                    <div class="wf-intestazione" aria-hidden="true">@foreach ($giorni as $g)<span>{{ $g }}</span>@endforeach</div>
                    @foreach ($settimane as $lun)
                        @php
                            $dom = $lun->copy()->addDays(6)->endOfDay();
                            $barre = $fasi->filter(fn ($f) => $f['inizio']->lte($dom) && $f['fine']->gte($lun));
                        @endphp
                        <div class="wf-settimana">
                            @if ($barre->isNotEmpty())
                                <div class="wf-barre">
                                    @foreach ($barre as $f)
                                        @php
                                            $da = $f['inizio']->lt($lun) ? 1 : $f['inizio']->dayOfWeekIso;
                                            $a = $f['fine']->gt($dom) ? 8 : $f['fine']->dayOfWeekIso + 1;
                                        @endphp
                                        <button type="button" class="wf-barra-fase {{ $classe($f) }}" data-v="{{ $f['chiave'] }}" data-tipo="fase" style="grid-column: {{ $da }} / {{ $a }}">
                                            {{ $f['titolo'] }}@if ($f['macchine']) <span class="tag">{{ $tag($f['macchine']) }}</span>@endif
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                            <div class="wf-giorni">
                                @for ($d = $lun->copy(); $d->lte($dom); $d->addDay())
                                    @php $diQuesto = $perGiorno[$d->format('Y-m-d')] ?? collect(); @endphp
                                    <div class="wf-giorno {{ $d->month !== $mese->month ? 'fuori' : '' }} {{ $d->isSameDay($oggi) ? 'oggi' : '' }} {{ $d->isWeekend() ? 'festivo' : '' }} {{ $diQuesto->isEmpty() ? 'vuoto' : '' }}">
                                        <span class="wf-data"><span class="wf-gs">{{ $giorni[$d->dayOfWeekIso - 1] }} </span>{{ $d->day }}@if ($scadenza === $d->format('Y-m-d')) <span class="chip chip-rischio">scadenza</span>@endif</span>
                                        @foreach ($diQuesto as $v)
                                            <button type="button" class="wf-voce {{ $classe($v) }}" data-v="{{ $v['chiave'] }}" data-tipo="{{ $v['tipo'] }}">
                                                @if ($v['ora'])<span class="mono">{{ $v['inizio']->format('G:i') }}</span> @endif{{ $v['titolo'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endfor
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Timeline per macchina --}}
            <section class="card wf-vista" id="vista-timeline" aria-label="Timeline per macchina" hidden>
                <h2>Dal {{ $tDal->format('d/m') }} al {{ $tAl->format('d/m/Y') }}</h2>
                <div class="wf-timeline">
                    <div class="wf-riga wf-testa-tl">
                        <div class="wf-nome"></div>
                        <div class="wf-pista">
                            @for ($l = $tDal->copy(); $l->lte($tAl); $l->addWeek())
                                <span class="wf-sett" style="left: {{ $pos($l) }}%; width: {{ 700 / $tGiorni }}%">S{{ $l->isoWeek }} · {{ $l->format('d/m') }}</span>
                            @endfor
                        </div>
                    </div>
                    @foreach ($righe as $r)
                        @php
                            $fasiRiga = $fasi->filter(fn ($f) => $diRiga($f, $r['codice']) && $dentro($f))->values();
                            $puntiRiga = $puntuali->filter(fn ($v) => $diRiga($v, $r['codice']) && $dentro($v));
                        @endphp
                        <div class="wf-riga">
                            <div class="wf-nome">
                                @if ($r['codice'])<a href="{{ route('pannello', [$progetto->slug, 'm' => $r['codice']]) }}"><span class="num">{{ $r['num'] }}</span>{{ $r['nome'] }}</a>@else<b>{{ $r['nome'] }}</b>@endif
                            </div>
                            <div class="wf-pista" style="--corsie: {{ max(1, $fasiRiga->count()) }}">
                                @if ($oggi->between($tDal, $tAl))<span class="wf-oggi" style="left: {{ $pos($oggi) + 50 / $tGiorni }}%" title="Oggi"></span>@endif
                                @if ($scadenza && \Illuminate\Support\Carbon::parse($scadenza)->between($tDal, $tAl))<span class="wf-fine" style="left: {{ $pos(\Illuminate\Support\Carbon::parse($scadenza)) + 100 / $tGiorni }}%" title="Scadenza {{ \Illuminate\Support\Carbon::parse($scadenza)->format('d/m/Y') }}"></span>@endif
                                @foreach ($fasiRiga as $i => $f)
                                    @php $da = $f['inizio']->max($tDal); $a = $f['fine']->min($tAl); @endphp
                                    <button type="button" class="wf-fase {{ $classe($f) }}" data-v="{{ $f['chiave'] }}" data-tipo="fase" title="{{ $f['titolo'] }} · {{ $f['inizio']->format('d/m') }}–{{ $f['fine']->format('d/m') }}"
                                            style="left: {{ $pos($da) }}%; width: {{ $largo($da, $a) }}%; top: calc({{ $i }} * var(--corsia))">{{ $f['titolo'] }}</button>
                                @endforeach
                                @foreach ($puntiRiga as $v)
                                    <button type="button" class="wf-punto {{ $classe($v) }}" data-v="{{ $v['chiave'] }}" data-tipo="{{ $v['tipo'] }}"
                                            style="left: {{ $pos($v['inizio']) + 50 / $tGiorni }}%" title="{{ $v['inizio']->format('d/m') }}{{ $v['ora'] ? ' '.$v['ora'] : '' }} · {{ $v['titolo'] }}"><span class="sr">{{ $v['titolo'] }}</span></button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="note">Una riga per macchina; in "Generale" le voci senza macchina. Barre: fasi del planning. Punti: appuntamenti, eventi Google, scadenze e mail. Linea blu: oggi; linea rossa: scadenza del progetto.</p>
            </section>
        </div>

        {{-- Dettaglio della voce scelta --}}
        <aside class="card wf-dettagli" aria-live="polite">
            <p class="empty" id="wf-nessuno">Tocca una voce del calendario o della timeline.</p>
            @foreach ($voci as $v)
                <div class="wf-dett" id="wd-{{ $v['chiave'] }}" hidden>
                    <div class="meta"><span class="wf-pallino {{ 'wf-'.$v['tipo'] }}"></span> <span class="chip {{ $v['stato'] === 'scaduto' || $v['stato'] === 'rischio' ? 'chip-rischio' : ($v['stato'] === 'proposto' ? 'chip-attesa' : '') }}">{{ $v['etichetta'] }}</span></div>
                    <h3>{{ $v['titolo'] }}</h3>
                    <p class="mono">
                        @if ($v['tipo'] === 'fase')
                            dal {{ $v['inizio']->format('d/m/Y') }} al {{ $v['fine']->format('d/m/Y') }}
                        @else
                            {{ $v['inizio']->format('d/m/Y') }}{{ $v['ora'] ? ' · '.$v['ora'] : '' }}@if (! $v['fine']->isSameDay($v['inizio'])) → {{ $v['fine']->format('d/m/Y') }}@endif
                        @endif
                    </p>
                    @if ($v['luogo'])<p><b>Dove:</b> {{ $v['luogo'] }}</p>@endif
                    @if ($v['dettaglio'] && $v['dettaglio'] !== $v['titolo'])<p class="wf-testo">{{ $v['dettaglio'] }}</p>@endif
                    @if ($v['macchine'])
                        <ul class="plain">
                            @foreach ($v['macchine'] as $c)
                                <li><a href="{{ route('pannello', [$progetto->slug, 'm' => $c]) }}">Scheda {{ $nomeMacchina($c) }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                    @foreach ($v['link'] as [$testo, $url])
                        <a class="link-azione" href="{{ $url }}">{{ $testo }}</a>
                    @endforeach
                    @if ($v['fonte'])<p class="src">Fonte: {{ $v['fonte'] }}</p>@endif
                </div>
            @endforeach
        </aside>
    </div>
</div>
@endsection

@push('script')
<script>
(() => {
    const chiave = 'rs-wf-{{ $progetto->slug }}';
    const leggi = k => { try { return localStorage.getItem(chiave + k); } catch (e) { return null; } };
    const scrivi = (k, v) => { try { localStorage.setItem(chiave + k, v); } catch (e) {} };

    const vista = v => {
        document.querySelectorAll('[data-vista]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.vista === v)));
        document.querySelectorAll('.wf-vista').forEach(s => s.hidden = s.id !== 'vista-' + v);
        scrivi('-vista', v);
    };
    document.querySelectorAll('[data-vista]').forEach(b => b.addEventListener('click', () => vista(b.dataset.vista)));
    vista(leggi('-vista') === 'timeline' ? 'timeline' : 'calendario');

    const spenti = new Set((leggi('-spenti') || '').split(',').filter(Boolean));
    const filtra = () => {
        document.querySelectorAll('[data-tipo]').forEach(el => el.hidden = spenti.has(el.dataset.tipo));
        document.querySelectorAll('[data-tipo-filtro]').forEach(c => c.checked = !spenti.has(c.dataset.tipoFiltro));
        scrivi('-spenti', [...spenti].join(','));
    };
    document.querySelectorAll('[data-tipo-filtro]').forEach(c => c.addEventListener('change', () => {
        c.checked ? spenti.delete(c.dataset.tipoFiltro) : spenti.add(c.dataset.tipoFiltro);
        filtra();
    }));
    filtra();

    const aside = document.querySelector('.wf-dettagli');
    document.querySelectorAll('[data-v]').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll('[data-v].sel').forEach(x => x.classList.remove('sel'));
        document.querySelectorAll('[data-v="' + b.dataset.v + '"]').forEach(x => x.classList.add('sel'));
        document.querySelectorAll('.wf-dett').forEach(d => d.hidden = d.id !== 'wd-' + b.dataset.v);
        document.getElementById('wf-nessuno').hidden = true;
        if (matchMedia('(max-width: 900px)').matches) aside.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    }));
})();
</script>
@endpush
