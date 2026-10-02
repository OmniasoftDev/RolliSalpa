@extends('layouts.app')

@section('titolo', 'Chat ' . $progetto->nome . ' · Rolli Salpa')
@section('classe', 'p-'.$progetto->slug)

@section('contenuto')
<div class="wrap chat-pagina">
    <header class="head">
        <div>
            <h1>Chat · {{ $progetto->nome }}</h1>
            <p>Ragiona con Claude sullo stato del progetto e prendi decisioni. Claude conosce macchine, fatti con fonte, domande aperte, eventi e appunti; non vede mail e file del PC.</p>
        </div>
    </header>

    @unless ($configurata)
        <p class="errore">Chat non ancora attiva: manca la chiave <span class="mono">ANTHROPIC_API_KEY</span> nel .env del server.</p>
    @endunless

    <div class="chat" id="chat" aria-live="polite">
        @forelse ($messaggi as $m)
            @include('chat.messaggio', ['m' => $m])
        @empty
            <p class="empty" id="chat-vuota">Nessun messaggio. Prova con: «Cosa manca per poter fare il preventivo della 06?» oppure «Prepara le domande per il sopralluogo di martedì».</p>
        @endforelse
    </div>

    <form class="chat-invio" id="chat-form" data-url="{{ route('chat.invia', $progetto->slug) }}">
        <label for="chat-testo" class="campo">Messaggio
            <textarea id="chat-testo" name="testo" rows="3" placeholder="Scrivi a Claude… (Ctrl+Invio per inviare)" @disabled(! $configurata)></textarea>
        </label>
        <div class="azioni">
            <button type="submit" class="btn" id="chat-invia" @disabled(! $configurata)>Invia</button>
            <span class="src" id="chat-stato" aria-live="polite"></span>
        </div>
    </form>

    @include('pannello.appunto-form')
</div>
@endsection

@push('script')
<script src="{{ asset('js/appunti.js') }}?v={{ filemtime(public_path('js/appunti.js')) }}"></script>
<script>
(() => {
    const token = document.querySelector('meta[name=csrf-token]').content;
    const chat = document.getElementById('chat');
    const form = document.getElementById('chat-form');
    const testo = document.getElementById('chat-testo');
    const stato = document.getElementById('chat-stato');
    const invia = document.getElementById('chat-invia');
    const fondo = () => window.scrollTo({ top: document.body.scrollHeight });
    fondo();

    // Stesso markup di chat/messaggio.blade.php, costruito in JS per i messaggi nuovi
    const bolla = m => {
        const d = document.createElement('div');
        d.className = 'msg ' + (m.ruolo === 'user' ? 'msg-io' : 'msg-claude');
        const meta = document.createElement('div'); meta.className = 'meta';
        meta.textContent = (m.ruolo === 'user' ? 'Tu' : 'Claude') + ' · ' + m.quando;
        const t = document.createElement('div'); t.className = 'testo-appunto'; t.textContent = m.testo;
        d.append(meta, t);
        if (m.ruolo === 'assistant') {
            const riga = (m.testo.match(/Decisione da registrare:\s*([\s\S]+)$/i) || [])[1];
            const b = document.createElement('button');
            b.type = 'button'; b.className = 'btn btn-sec'; b.dataset.apriAppunto = ''; b.dataset.tipo = 'decisione';
            b.dataset.testo = (riga || m.testo).trim(); b.textContent = 'Registra come decisione';
            d.append(b);
        }
        return d;
    };

    form.addEventListener('submit', async e => {
        e.preventDefault();
        const msg = testo.value.trim();
        if (!msg) return;
        document.getElementById('chat-vuota')?.remove();
        invia.disabled = true; testo.disabled = true;
        stato.textContent = 'Claude sta ragionando… può volerci un minuto.';
        const provvisoria = bolla({ ruolo: 'user', testo: msg, quando: 'ora' });
        chat.append(provvisoria); fondo();
        try {
            const r = await fetch(form.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ testo: msg }) });
            const j = await r.json().catch(() => ({}));
            if (j.domanda) provvisoria.replaceWith(bolla(j.domanda));
            if (!r.ok) throw new Error(j.errore || (j.errors ? Object.values(j.errors)[0][0] : 'errore ' + r.status));
            chat.append(bolla(j.risposta));
            testo.value = '';
            stato.textContent = '';
        } catch (err) {
            stato.textContent = err.message + ' Il messaggio resta salvato: puoi riprovare con un nuovo invio.';
        } finally {
            invia.disabled = false; testo.disabled = false; testo.focus(); fondo();
        }
    });
    testo.addEventListener('keydown', e => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) form.requestSubmit(); });
})();
</script>
@endpush
