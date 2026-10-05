@extends('layouts.app')

@section('titolo', 'Il mio lavoro · '.$progetto->nome)
@section('classe', 'p-'.$progetto->slug)

@php
    $numDi = $macchine->mapWithKeys(fn ($m) => [$m->codice => $m->dato('num', $m->codice)]);
    $tagMacchine = fn ($codici) => collect($codici ?? [])->map(fn ($c) => '#'.($numDi[$c] ?? $c))->reject(fn ($t) => $t === '#—')->join(' ');
    $nomeDi = fn ($codice) => $codice ? ($persone[$codice]->nome ?? $codice) : 'Da assegnare';
    $gruppi = $persone->groupBy(fn ($p) => $p->gruppo ?: 'Altri');
    // il partial compiti.aperto usa $opzioniPersone per il modulo "Chiudi o modifica"
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
    $scadutiAttesi = $attesi->filter(fn ($c) => $c->scaduto());
    $oggi = today();
    $perGiorno = $calendario->groupBy(fn ($v) => $v['data']->format('Y-m-d'));
    $giorni = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'];
    $etichettaGiorno = fn ($d) => $d->isSameDay($oggi) ? 'Oggi' : ($d->isSameDay($oggi->copy()->addDay()) ? 'Domani' : ucfirst($giorni[$d->dayOfWeek]).' '.$d->format('d/m'));
    $numDomande = $domande->sum(fn ($g) => $g['domande']->count());
@endphp

@section('contenuto')
<div class="wrap lavoro-pagina">
    <header class="head">
        <div>
            <h1>Il mio lavoro · {{ $progetto->nome }}</h1>
            <p>Quello che tocca a te: i tuoi compiti, le decisioni, le risposte che aspetti dagli altri e le domande da fare. Per ogni voce Claude ti prepara la mail; il calendario propone una data per ognuna, che puoi accettare e mettere in Google Calendar.</p>
        </div>
        <div class="gauges">
            <div class="gauge"><b>{{ $miei->count() }}</b><span>miei compiti</span></div>
            <div class="gauge {{ $decisioni->isNotEmpty() ? 'alert-wait' : '' }}"><b>{{ $decisioni->count() }}</b><span>decisioni</span></div>
            <div class="gauge {{ $scadutiAttesi->isNotEmpty() ? 'alert' : '' }}"><b>{{ $attesi->count() }}</b><span>risposte attese</span></div>
            <div class="gauge"><b>{{ $numDomande }}</b><span>domande da fare</span></div>
        </div>
    </header>

    {{-- modulo unico per le bozze: si apre sotto la voce scelta --}}
    <form class="appunto-form bozza-form" id="bozza-form" data-url="{{ route('lavoro.bozza', $progetto->slug) }}" hidden>
        <div class="top"><h3>Prepara mail</h3><button type="button" class="esci" data-chiudi-bozza>Chiudi</button></div>
        <p class="note" id="bozza-punti"></p>
        <div class="riga-campi">
            <label class="campo" for="bozza-persona">A chi
                <select id="bozza-persona" name="persona" class="sel">
                    <option value="" data-mail="">— nessuno —</option>
                    @foreach ($gruppi as $g => $ps)
                        <optgroup label="{{ $g }}">
                            @foreach ($ps->reject(fn ($p) => $p->codice === $io) as $p)
                                <option value="{{ $p->codice }}" data-mail="{{ \App\Services\LavoroFrancesco::mailDi($p) }}">{{ $p->nome }}{{ \App\Services\LavoroFrancesco::mailDi($p) ? '' : ' (senza mail)' }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </label>
            <label class="campo" for="bozza-scopo">Tipo
                <select id="bozza-scopo" name="scopo" class="sel">
                    <option value="sollecito">Sollecito</option>
                    <option value="richiesta">Richiesta</option>
                    <option value="risposta">Risposta</option>
                    <option value="aggiornamento">Aggiornamento</option>
                </select>
            </label>
        </div>
        <label class="campo" for="bozza-nota">Cosa vuoi dire in più (facoltativo)
            <textarea id="bozza-nota" name="nota" rows="2" placeholder="Es.: rispondo alla sua mail del 04/10, propongo di sentirci mercoledì."></textarea>
        </label>
        <div class="azioni"><button type="button" class="btn" id="bozza-scrivi">Scrivi con Claude</button> <span class="err" id="bozza-errore" aria-live="polite"></span></div>
        <div class="bozza-risultato" id="bozza-risultato" hidden>
            <label class="campo" for="bozza-a">A <input type="text" id="bozza-a" class="sel" placeholder="indirizzo mail"></label>
            <label class="campo" for="bozza-oggetto">Oggetto <input type="text" id="bozza-oggetto" class="sel"></label>
            <label class="campo" for="bozza-corpo">Testo <textarea id="bozza-corpo" rows="12"></textarea></label>
            <div class="azioni">
                <a class="btn" id="bozza-outlook" href="#">Apri in Outlook</a>
                <button type="button" class="btn btn-sec" id="bozza-copia">Copia testo</button>
                <span class="note">Il sito non invia nulla: la mail parte solo da Outlook.</span>
            </div>
        </div>
    </form>

    <div class="lavoro-griglia">
        <div class="lavoro-col">
            <div class="card">
                <h2>Calendario proposto <span class="conta">{{ $calendario->count() }}</span></h2>
                <p class="note">Una data per ogni voce: la scadenza, oppure oggi se è passata o manca (per le risposte attese senza scadenza, una settimana dall'assegnazione). Cambia data e ora se vuoi (l'evento dura {{ \App\Services\LavoroFrancesco::MINUTI_EVENTO }} minuti), poi "Google Calendar" apre l'evento già compilato nel calendario Salpa-Rolli: lo salvi tu.</p>
                @forelse ($perGiorno as $giorno => $voci)
                    <div class="cal-giorno">
                        <h3>{{ $etichettaGiorno($voci->first()['data']) }}</h3>
                        @foreach ($voci as $v)
                            <div class="cal-voce tipo-{{ $v['tipo'] }} {{ $v['scaduto'] ? 'scaduto' : '' }}" data-cal="{{ $v['chiave'] }}"
                                 data-titolo="{{ $v['titolo'] }}" data-dettaglio="{{ trim(($v['dettaglio'] ? 'Fonte: '.$v['dettaglio']."\n" : '').route('lavoro', $progetto->slug)) }}">
                                <div class="t">{{ $v['titolo'] }}</div>
                                <div class="meta">
                                    <span class="chip {{ $v['scaduto'] ? 'chip-rischio' : ($v['tipo'] === 'mio' ? 'chip-corso' : ($v['tipo'] === 'decisione' ? 'chip-attesa' : '')) }}">{{ ['mio' => 'mio compito', 'decisione' => 'decisione', 'attesa' => 'risposta attesa'][$v['tipo']] }}</span>
                                    <span class="src">{{ $v['motivo'] }}</span>
                                </div>
                                <div class="cal-azioni">
                                    <label class="sr" for="cal-{{ $v['chiave'] }}">Data</label>
                                    <input type="date" id="cal-{{ $v['chiave'] }}" class="sel" value="{{ $v['data']->format('Y-m-d') }}">
                                    <label class="sr" for="ora-{{ $v['chiave'] }}">Ora</label>
                                    <input type="time" id="ora-{{ $v['chiave'] }}" class="sel" step="900" value="{{ \App\Services\LavoroFrancesco::ORA_EVENTO }}">
                                    <a class="btn btn-sec" target="_blank" rel="noopener" data-gcal
                                       href="{{ \App\Services\LavoroFrancesco::linkCalendario($v['titolo'], $v['data'], ($v['dettaglio'] ? 'Fonte: '.$v['dettaglio']."\n" : '').route('lavoro', $progetto->slug)) }}">Google Calendar</a>
                                    <span class="chip chip-ok" data-in-calendario hidden></span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <p class="empty">Niente da mettere in calendario: nessun compito aperto, decisione o risposta attesa.</p>
                @endforelse
            </div>
        </div>

        <div class="lavoro-col">
            <div class="card">
                <h2>I miei compiti <span class="conta">{{ $miei->count() }}</span>
                    <a class="link-azione" href="{{ route('compiti', $progetto->slug) }}">Persone e compiti</a></h2>
                @if ($proposti->isNotEmpty())<p class="note"><b>{{ $proposti->count() }}</b> proposti a te da confermare in <a href="{{ route('compiti', $progetto->slug) }}">Persone e compiti</a>.</p>@endif
                @forelse ($miei as $c)
                    @include('compiti.aperto')
                    <div class="voce-mail"><button type="button" class="link-azione" data-bozza data-scopo="aggiornamento" data-compiti="{{ $c->id }}" data-punti="{{ $c->testo }}">Prepara mail</button></div>
                @empty
                    <p class="empty">Nessun compito assegnato a te. Assegnatene uno da "Persone e compiti" scegliendo te stesso.</p>
                @endforelse
            </div>

            <div class="card {{ $decisioni->isNotEmpty() ? 'card-attesa' : '' }}">
                <h2>Decisioni da prendere <span class="conta">{{ $decisioni->count() }}</span></h2>
                @forelse ($decisioni as $d)
                    <label class="ask" for="dec-{{ $d->id }}">
                        <input type="checkbox" id="dec-{{ $d->id }}" data-decisione="{{ route('decisioni.segna', $d) }}">
                        <span><span class="t">{{ $d->testo }}</span>
                            @if ($tagMacchine($d->macchine))<span class="tag">{{ $tagMacchine($d->macchine) }}</span>@endif
                            @if ($d->fonte)<span class="sub">{{ $d->fonte }}@if ($d->data) · {{ $d->data->format('d/m') }}@endif</span>@endif
                            <span class="err" aria-live="polite"></span>
                        </span>
                    </label>
                @empty
                    <p class="empty">Nessuna decisione in sospeso.</p>
                @endforelse
            </div>

            <div class="card {{ $scadutiAttesi->isNotEmpty() ? 'card-rischio' : '' }}">
                <h2>Risposte che aspetto <span class="conta">{{ $attesi->count() }}</span></h2>
                @forelse ($attesi->groupBy(fn ($c) => $c->persona ?? '') as $codice => $suoi)
                    <div class="attesa-persona">
                        <div class="who">{{ $nomeDi($codice ?: null) }}@if ($codice && ($persone[$codice]->azienda ?? null)) <span class="src">· {{ $persone[$codice]->azienda }}</span>@endif</div>
                        @foreach ($suoi as $c)
                            <div class="compito {{ $c->scaduto() ? 'scaduto' : '' }}">
                                <div class="t">{{ $c->testo }}</div>
                                <div class="meta">
                                    @if ($c->scadenza)<span class="chip {{ $c->scaduto() ? 'chip-rischio' : '' }}">{{ $c->scaduto() ? 'scaduto il' : 'entro il' }} {{ $c->scadenza->format('d/m') }}</span>@endif
                                    @if ($tagMacchine($c->macchine))<span class="tag">{{ $tagMacchine($c->macchine) }}</span>@endif
                                    @if ($c->assegnato_il)<span class="src">assegnato il {{ $c->assegnato_il->format('d/m') }}</span>@endif
                                </div>
                            </div>
                        @endforeach
                        <button type="button" class="link-azione" data-bozza data-persona="{{ $codice }}" data-scopo="{{ $suoi->contains(fn ($c) => $c->scaduto()) ? 'sollecito' : 'richiesta' }}"
                                data-compiti="{{ $suoi->pluck('id')->join(',') }}" data-punti="{{ $suoi->pluck('testo')->join(' · ') }}">Prepara mail a {{ $nomeDi($codice ?: null) }}</button>
                    </div>
                @empty
                    <p class="empty">Nessun compito aperto assegnato ad altri.</p>
                @endforelse
            </div>

            <div class="card">
                <h2>Domande da fare <span class="conta">{{ $numDomande }}</span></h2>
                <p class="note">Le domande aperte delle schede macchina, raggruppate per destinatario. Le spunti dal Quadro quando hai la risposta.</p>
                @forelse ($domande as $g)
                    <details class="attesa-persona">
                        <summary><span class="who">{{ $g['chi'] }}</span> <span class="chip">{{ $g['domande']->count() }}</span>
                            @if ($g['persona'] && $g['persona']->nome !== $g['chi'])<span class="src">→ mail a {{ $g['persona']->nome }}</span>@endif</summary>
                        <ul class="plain">
                            @foreach ($g['domande'] as $q)
                                <li><span class="tag">#{{ $q->machine->dato('num', $q->machine->codice) }}</span> {{ $q->cosa }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="link-azione" data-bozza data-persona="{{ $g['persona']?->codice }}" data-scopo="richiesta"
                                data-domande="{{ $g['domande']->pluck('id')->join(',') }}" data-punti="{{ $g['domande']->count() }} domande per {{ $g['chi'] }}">Prepara mail</button>
                    </details>
                @empty
                    <p class="empty">Nessuna domanda aperta.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
(() => {
    const token = document.querySelector('meta[name=csrf-token]').content;
    const posta = (url, corpo) => fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(corpo) });
    const errore = async r => { try { const j = await r.json(); return j.errore || Object.values(j.errors || {}).flat()[0] || j.message || 'Non riuscito, riprova.'; } catch (e) { return 'Non riuscito, riprova (sessione scaduta? ricarica la pagina).'; } };
    const memoria = { leggi: k => { try { return localStorage.getItem(k); } catch (e) { return null; } }, scrivi: (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} } };

    // --- calendario: data e ora scelte entrano nel link (ora di Roma, durata fissa, calendario Salpa-Rolli);
    //     "in calendario" e' ricordato solo in questo browser ---
    const minuti = {{ \App\Services\LavoroFrancesco::MINUTI_EVENTO }}, calendario = @json((string) config('pannello.calendario'));
    const gcal = (titolo, giorno, ora, dettaglio) => {
        const [a, me, g] = giorno.split('-').map(Number), [h, m] = (ora || '{{ \App\Services\LavoroFrancesco::ORA_EVENTO }}').split(':').map(Number);
        // UTC solo per sommare i minuti: l'ora resta quella scritta, il fuso lo dice ctz
        const f = x => x.toISOString().slice(0, 19).replace(/[-:]/g, '');
        const inizio = new Date(Date.UTC(a, me - 1, g, h, m)), fine = new Date(inizio.getTime() + minuti * 60000);
        return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' + encodeURIComponent(titolo.slice(0, 200))
            + '&dates=' + f(inizio) + '/' + f(fine) + '&ctz=Europe%2FRome&details=' + encodeURIComponent(dettaglio)
            + (calendario ? '&src=' + encodeURIComponent(calendario) : '');
    };
    document.querySelectorAll('[data-cal]').forEach(v => {
        const data = v.querySelector('input[type=date]'), ora = v.querySelector('input[type=time]'), link = v.querySelector('[data-gcal]'), segno = v.querySelector('[data-in-calendario]');
        const mostra = () => { const g = memoria.leggi('cal-' + v.dataset.cal); segno.hidden = !g; if (g) segno.textContent = 'in calendario ' + g.split('-').reverse().slice(0, 2).join('/'); };
        const aggiorna = () => { if (data.value) link.href = gcal(v.dataset.titolo, data.value, ora.value, v.dataset.dettaglio); };
        data.addEventListener('change', aggiorna);
        ora.addEventListener('change', aggiorna);
        link.addEventListener('click', () => { memoria.scrivi('cal-' + v.dataset.cal, data.value); mostra(); });
        mostra();
    });

    // --- decisioni: stessa spunta del Quadro ---
    document.addEventListener('change', async e => {
        const box = e.target.closest('input[data-decisione]');
        if (!box) return;
        box.disabled = true;
        const r = await posta(box.dataset.decisione, { fatta: box.checked }).catch(() => null);
        if (r && r.ok) location.reload();
        else { box.checked = !box.checked; box.disabled = false; box.closest('label').querySelector('.err').textContent = 'Non salvato, riprova.'; }
    });

    // --- compiti miei: chiudi o modifica (come in Persone e compiti) ---
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

    // --- bozza mail ---
    const form = document.getElementById('bozza-form'), ris = document.getElementById('bozza-risultato');
    const a = document.getElementById('bozza-a'), oggetto = document.getElementById('bozza-oggetto'), corpo = document.getElementById('bozza-corpo');
    const outlook = document.getElementById('bozza-outlook'), err = document.getElementById('bozza-errore'), scrivi = document.getElementById('bozza-scrivi');
    let voci = { compiti: [], domande: [] };
    const lista = s => (s || '').split(',').filter(Boolean).map(Number);
    const aggiornaLink = () => { outlook.href = 'mailto:' + encodeURIComponent(a.value.trim()).replace(/%40/g, '@') + '?subject=' + encodeURIComponent(oggetto.value) + '&body=' + encodeURIComponent(corpo.value); };
    [a, oggetto, corpo].forEach(x => x.addEventListener('input', aggiornaLink));
    form.persona.addEventListener('change', () => { a.value = form.persona.selectedOptions[0]?.dataset.mail || ''; aggiornaLink(); });

    document.addEventListener('click', e => {
        const b = e.target.closest('[data-bozza]');
        if (b) {
            voci = { compiti: lista(b.dataset.compiti), domande: lista(b.dataset.domande) };
            form.persona.value = b.dataset.persona || '';
            form.scopo.value = b.dataset.scopo || 'richiesta';
            form.nota.value = ''; err.textContent = ''; ris.hidden = true;
            document.getElementById('bozza-punti').textContent = 'Su: ' + (b.dataset.punti || '');
            b.closest('.card, details').after(form);
            form.hidden = false;
            form.scrollIntoView({ block: 'nearest' });
        }
        if (e.target.closest('[data-chiudi-bozza]')) form.hidden = true;
    });
    scrivi.addEventListener('click', async () => {
        scrivi.disabled = true; err.textContent = 'Claude sta scrivendo…';
        const r = await posta(form.dataset.url, { persona: form.persona.value || null, scopo: form.scopo.value, nota: form.nota.value || null, ...voci }).catch(() => null);
        scrivi.disabled = false;
        if (!r || !r.ok) { err.textContent = r ? await errore(r) : 'Rete non raggiungibile, riprova.'; return; }
        const j = await r.json();
        err.textContent = '';
        a.value = j.a || form.persona.selectedOptions[0]?.dataset.mail || '';
        oggetto.value = j.oggetto; corpo.value = j.corpo;
        ris.hidden = false; aggiornaLink();
        scrivi.textContent = 'Riscrivi';
    });
    document.getElementById('bozza-copia').addEventListener('click', async e => {
        try { await navigator.clipboard.writeText((oggetto.value ? 'Oggetto: ' + oggetto.value + '\n\n' : '') + corpo.value); e.target.textContent = 'Copiato'; }
        catch (x) { corpo.select(); e.target.textContent = 'Copia con Ctrl+C'; }
    });
})();
</script>
@endpush
