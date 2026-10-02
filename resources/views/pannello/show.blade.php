@extends('layouts.app')

@section('titolo', $progetto->nome.' 4.0 · Rolli Salpa')
@section('classe', 'p-'.$progetto->slug)

@php
    $stati = [
        'ok' => ['✓', 'Fatto'], 'corso' => ['▸', 'In corso'], 'attesa' => ['…', 'In attesa di altri'],
        'rischio' => ['!', 'Bloccato / a rischio'], 'no' => ['·', 'Non iniziato'], 'na' => ['–', 'Non applicabile'],
    ];
    $lamp = fn (string $s) => '<span class="lamp s-'.$s.'" title="'.e($stati[$s][1]).'">'.$stati[$s][0].'</span>';
    $fasi = collect($progetto->fasi ?? [])->filter(fn ($f) => is_array($f) && count($f) >= 2)->values();
    $macchine = $progetto->machines;
    $contate = $macchine->reject(fn ($m) => $m->dato('escludiDaiConteggi'));
    $n = $contate->count();
    $scadenza = $progetto->info('scadenza');
    $giorni = $scadenza ? max(0, (int) ceil(now()->diffInDays(\Illuminate\Support\Carbon::parse($scadenza)->endOfDay(), false))) : null;
    $aperte = $macchine->sum(fn ($m) => $m->questions->where('fatto', false)->count());
    $rischio = $contate->filter(fn ($m) => in_array('rischio', (array) $m->dato('fasi', []), true))->count();
    $contatori = $progetto->info('contatori') ?: [['tipo' => 'giorni', 'etichetta' => 'giorni alla scadenza'], ['tipo' => 'domande', 'etichetta' => 'domande aperte']];
    $numDi = $macchine->mapWithKeys(fn ($m) => [$m->codice => $m->dato('num', $m->codice)]);
    $perChi = $macchine->flatMap(fn ($m) => $m->questions->where('fatto', false)->map(fn ($q) => [$m, $q]))
        ->groupBy(fn ($x) => $x[1]->chi ?: 'Da assegnare')->sortByDesc(fn ($g) => count($g));
    $tag = fn ($m) => $m->dato('num') === '—' ? '#generale' : '#'.$m->dato('num', $m->codice);
@endphp

@section('contenuto')
<div class="wrap">
    @include('pannello.aggiornamenti')
    @include('pannello.da-guardare')
    <header class="head">
        <div>
            <h1>{{ $progetto->info('titolo', $progetto->nome.' · Industria 4.0') }}</h1>
            @if ($progetto->info('sottotitolo'))<p>{{ $progetto->info('sottotitolo') }}</p>@endif
            <p class="status-line">
                @foreach (['fase' => 'Fase', 'rolling' => 'Rolling letto', 'mail' => 'Mail lette fino al', 'aggiornato' => 'Aggiornato'] as $k => $l)
                    @if ($progetto->info($k)){{ $l }}: {{ $progetto->info($k) }} · @endif
                @endforeach
                Sincronizzato {{ $progetto->synced_at?->format('d/m/Y H:i') ?? '–' }}
            </p>
        </div>
        <div class="gauges">
            @foreach ($contatori as $c)
                @php
                    [$v, $alert] = match ($c['tipo'] ?? '') {
                        'giorni' => [$giorni ?? '–', $giorni !== null && $giorni < 21],
                        'fase' => [$n ? $contate->filter(fn ($m) => $m->fase($c['fase'] ?? '') === 'ok')->count().'/'.$n : '–', false],
                        'rischio' => [$rischio, $rischio > 0],
                        'domande' => [$aperte, false],
                        default => ['–', false],
                    };
                @endphp
                <div class="gauge {{ $alert ? 'alert' : '' }}"><b>{{ $v }}</b><span>{{ $c['etichetta'] ?? '' }}</span></div>
            @endforeach
        </div>
    </header>

    <div class="azioni"><button type="button" class="btn" data-apri-appunto="">+ Nuovo appunto</button></div>
    @include('pannello.appunto-form')

    @if ($progetto->info('richiesta') || $progetto->info('posizione'))
        <div class="brief">
            @if ($progetto->info('richiesta'))<b>Richiesta del cliente</b><span>{{ $progetto->info('richiesta') }}</span>@endif
            @if ($progetto->info('posizione'))<b>Posizione Omniasoft</b><span>{{ $progetto->info('posizione') }}</span>@endif
        </div>
    @endif

    <div class="main">
        <section>
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;align-items:center">
                <h2>{{ $progetto->info('titoloTabella', 'Stato per macchina') }}</h2>
                <div class="filters" id="filters">
                    <button type="button" data-f="all" aria-pressed="true">Tutte</button>
                    <button type="button" data-f="attn" aria-pressed="false">Da seguire</button>
                </div>
            </div>
            <div class="board">
                <table>
                    <thead><tr><th>Macchina</th>@foreach ($fasi as $f)<th>{{ $f[1] }}</th>@endforeach</tr></thead>
                    <tbody id="tbody">
                    @php $gruppo = null; @endphp
                    @forelse ($macchine as $m)
                        @if ($m->dato('gruppo') !== $gruppo)
                            @php $gruppo = $m->dato('gruppo'); @endphp
                            <tr class="group"><td colspan="{{ $fasi->count() + 1 }}">{{ $gruppo }}</td></tr>
                        @endif
                        <tr class="row" tabindex="0" data-m="{{ $m->codice }}" data-attn="{{ $m->daSeguire() ? 1 : 0 }}" aria-label="{{ $m->dato('nome') }}">
                            <td class="name"><span class="num">{{ $m->dato('num', $m->codice) }}</span>{{ $m->dato('nome') }}<span class="sub">{{ $m->dato('fornitore') }}</span></td>
                            @foreach ($fasi as $f)<td>{!! $lamp($m->fase($f[0])) !!}</td>@endforeach
                        </tr>
                    @empty
                        <tr><td class="empty" colspan="{{ $fasi->count() + 1 }}">Nessuna macchina: i dati arrivano alla prossima sincronizzazione dal PC.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="legend">@foreach ($stati as $k => $s)<span>{!! $lamp($k) !!}{{ $s[1] }}</span>@endforeach</div>

            @foreach ($macchine as $m)
                @php
                    $kv = collect(['Fornitore' => 'fornitore', 'Referenti' => 'referenti', 'Anno investimento' => 'anno', 'Rete' => 'rete', 'IP' => 'ip', 'Protocollo' => 'protocollo', 'Requisiti' => 'requisiti', 'Stima' => 'stima'])
                        ->map(fn ($k) => $m->dato($k))->filter();
                    $storia = $eventi->filter(fn ($e) => in_array($m->codice, $e->macchine ?? [], true));
                    $suoiAppunti = $appunti->filter(fn ($a) => in_array($m->codice, $a->macchine ?? [], true));
                @endphp
                <div class="detail" id="d-{{ $m->codice }}" hidden>
                    <div class="top">
                        <h3><span class="num">{{ $m->dato('num', $m->codice) }}</span>{{ $m->dato('nome') }}</h3>
                        @if ($m->dato('aggiornato'))<span class="src">Scheda aggiornata il {{ \Illuminate\Support\Carbon::parse($m->dato('aggiornato'))->format('d/m/Y') }}</span>@endif
                    </div>
                    <div class="azioni"><button type="button" class="btn btn-sec" data-apri-appunto="{{ $m->codice }}">+ Appunto su questa macchina</button></div>
                    @if ($m->dato('prossimo'))<div class="next"><b>Prossimo passo</b>{{ $m->dato('prossimo') }}</div>@endif
                    <div class="phases">@foreach ($fasi as $f)<div class="phase">{!! $lamp($m->fase($f[0])) !!}{{ $f[1] }}</div>@endforeach</div>
                    @if ($kv->isNotEmpty())
                        <dl class="kv">@foreach ($kv as $k => $v)<div><dt>{{ $k }}</dt><dd class="{{ $k === 'IP' ? 'mono' : '' }}">{{ $v }}</dd></div>@endforeach</dl>
                    @endif
                    @if ($m->questions->isNotEmpty())
                        <div class="blocco"><h2>Da chiedere</h2>
                            @foreach ($m->questions as $q)
                                <div><div class="who">{{ $q->chi }}</div>@include('pannello.domanda', ['q' => $q, 'm' => $m, 'tag' => $tag($m), 'dove' => 'd'])</div>
                            @endforeach
                        </div>
                    @endif
                    @if ($m->dato('note'))
                        <div class="blocco"><h2>Fatti accertati</h2>
                            <ul class="plain">@foreach ($m->dato('note') as $nota)<li class="note">{{ $nota['testo'] ?? '' }} <span class="src">— {{ $nota['fonte'] ?? '' }}</span></li>@endforeach</ul>
                        </div>
                    @endif
                    @if ($suoiAppunti->isNotEmpty())
                        <div class="blocco"><h2>Appunti</h2>@foreach ($suoiAppunti as $a)@include('pannello.appunto', ['a' => $a])@endforeach</div>
                    @endif
                    @if ($storia->isNotEmpty())
                        <div class="blocco"><h2>Storia</h2>@foreach ($storia as $e)@include('pannello.evento', ['e' => $e, 'tags' => []])@endforeach</div>
                    @endif
                </div>
            @endforeach
        </section>

        <aside>
            <div class="card">
                <h2>Da chiedere</h2>
                @forelse ($perChi as $chi => $voci)
                    <div class="blocco"><div class="who">{{ $chi }} · {{ count($voci) }}</div>
                        @foreach ($voci as [$m, $q])@include('pannello.domanda', ['q' => $q, 'm' => $m, 'tag' => $tag($m), 'dove' => 'a'])@endforeach
                    </div>
                @empty
                    <p class="empty">Nessuna domanda aperta.</p>
                @endforelse
            </div>
            <div class="card">
                <h2>Appunti dal sito</h2>
                @forelse ($appunti->take(8) as $a)
                    @include('pannello.appunto', ['a' => $a])
                @empty
                    <p class="empty">Nessun appunto. Quelli presi in sede o in riunione compaiono qui e vengono elaborati dal PC ogni ora.</p>
                @endforelse
            </div>
            <div class="card">
                <h2>Ultimi arrivi</h2>
                @forelse ($eventi->take(12) as $e)
                    @include('pannello.evento', ['e' => $e, 'tags' => collect($e->macchine ?? [])->map(fn ($c) => $numDi[$c] ?? null)->filter(fn ($t) => $t && $t !== '—')])
                @empty
                    <p class="empty">Mail, aggiornamenti del rolling e verbali compaiono qui, con la fonte.</p>
                @endforelse
            </div>
        </aside>
    </div>
</div>
@endsection

@push('script')
<script src="{{ asset('js/appunti.js') }}?v={{ filemtime(public_path('js/appunti.js')) }}"></script>
<script>
(() => {
    const chiave = 'rs-{{ $progetto->slug }}';
    const leggi = k => { try { return localStorage.getItem(chiave + k); } catch (e) { return null; } };
    const scrivi = (k, v) => { try { localStorage.setItem(chiave + k, v); } catch (e) {} };

    const scegli = (codice, scorri) => {
        document.querySelectorAll('tr.row').forEach(r => r.classList.toggle('sel', r.dataset.m === codice));
        document.querySelectorAll('.detail').forEach(d => d.hidden = d.id !== 'd-' + codice);
        scrivi('-sel', codice);
        const d = document.getElementById('d-' + codice);
        if (d && scorri) d.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest' });
    };
    document.querySelectorAll('tr.row').forEach(r => {
        r.addEventListener('click', () => scegli(r.dataset.m, true));
        r.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); scegli(r.dataset.m, true); } });
    });
    const sel = leggi('-sel');
    if (sel && document.getElementById('d-' + sel)) scegli(sel, false);

    const filtra = f => {
        document.querySelectorAll('#filters button').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.f === f)));
        document.querySelectorAll('tr.row').forEach(r => r.hidden = f === 'attn' && r.dataset.attn !== '1');
        scrivi('-filtro', f);
    };
    document.getElementById('filters').addEventListener('click', e => { const b = e.target.closest('button'); if (b) filtra(b.dataset.f); });
    filtra(leggi('-filtro') || 'all');

    const token = document.querySelector('meta[name=csrf-token]').content;

    // --- Da guardare: novita' viste e decisioni prese ---
    const posta = (url, corpo) => fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(corpo || {}) });
    document.addEventListener('click', async e => {
        const v = e.target.closest('[data-visto]');
        if (v) { v.disabled = true; const r = await posta(v.dataset.visto, { visto: true }); if (r.ok) v.closest('.ev').remove(); else { v.disabled = false; v.textContent = 'Non salvato, riprova'; } return; }
        const t = e.target.closest('[data-tutti-visti]');
        if (t) { t.disabled = true; const r = await posta(t.dataset.tuttiVisti); if (r.ok) location.reload(); else t.disabled = false; }
    });
    document.addEventListener('change', async e => {
        const box = e.target.closest('input[data-decisione]');
        if (!box) return;
        box.disabled = true;
        const r = await posta(box.dataset.decisione, { fatta: box.checked }).catch(() => null);
        box.disabled = false;
        if (r && r.ok) box.closest('.ask').classList.toggle('done', box.checked);
        else { box.checked = !box.checked; box.closest('.ask').querySelector('.err').textContent = 'Non salvato, riprova.'; }
    });

    document.addEventListener('change', async e => {
        const box = e.target.closest('input[data-q]');
        if (!box) return;
        const id = box.dataset.q, val = box.checked;
        const tutte = document.querySelectorAll(`input[data-q="${id}"]`);
        const aggiorna = v => tutte.forEach(b => { b.checked = v; b.closest('.ask').classList.toggle('done', v); });
        aggiorna(val);
        tutte.forEach(b => b.disabled = true);
        try {
            const r = await fetch(box.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ fatto: val }) });
            if (!r.ok) throw new Error(r.status);
        } catch (err) {
            aggiorna(!val);
            box.closest('.ask').querySelector('.err').textContent = 'Non salvato, riprova (sessione scaduta? ricarica la pagina).';
        } finally {
            tutte.forEach(b => b.disabled = false);
        }
    });
})();
</script>
@endpush
