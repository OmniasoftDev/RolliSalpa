{{-- Nuovo appunto: sopralluogo, riunione, decisione o nota, con foto e PDF. Pensato per il telefono. --}}
<form class="appunto-form" id="appunto-form" data-url="{{ route('appunti.store', $progetto->slug) }}" hidden>
    <div class="top"><h3>Nuovo appunto · {{ $progetto->nome }}</h3><button type="button" class="esci" data-chiudi-appunto>Chiudi</button></div>

    <fieldset class="tipi">
        <legend>Tipo</legend>
        @foreach (\App\Models\Appunto::TIPI as $k => $l)
            <label for="tipo-{{ $k }}"><input type="radio" id="tipo-{{ $k }}" name="tipo" value="{{ $k }}" @checked($loop->first)> {{ $l }}</label>
        @endforeach
    </fieldset>

    <fieldset class="scelta-macchine">
        <legend>Macchine (nessuna = progetto in generale)</legend>
        @foreach ($macchine->reject(fn ($m) => $m->dato('escludiDaiConteggi')) as $m)
            <label for="am-{{ $m->codice }}"><input type="checkbox" id="am-{{ $m->codice }}" name="macchine[]" value="{{ $m->codice }}"> <span class="num">{{ $m->dato('num', $m->codice) }}</span>{{ $m->dato('nome') }}</label>
        @endforeach
    </fieldset>

    <label for="appunto-testo" class="campo">Appunto
        <textarea id="appunto-testo" name="testo" rows="7" placeholder="Cosa hai visto o deciso: chi era presente, cosa ha detto, dati letti in macchina (PLC, targa, IP, protocollo), cosa manca, prossimo passo."></textarea>
    </label>

    <label for="appunto-file" class="campo">Foto e PDF (targhe, PLC, quadri, lavagne, documenti)
        <input type="file" id="appunto-file" name="allegati[]" accept="image/*,application/pdf" multiple>
    </label>
    <ul class="file-scelti" id="file-scelti"></ul>

    <p class="errore" id="appunto-errore" aria-live="polite"></p>
    <div class="azioni">
        <button type="submit" class="btn" id="appunto-invia">Salva appunto</button>
        <span class="src" id="appunto-stato" aria-live="polite"></span>
    </div>
</form>
