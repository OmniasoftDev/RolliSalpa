// Cassaforte delle credenziali (resources/views/credenziali/blocco.blade.php): Mostra/Nascondi, Copia,
// aggiunta, modifica ed eliminazione. I valori in chiaro arrivano solo da POST .../mostra; se la password
// non e' stata confermata negli ultimi 15 minuti la risposta e' 423 e si apre il dialogo di conferma.
(() => {
    const blocco = document.getElementById('credenziali');
    if (!blocco) return;
    const token = document.querySelector('meta[name=csrf-token]').content;
    const dialogo = document.getElementById('cred-dialogo');
    const confermaForm = document.getElementById('cred-conferma');
    const confermaErrore = document.getElementById('cred-conferma-errore');

    const chiama = (url, metodo, corpo) => fetch(url, {
        method: metodo,
        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
        body: corpo,
        cache: 'no-store',
    });
    const messaggio = async r => {
        const j = await r.json().catch(() => ({}));
        return j.errors ? Object.values(j.errors)[0][0] : (j.errore || j.message || 'errore ' + r.status);
    };

    // Dialogo della password: risolve true se confermata, false se annullato
    const chiediPassword = () => new Promise(risolvi => {
        if (!dialogo || typeof dialogo.showModal !== 'function') { risolvi(false); return; }
        confermaErrore.textContent = '';
        confermaForm.reset();
        const fine = esito => { confermaForm.onsubmit = null; dialogo.onclose = null; if (dialogo.open) dialogo.close(); risolvi(esito); };
        confermaForm.onsubmit = async e => {
            e.preventDefault();
            const fd = new FormData(confermaForm);
            const r = await chiama(blocco.dataset.conferma, 'POST', fd);
            if (r.ok) fine(true); else confermaErrore.textContent = await messaggio(r);
        };
        dialogo.onclose = () => fine(false);
        dialogo.querySelector('[data-cred-chiudi]').onclick = () => fine(false);
        dialogo.showModal();
        document.getElementById('cred-password').focus();
    });

    // Valori in chiaro di una credenziale (null se annullato o in errore)
    const svela = async (box, stato) => {
        let r = await chiama(box.dataset.mostra, 'POST');
        if (r.status === 423) {
            if (!await chiediPassword()) return null;
            r = await chiama(box.dataset.mostra, 'POST');
        }
        if (!r.ok) { stato.textContent = await messaggio(r); return null; }
        return r.json();
    };
    const nascondi = box => {
        box.querySelectorAll('[data-campo]').forEach(d => d.textContent = d.dataset.mascherato);
        box.querySelector('[data-cred-mostra]').textContent = 'Mostra';
        delete box.dataset.visibile;
    };

    blocco.addEventListener('click', async e => {
        const box = e.target.closest('[data-cred]');
        if (!box) return;
        const stato = box.querySelector('[data-cred-stato]');
        stato.textContent = '';

        if (e.target.closest('[data-cred-mostra]')) {
            if (box.dataset.visibile) { nascondi(box); return; }
            const v = await svela(box, stato);
            if (!v) return;
            box.querySelectorAll('[data-campo]').forEach(d => d.textContent = v[d.dataset.campo] ?? '—');
            box.querySelector('[data-cred-mostra]').textContent = 'Nascondi';
            box.dataset.visibile = '1';
            // si richiudono da sole dopo 2 minuti
            setTimeout(() => { if (box.dataset.visibile) nascondi(box); }, 120000);
        } else if (e.target.closest('[data-cred-copia]')) {
            const v = await svela(box, stato);
            if (!v) return;
            try { await navigator.clipboard.writeText(v.segreto ?? ''); stato.textContent = 'Password copiata.'; }
            catch (err) { stato.textContent = 'Copia non riuscita: usa Mostra.'; }
        } else if (e.target.closest('[data-cred-modifica]')) {
            const v = await svela(box, stato);
            if (!v) return;
            const f = box.querySelector('[data-modifica]');
            f.elements.utente.value = v.utente ?? '';
            f.elements.segreto.value = v.segreto ?? '';
            f.elements.note.value = v.note ?? '';
            f.hidden = false;
            f.elements.etichetta.focus();
        } else if (e.target.closest('[data-cred-annulla]')) {
            const f = box.querySelector('[data-modifica]');
            f.reset();
            f.hidden = true;
        } else if (e.target.closest('[data-cred-elimina]')) {
            const b = e.target.closest('[data-cred-elimina]');
            if (!confirm('Eliminare la credenziale "' + b.dataset.etichetta + '"? Non si può recuperare.')) return;
            const r = await chiama(b.dataset.credElimina, 'DELETE');
            if (r.ok) location.reload(); else stato.textContent = await messaggio(r);
        }
    });

    // Salvataggio di una credenziale nuova o modificata
    blocco.addEventListener('submit', async e => {
        const f = e.target.closest('form[data-nuova], form[data-modifica]');
        if (!f) return;
        e.preventDefault();
        const errore = f.querySelector('[data-cred-errore]');
        errore.textContent = '';
        const fd = new FormData(f);
        let url = f.dataset.url;
        if (f.hasAttribute('data-modifica')) { url = f.closest('[data-cred]').dataset.url; fd.append('_method', 'PUT'); }
        const b = f.querySelector('button[type=submit]');
        b.disabled = true;
        const r = await chiama(url, 'POST', fd);
        if (r.ok) { f.reset(); location.reload(); return; }
        errore.textContent = 'Non salvata: ' + await messaggio(r);
        b.disabled = false;
    });
})();
