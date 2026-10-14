{{-- Campi di una credenziale (nuova o da modificare). Utente, password e note non si precompilano mai: in modifica li riempie "Mostra". --}}
<div class="riga-campi">
    <label for="{{ $prefisso }}-etichetta" class="campo">Etichetta <input type="text" id="{{ $prefisso }}-etichetta" name="etichetta" class="sel" maxlength="150" required value="{{ $c?->etichetta }}" placeholder="es. Router ASEM WAN, Utente SCADA consultazione"></label>
    <label for="{{ $prefisso }}-indirizzo" class="campo">Indirizzo (IP o URL) <input type="text" id="{{ $prefisso }}-indirizzo" name="indirizzo" class="sel" maxlength="255" value="{{ $c?->indirizzo }}" autocomplete="off"></label>
</div>
<div class="riga-campi">
    <label for="{{ $prefisso }}-utente" class="campo">Utente <input type="text" id="{{ $prefisso }}-utente" name="utente" class="sel" maxlength="500" autocomplete="off"></label>
    <label for="{{ $prefisso }}-segreto" class="campo">Password <input type="password" id="{{ $prefisso }}-segreto" name="segreto" class="sel" maxlength="2000" autocomplete="new-password"></label>
</div>
<label for="{{ $prefisso }}-note" class="campo">Note <textarea id="{{ $prefisso }}-note" name="note" rows="2" maxlength="5000"></textarea></label>
<label for="{{ $prefisso }}-fonte" class="campo">Fonte <input type="text" id="{{ $prefisso }}-fonte" name="fonte" class="sel" maxlength="255" value="{{ $c?->fonte }}" placeholder="es. Mail 14/10 Merlotti"></label>
<p class="errore" data-cred-errore aria-live="polite"></p>
