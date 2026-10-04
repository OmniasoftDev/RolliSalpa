@extends('layouts.app')

@section('titolo', 'Persone e compiti · '.$progetto->nome)
@section('classe', 'p-'.$progetto->slug)

@php
    $perCodice = $persone->keyBy('codice');
    $numDi = $macchine->mapWithKeys(fn ($m) => [$m->codice => $m->dato('num', $m->codice)]);
    $tagMacchine = fn ($codici) => collect($codici ?? [])->map(fn ($c) => '#'.($numDi[$c] ?? $c))->reject(fn ($t) => $t === '#—')->join(' ');
    $nomeDi = fn ($codice) => $codice ? ($perCodice[$codice]->nome ?? $codice) : 'Da assegnare';
    $proposti = $compiti->where('stato', 'proposto');
    $aperti = $compiti->where('stato', 'aperto');
    $scaduti = $aperti->filter(fn ($c) => $c->scaduto());
    $chiusi = $compiti->whereIn('stato', ['fatto', 'annullato'])->sortByDesc('chiuso_il');
    $gruppi = $persone->groupBy(fn ($p) => $p->gruppo ?: 'Altri');
    // un box per persona: dentro il gruppo prima chi ha scaduti, poi assegnati, poi da confermare, poi chi non ha nulla
    $peso = fn ($codice) => $scaduti->where('persona', $codice)->isNotEmpty() ? 0
        : ($aperti->where('persona', $codice)->isNotEmpty() ? 1 : ($proposti->where('persona', $codice)->isNotEmpty() ? 2 : 3));
    $boxGruppi = $gruppi->map(fn ($ps) => $ps->sortBy(fn ($p) => [$peso($p->codice), $p->ordine])->values());
    $senzaPersona = $aperti->filter(fn ($c) => ! $c->persona);
    $opzioniPersone = function (?string $scelta) use ($gruppi) {
        $html = '<option value="">Da assegnare</option>';
        foreach ($gruppi as $g => $ps) {
            $html .= '<optgroup label="'.e($g).'">';
            foreach ($ps as $p) {
                $html .= '<option value="'.e($p->codice).'"'.($p->codice === $scelta ? ' selected' : '').'>'.e($p->nome).'</option>';
            }
            $html .= '</optgroup>';
        }
        return $html;
    };
@endphp

@section('contenuto')
<div class="wrap compiti-pagina">
    <header class="head">
        <div>
            <h1>Persone e compiti · {{ $progetto->nome }}</h1>
            <p>Un box per ogni persona: cosa le hai chiesto, cosa è scaduto, cosa ha già chiuso. I compiti proposti da Claude (da mail, appunti, visite) restano "da confermare" finché non li confermi o li scarti.</p>
        </div>
        <div class="gauges">
            <div class="gauge {{ $proposti->isNotEmpty() ? 'alert-wait' : '' }}"><b>{{ $proposti->count() }}</b><span>da confermare</span></div>
            <div class="gauge"><b>{{ $aperti->count() }}</b><span>assegnati</span></div>
            <div class="gauge {{ $scaduti->isNotEmpty() ? 'alert' : '' }}"><b>{{ $scaduti->count() }}</b><span>scaduti</span></div>
            <div class="gauge"><b>{{ $chiusi->where('stato', 'fatto')->count() }}</b><span>fatti</span></div>
        </div>
    </header>

    <div class="azioni"><button type="button" class="btn" data-apri-compito="">+ Nuovo compito</button></div>

    <form class="appunto-form" id="compito-form" data-url="{{ route('compiti.store', $progetto->slug) }}" hidden>
        <div class="top"><h3>Nuovo compito · {{ $progetto->nome }}</h3><button type="button" class="esci" data-chiudi-compito>Chiudi</button></div>
        <label for="compito-persona" class="campo">A chi
            <select id="compito-persona" name="persona" class="sel">{!! $opzioniPersone(null) !!}</select>
        </label>
        <label for="compito-testo" class="campo">Cosa deve fare
            <textarea id="compito-testo" name="testo" rows="4" placeholder="Es.: mandare la tabella variabili dei forni 07 e 08 con indirizzi e tipi."></textarea>
        </label>
        <fieldset class="scelta-macchine">
            <legend>Macchine (nessuna = progetto in generale)</legend>
            @foreach ($macchine->reject(fn ($m) => $m->dato('escludiDaiConteggi')) as $m)
                <label for="cm-{{ $m->codice }}"><input type="checkbox" id="cm-{{ $m->codice }}" name="macchine[]" value="{{ $m->codice }}"> <span class="num">{{ $m->dato('num', $m->codice) }}</span>{{ $m->dato('nome') }}</label>
            @endforeach
        </fieldset>
        <div class="riga-campi">
            <label for="compito-scadenza" class="campo">Entro il <input type="date" id="compito-scadenza" name="scadenza" class="sel"></label>
            <label for="compito-fonte" class="campo">Nato da <input type="text" id="compito-fonte" name="fonte" class="sel" maxlength="250" placeholder="es. Visita 06/10, Mail 03/10 Merlotti"></label>
        </div>
        <p class="errore" id="compito-errore" aria-live="polite"></p>
        <div class="azioni"><button type="submit" class="btn" id="compito-invia">Assegna</button></div>
    </form>

    <div class="card {{ $proposti->isNotEmpty() ? 'card-attesa' : '' }}">
        <h2>Da confermare <span class="conta">{{ $proposti->count() }}</span></h2>
        @forelse ($proposti as $c)
            <div class="compito" data-compito="{{ route('compiti.aggiorna', $c) }}">
                <div class="t">{{ $c->testo }}</div>
                <div class="meta">
                    @if ($tagMacchine($c->macchine))<span class="tag">{{ $tagMacchine($c->macchine) }}</span>@endif
                    @if ($c->fonte)<span class="src">{{ $c->fonte }}</span>@endif
                </div>
                <div class="riga-campi">
                    <label class="campo" for="cp-{{ $c->id }}">A chi
                        <select id="cp-{{ $c->id }}" class="sel" data-campo="persona">{!! $opzioniPersone($c->persona) !!}</select>
                    </label>
                    <label class="campo" for="cs-{{ $c->id }}">Entro il <input type="date" id="cs-{{ $c->id }}" class="sel" data-campo="scadenza" value="{{ $c->scadenza?->format('Y-m-d') }}"></label>
                </div>
                <div class="azioni">
                    <button type="button" class="btn" data-azione="aperto">Conferma e assegna</button>
                    <button type="button" class="btn btn-sec" data-azione="annullato">Scarta</button>
                    <span class="err" aria-live="polite"></span>
                </div>
            </div>
        @empty
            <p class="empty">Nessuna proposta. Quando arriva una mail o un appunto con qualcosa da far fare a qualcuno, Claude la mette qui.</p>
        @endforelse
    </div>

    <div class="filtro-persone">
        <h2>Per persona</h2>
        <label for="solo-con-compiti"><input type="checkbox" id="solo-con-compiti"> Solo chi ha compiti</label>
    </div>

    @if ($senzaPersona->isNotEmpty())
        <div class="griglia-persone">
            <div class="box-persona stato-attesa" data-con-compiti>
                <div class="testa"><div class="who">Da assegnare</div><div class="conti"><span class="chip">{{ $senzaPersona->count() }} aperti</span></div></div>
                <div class="lista">
                    @foreach ($senzaPersona as $c)
                        @include('compiti.aperto')
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @forelse ($boxGruppi as $g => $ps)
        <div class="gruppo-persone">{{ $g }}</div>
        <div class="griglia-persone">
            @foreach ($ps as $p)
                @php
                    $suoi = $aperti->where('persona', $p->codice);
                    $suoiScaduti = $suoi->filter(fn ($c) => $c->scaduto());
                    $suoiProposti = $proposti->where('persona', $p->codice);
                    $suoiChiusi = $chiusi->where('persona', $p->codice);
                    $suoiFatti = $suoiChiusi->where('stato', 'fatto');
                    $stato = $suoiScaduti->isNotEmpty() ? 'stato-rischio' : ($suoi->isNotEmpty() ? 'stato-corso' : ($suoiProposti->isNotEmpty() ? 'stato-attesa' : 'stato-vuoto'));
                @endphp
                <div class="box-persona {{ $stato }}" @if ($suoi->isNotEmpty() || $suoiProposti->isNotEmpty()) data-con-compiti @endif>
                    <div class="testa">
                        <div>
                            <div class="who">{{ $p->nome }}@if ($p->azienda) <span class="src">· {{ $p->azienda }}</span>@endif</div>
                            @if ($p->ruolo)<div class="note">{{ $p->ruolo }}</div>@endif
                        </div>
                        <div class="conti">
                            @if ($suoiScaduti->isNotEmpty())<span class="chip chip-rischio">{{ $suoiScaduti->count() }} scaduti</span>@endif
                            @if ($suoi->isNotEmpty())<span class="chip chip-corso">{{ $suoi->count() }} aperti</span>@endif
                            @if ($suoiProposti->isNotEmpty())<span class="chip chip-attesa">{{ $suoiProposti->count() }} da confermare</span>@endif
                            @if ($suoiFatti->isNotEmpty())<span class="chip chip-ok">{{ $suoiFatti->count() }} fatti</span>@endif
                        </div>
                    </div>

                    @if ($suoi->isNotEmpty())
                        <div class="lista">
                            @foreach ($suoi as $c)
                                @include('compiti.aperto')
                            @endforeach
                        </div>
                    @endif

                    @if ($suoiProposti->isNotEmpty())
                        <div class="proposti-persona">
                            @foreach ($suoiProposti as $c)
                                <div><span class="chip chip-attesa">da confermare</span> {{ $c->testo }}</div>
                            @endforeach
                        </div>
                    @endif

                    @if ($suoiChiusi->isNotEmpty())
                        <details class="chiusi-persona">
                            <summary class="link-azione">Chiusi ({{ $suoiChiusi->count() }})</summary>
                            @foreach ($suoiChiusi as $c)
                                <div class="chiuso-riga">
                                    <span class="chip">{{ \App\Models\Compito::STATI[$c->stato] }} {{ $c->chiuso_il?->format('d/m') }}</span>
                                    <span class="{{ $c->stato === 'annullato' ? 'annullato' : '' }}">{{ $c->testo }}</span>
                                    @if ($c->esito)<div class="note">{{ $c->esito }}</div>@endif
                                </div>
                            @endforeach
                        </details>
                    @endif

                    <div class="piede">
                        @if ($tagMacchine($p->macchine))<span class="tag">{{ $tagMacchine($p->macchine) }}</span>@endif
                        @if ($p->contatti)<span class="mono">{{ $p->contatti }}</span>@endif
                        <button type="button" class="link-azione" data-apri-compito="{{ $p->codice }}">+ compito</button>
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <p class="empty">La rubrica arriva dal PC alla prossima sincronizzazione.</p>
    @endforelse

    @if ($chiusi->isNotEmpty())
        <details class="card">
            <summary><h2 style="display:inline">Tutti i chiusi <span class="conta">{{ $chiusi->count() }}</span></h2></summary>
            @foreach ($chiusi as $c)
                <div class="compito chiuso" data-compito="{{ route('compiti.aggiorna', $c) }}">
                    <div class="t {{ $c->stato === 'annullato' ? 'annullato' : '' }}">{{ $c->testo }}</div>
                    <div class="meta">
                        <span class="chip">{{ \App\Models\Compito::STATI[$c->stato] }} {{ $c->chiuso_il?->format('d/m') }}</span>
                        <span>{{ $nomeDi($c->persona) }}</span>
                        @if ($tagMacchine($c->macchine))<span class="tag">{{ $tagMacchine($c->macchine) }}</span>@endif
                    </div>
                    @if ($c->esito)<div class="note">{{ $c->esito }}</div>@endif
                    <div><button type="button" class="link-azione" data-azione="aperto">Riapri</button> <span class="err" aria-live="polite"></span></div>
                </div>
            @endforeach
        </details>
    @endif
</div>
@endsection

@push('script')
<script>
(() => {
    const token = document.querySelector('meta[name=csrf-token]').content;
    const posta = (url, corpo) => fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(corpo) });
    const errore = async r => { try { const j = await r.json(); return Object.values(j.errors || {}).flat()[0] || j.message || 'Non salvato, riprova.'; } catch (e) { return 'Non salvato, riprova (sessione scaduta? ricarica la pagina).'; } };

    // --- filtro: solo le persone con compiti aperti o da confermare (ricordato nel browser) ---
    const solo = document.getElementById('solo-con-compiti');
    const filtra = () => {
        document.querySelectorAll('.box-persona').forEach(b => b.hidden = solo.checked && !b.hasAttribute('data-con-compiti'));
        try { localStorage.setItem('compiti-solo', solo.checked ? '1' : ''); } catch (e) {}
    };
    try { solo.checked = localStorage.getItem('compiti-solo') === '1'; } catch (e) {}
    solo.addEventListener('change', filtra);
    filtra();

    // --- nuovo compito ---
    const form = document.getElementById('compito-form');
    document.addEventListener('click', e => {
        const a = e.target.closest('[data-apri-compito]');
        if (a) {
            form.hidden = false;
            form.querySelector('[name=persona]').value = a.dataset.apriCompito;
            form.scrollIntoView({ block: 'start' });
            form.querySelector('textarea').focus();
        }
        if (e.target.closest('[data-chiudi-compito]')) form.hidden = true;
    });
    form.addEventListener('submit', async e => {
        e.preventDefault();
        const b = document.getElementById('compito-invia'), err = document.getElementById('compito-errore');
        const corpo = {
            persona: form.persona.value || null,
            testo: form.testo.value,
            macchine: [...form.querySelectorAll('input[name="macchine[]"]:checked')].map(x => x.value),
            scadenza: form.scadenza.value || null,
            fonte: form.fonte.value || null,
        };
        b.disabled = true; err.textContent = '';
        const r = await posta(form.dataset.url, corpo).catch(() => null);
        if (r && r.ok) location.reload();
        else { err.textContent = r ? await errore(r) : 'Rete non raggiungibile, riprova.'; b.disabled = false; }
    });

    // --- conferma, chiusura, modifica, riapertura ---
    document.addEventListener('click', async e => {
        const b = e.target.closest('[data-azione]');
        if (!b) return;
        const box = b.closest('[data-compito]');
        const corpo = { stato: b.dataset.azione };
        box.querySelectorAll('[data-campo]').forEach(c => corpo[c.dataset.campo] = c.value || null);
        b.disabled = true;
        const r = await posta(box.dataset.compito, corpo).catch(() => null);
        if (r && r.ok) location.reload();
        else { box.querySelector('.err').textContent = r ? await errore(r) : 'Rete non raggiungibile, riprova.'; b.disabled = false; }
    });
})();
</script>
@endpush
