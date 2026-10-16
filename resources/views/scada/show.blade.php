@extends('layouts.app')

@section('titolo', 'SCADA · '.$progetto->nome)
@section('classe', 'p-'.$progetto->slug)

@section('contenuto')
<div class="wrap sc-pagina">
    <header class="head">
        <div>
            <h1>Sullo SCADA · {{ $progetto->nome }}</h1>
            <p>Cosa si vede di ogni macchina dentro lo SCADA Edica: connessione, valori letti, storico, pagine e schermate.</p>
        </div>
        <div class="gauges">
            @foreach (\App\Services\Scada::STATI as $k => $st)
                @if ($conteggi[$k])<div class="gauge"><b>{{ $conteggi[$k] }}</b><span>{{ $st[0] }}</span></div>@endif
            @endforeach
        </div>
    </header>

    @php $nDom = count($raccolta['domande']) + $raccolta['compiti']->count(); @endphp
    @if ($nDom)
        <section class="card sc-domande" aria-labelledby="t-domande">
            <h2 id="t-domande">Domande dallo SCADA <span class="conta">{{ $nDom }}</span></h2>
            <p class="sub">Le domande nate dentro lo SCADA, macchina per macchina, e i compiti aperti che vengono dalla visita.</p>
            <ul class="sc-dom-lista">
                @foreach ($raccolta['domande'] as $d)
                    <li><span class="num">{{ $d['num'] }}</span><a href="{{ route('progetti.show', [$progetto->slug, $d['codice']]) }}#scada">{{ $d['nome'] }}</a> — {{ $d['testo'] }}</li>
                @endforeach
                @foreach ($raccolta['compiti'] as $c)
                    <li><span class="chip">{{ $c->codice ?: 'compito' }}</span> {{ $c->testo }} <span class="src">{{ \App\Models\Compito::STATI[$c->stato] ?? $c->stato }}@if ($c->scadenza) · entro {{ $c->scadenza->format('d/m') }}@endif</span></li>
                @endforeach
            </ul>
            @if ($destinatario)
                <div class="azioni">
                    <button type="button" class="btn" id="sc-bozza" data-url="{{ route('scada.bozza', $progetto->slug) }}">Prepara bozza mail per {{ $destinatario->nome }}</button>
                    <span class="err" id="sc-bozza-errore" aria-live="polite"></span>
                </div>
                <div class="bozza-risultato" id="sc-bozza-ris" hidden>
                    <label class="campo" for="sc-bozza-a">A <input type="text" id="sc-bozza-a" class="sel" placeholder="indirizzo mail"></label>
                    <label class="campo" for="sc-bozza-oggetto">Oggetto <input type="text" id="sc-bozza-oggetto" class="sel"></label>
                    <label class="campo" for="sc-bozza-corpo">Testo <textarea id="sc-bozza-corpo" rows="12"></textarea></label>
                    <div class="azioni"><a class="btn" id="sc-bozza-outlook" href="#">Apri in Outlook</a> <button type="button" class="btn btn-sec" id="sc-bozza-copia">Copia testo</button></div>
                    <p class="src">Il sito non invia nulla: rileggi e manda tu.</p>
                </div>
            @else
                <p class="src">Per preparare la mail serve il destinatario nel file del progetto (<span class="mono">info.destinatarioScada</span>).</p>
            @endif
        </section>
    @endif

    @foreach ($sistemi as $si)
        <section class="card">
            <h2><a href="{{ route('sistemi.show', [$progetto->slug, $si['codice']]) }}">{{ $si['nome'] }}</a></h2>
            @if ($si['sintesi'])<p>{{ $si['sintesi'] }}</p>@endif
            @if ($si['galleria'])@include('scada.galleria', ['galleria' => $si['galleria']])@else<p class="empty">Nessuna schermata del sistema.</p>@endif
        </section>
    @endforeach

    <div class="sc-elenco">
        @foreach ($righe as $r)
            @php $m = $r['macchina']; $s = $r['s']; @endphp
            <article class="card sc-card {{ ($s['vecchio'] ?? false) ? 'sc-vecchio' : '' }}">
                <h2><span class="num">{{ $m->dato('num', $m->codice) }}</span><a href="{{ route('progetti.show', [$progetto->slug, $m->codice]) }}#scada">{{ $m->dato('nome') }}</a> <a class="link-azione sc-mappa" href="{{ route('progetti.show', [$progetto->slug, $m->codice]) }}?vista=oggi#mappa">Mappa</a></h2>
                @if ($s)
                    <p><span class="sc-badge {{ $s['classe'] }}">{{ $s['etichetta'] }}</span> @if ($s['connessione'])<span class="mono">{{ $s['connessione'] }}</span>@endif</p>
                    <p class="sub">{{ count($s['variabili']) }} variabili · storico {{ in_array(trim($s['storico']), ['', '—', '-'], true) ? 'no' : 'sì' }} · @include('scada.eta', ['data' => $s['visto']])</p>
                    @if ($s['sintesi'])<p>{{ $s['sintesi'] }}</p>@endif
                    @include('scada.galleria', ['galleria' => $s['schermate'], 'max' => 3])
                @else
                    <p class="empty">Nessun dato SCADA per questa macchina.</p>
                @endif
            </article>
        @endforeach
    </div>
</div>
@endsection

@push('script')
<script>
(() => {
    const b = document.getElementById('sc-bozza');
    if (!b) return;
    const token = document.querySelector('meta[name=csrf-token]').content;
    const err = document.getElementById('sc-bozza-errore'), ris = document.getElementById('sc-bozza-ris');
    const a = document.getElementById('sc-bozza-a'), oggetto = document.getElementById('sc-bozza-oggetto'), corpo = document.getElementById('sc-bozza-corpo');
    const outlook = document.getElementById('sc-bozza-outlook');
    const link = () => { outlook.href = 'mailto:' + encodeURIComponent(a.value.trim()).replace(/%40/g, '@') + '?subject=' + encodeURIComponent(oggetto.value) + '&body=' + encodeURIComponent(corpo.value); };
    [a, oggetto, corpo].forEach(x => x.addEventListener('input', link));
    b.addEventListener('click', async () => {
        b.disabled = true; err.textContent = 'Claude sta scrivendo…';
        const r = await fetch(b.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: '{}' }).catch(() => null);
        b.disabled = false;
        if (!r || !r.ok) { let m = 'Rete non raggiungibile, riprova.'; if (r) { try { m = (await r.json()).errore || 'Non riuscito, riprova.'; } catch (e) { m = 'Non riuscito, riprova (sessione scaduta?).'; } } err.textContent = m; return; }
        const j = await r.json();
        err.textContent = ''; a.value = j.a || ''; oggetto.value = j.oggetto; corpo.value = j.corpo;
        ris.hidden = false; link(); b.textContent = 'Riscrivi';
    });
    document.getElementById('sc-bozza-copia').addEventListener('click', async e => {
        try { await navigator.clipboard.writeText((oggetto.value ? 'Oggetto: ' + oggetto.value + '\n\n' : '') + corpo.value); e.target.textContent = 'Copiato'; }
        catch (x) { corpo.select(); e.target.textContent = 'Copia con Ctrl+C'; }
    });
})();
</script>
@endpush
