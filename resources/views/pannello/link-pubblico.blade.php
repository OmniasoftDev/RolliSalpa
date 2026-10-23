{{-- "Link pubblico" (23/10/2026): l'URL della pagina pubblica di avanzamento, visibile solo qui nel quadro (con login).
     La chiave si calcola sul server da APP_KEY e info.pubblico.versione: non e' salvata da nessuna parte. --}}
<details class="link-pubblico">
    <summary class="btn btn-sec">Link pubblico</summary>
    <div class="link-pubblico-corpo">
        @if (\App\Services\Pubblico::attivo($progetto))
            @php $urlPubblico = \App\Services\Pubblico::url($progetto); @endphp
            <p>Stato avanzamento in sola lettura, senza login, per chi riceve il link (cliente, fornitori, certificatore).</p>
            <div class="link-pubblico-riga">
                <input type="text" readonly value="{{ $urlPubblico }}" aria-label="Indirizzo della pagina pubblica" id="link-pubblico-url">
                <button type="button" class="btn" id="link-pubblico-copia">Copia</button>
                <a href="{{ $urlPubblico }}" target="_blank" rel="noopener">Apri</a>
            </div>
            <span class="src">Per cambiare il link (e spegnere il vecchio) alzare <code>info.pubblico.versione</code> nel file del PC.</span>
        @else
            <p>Non attiva: nel file del PC manca <code>info.pubblico.attivo: true</code>.</p>
        @endif
    </div>
</details>
@push('script')
<script>
(() => {
    const campo = document.getElementById('link-pubblico-url');
    const copia = document.getElementById('link-pubblico-copia');
    if (!campo || !copia) return;
    campo.addEventListener('focus', () => campo.select());
    copia.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(campo.value);
        } catch (e) {
            campo.select();
            document.execCommand('copy');
        }
        copia.textContent = 'Copiato';
        setTimeout(() => { copia.textContent = 'Copia'; }, 1600);
    });
})();
</script>
@endpush
