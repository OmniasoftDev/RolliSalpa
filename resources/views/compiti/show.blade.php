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
    // assegnati raggruppati per persona, nell'ordine della rubrica; chi ha compiti scaduti va in cima
    $perPersona = $aperti->groupBy(fn ($c) => $c->persona ?: '')
        ->sortBy(fn ($g, $k) => [$g->contains(fn ($c) => $c->scaduto()) ? 0 : 1, $perCodice[$k]->ordine ?? 999]);
    $gruppi = $persone->groupBy(fn ($p) => $p->gruppo ?: 'Altri');
@endphp

@section('contenuto')
<div class="wrap compiti-pagina">
    <header class="head">
        <div>
            <h1>Persone e compiti · {{ $progetto->nome }}</h1>
            <p>Chi fa cosa sulle macchine e cosa hai chiesto a ciascuno. I compiti proposti da Claude (da mail, appunti, visite) restano "da confermare" finché non li confermi o li scarti.</p>
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
            <select id="compito-persona" name="persona" class="sel">
                <option value="">Da assegnare</option>
                @foreach ($gruppi as $g => $ps)
                    <optgroup label="{{ $g }}">@foreach ($ps as $p)<option value="{{ $p->codice }}">{{ $p->nome }}{{ $p->azienda ? ' · '.$p->azienda : '' }}</option>@endforeach</optgroup>
                @endforeach
            </select>
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

    <div class="main">
        <section>
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
                                <select id="cp-{{ $c->id }}" class="sel" data-campo="persona">
                                    <option value="">Da assegnare</option>
                                    @foreach ($gruppi as $g => $ps)
                                        <optgroup label="{{ $g }}">@foreach ($ps as $p)<option value="{{ $p->codice }}" @selected($p->codice === $c->persona)>{{ $p->nome }}</option>@endforeach</optgroup>
                                    @endforeach
                                </select>
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

            <div class="card">
                <h2>Assegnati <span class="conta">{{ $aperti->count() }}</span></h2>
                @forelse ($perPersona as $codice => $voci)
                    @php $p = $perCodice[$codice] ?? null; @endphp
                    <div class="blocco">
                        <div class="who">{{ $nomeDi($codice) }}@if ($p?->azienda) <span class="src">· {{ $p->azienda }}</span>@endif · {{ $voci->count() }}</div>
                        @foreach ($voci as $c)
                            <div class="compito {{ $c->scaduto() ? 'scaduto' : '' }}" data-compito="{{ route('compiti.aggiorna', $c) }}">
                                <div class="t">{{ $c->testo }}</div>
                                <div class="meta">
                                    @if ($c->scadenza)<span class="chip {{ $c->scaduto() ? 'chip-rischio' : '' }}">{{ $c->scaduto() ? 'scaduto il' : 'entro il' }} {{ $c->scadenza->format('d/m') }}</span>@endif
                                    @if ($tagMacchine($c->macchine))<span class="tag">{{ $tagMacchine($c->macchine) }}</span>@endif
                                    @if ($c->fonte)<span class="src">{{ $c->fonte }}</span>@endif
                                    @if ($c->assegnato_il)<span class="src">assegnato il {{ $c->assegnato_il->format('d/m') }}</span>@endif
                                </div>
                                <details class="chiudi">
                                    <summary class="link-azione">Chiudi o modifica</summary>
                                    <div class="riga-campi">
                                        <label class="campo" for="ca-{{ $c->id }}">A chi
                                            <select id="ca-{{ $c->id }}" class="sel" data-campo="persona">
                                                <option value="">Da assegnare</option>
                                                @foreach ($gruppi as $g => $ps)
                                                    <optgroup label="{{ $g }}">@foreach ($ps as $pp)<option value="{{ $pp->codice }}" @selected($pp->codice === $c->persona)>{{ $pp->nome }}</option>@endforeach</optgroup>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="campo" for="cd-{{ $c->id }}">Entro il <input type="date" id="cd-{{ $c->id }}" class="sel" data-campo="scadenza" value="{{ $c->scadenza?->format('Y-m-d') }}"></label>
                                    </div>
                                    <label class="campo" for="ce-{{ $c->id }}">Esito (facoltativo)
                                        <textarea id="ce-{{ $c->id }}" rows="2" data-campo="esito" placeholder="Es.: tabella arrivata con mail del 07/10.">{{ $c->esito }}</textarea>
                                    </label>
                                    <div class="azioni">
                                        <button type="button" class="btn" data-azione="fatto">Fatto</button>
                                        <button type="button" class="btn btn-sec" data-azione="aperto">Salva modifiche</button>
                                        <button type="button" class="link-elimina" data-azione="annullato">Annulla compito</button>
                                        <span class="err" aria-live="polite"></span>
                                    </div>
                                </details>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <p class="empty">Nessun compito assegnato.</p>
                @endforelse
            </div>

            @if ($chiusi->isNotEmpty())
                <details class="card">
                    <summary><h2 style="display:inline">Chiusi <span class="conta">{{ $chiusi->count() }}</span></h2></summary>
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
        </section>

        <aside>
            <div class="card">
                <h2>Persone del progetto</h2>
                @forelse ($gruppi as $g => $ps)
                    <div class="blocco">
                        <div class="gruppo-persone">{{ $g }}</div>
                        @foreach ($ps as $p)
                            @php $suoi = $aperti->where('persona', $p->codice); $suoiScaduti = $suoi->filter(fn ($c) => $c->scaduto()); @endphp
                            <div class="persona">
                                <div class="who">{{ $p->nome }}@if ($p->azienda) <span class="src">· {{ $p->azienda }}</span>@endif</div>
                                @if ($p->ruolo)<div class="note">{{ $p->ruolo }}</div>@endif
                                <div class="meta">
                                    @if ($tagMacchine($p->macchine))<span class="tag">{{ $tagMacchine($p->macchine) }}</span>@endif
                                    @if ($p->contatti)<span class="mono">{{ $p->contatti }}</span>@endif
                                </div>
                                <div class="meta">
                                    @if ($suoi->isNotEmpty())<span class="chip {{ $suoiScaduti->isNotEmpty() ? 'chip-rischio' : '' }}">{{ $suoi->count() }} aperti{{ $suoiScaduti->isNotEmpty() ? ', '.$suoiScaduti->count().' scaduti' : '' }}</span>@endif
                                    <button type="button" class="link-azione" data-apri-compito="{{ $p->codice }}">+ compito</button>
                                    @if ($p->fonte)<span class="src">{{ $p->fonte }}</span>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <p class="empty">La rubrica arriva dal PC alla prossima sincronizzazione.</p>
                @endforelse
            </div>
        </aside>
    </div>
</div>
@endsection

@push('script')
<script>
(() => {
    const token = document.querySelector('meta[name=csrf-token]').content;
    const posta = (url, corpo) => fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(corpo) });
    const errore = async r => { try { const j = await r.json(); return Object.values(j.errors || {}).flat()[0] || j.message || 'Non salvato, riprova.'; } catch (e) { return 'Non salvato, riprova (sessione scaduta? ricarica la pagina).'; } };

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
