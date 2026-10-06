// Modulo "Nuovo appunto" (pannello e chat): apertura, bozza, foto rimpicciolite, invio, eliminazione.
// Il modulo e' resources/views/pannello/appunto-form.blade.php; data-progetto sul form identifica la bozza.
(() => {
    const form = document.getElementById('appunto-form');
    if (!form) return;
    const token = document.querySelector('meta[name=csrf-token]').content;
    const chiave = 'rs-' + form.dataset.progetto + '-bozza';
    const leggi = () => { try { return localStorage.getItem(chiave); } catch (e) { return null; } };
    const scrivi = v => { try { localStorage.setItem(chiave, v); } catch (e) {} };
    const testo = document.getElementById('appunto-testo');
    const fileInput = document.getElementById('appunto-file');
    const errore = document.getElementById('appunto-errore');
    const stato = document.getElementById('appunto-stato');
    const invia = document.getElementById('appunto-invia');
    const bozza = leggi();
    if (bozza) testo.value = bozza;
    testo.addEventListener('input', () => scrivi(testo.value));

    // Apre il modulo; data-apri-appunto = codice macchina da spuntare (vuoto = nessuna),
    // data-tipo e data-testo (opzionali) lo precompilano, es. "Registra come decisione" dalla chat.
    window.apriAppunto = ({ macchina = '', tipo = '', testoIniziale = '' } = {}) => {
        form.querySelectorAll('input[name="macchine[]"]').forEach(c => c.checked = c.value === macchina);
        if (tipo) { const r = form.querySelector('input[name=tipo][value="' + tipo + '"]'); if (r) r.checked = true; }
        if (testoIniziale) { testo.value = testoIniziale; scrivi(testoIniziale); }
        form.hidden = false;
        form.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        testo.focus({ preventScroll: true });
    };
    document.addEventListener('click', e => {
        const b = e.target.closest('[data-apri-appunto]');
        if (b && b.tagName === 'A') e.preventDefault();
        if (b) apriAppunto({ macchina: b.dataset.apriAppunto, tipo: b.dataset.tipo || '', testoIniziale: b.dataset.testo || '' });
    });
    // "+ Appunto" del menu dalle altre pagine arriva qui con ?appunto (o ?appunto=<codice macchina>)
    const daMenu = new URLSearchParams(location.search);
    if (daMenu.has('appunto')) {
        apriAppunto({ macchina: daMenu.get('appunto') });
        daMenu.delete('appunto');
        history.replaceState(null, '', location.pathname + (daMenu.size ? '?' + daMenu : '') + location.hash);
    }
    form.querySelector('[data-chiudi-appunto]').addEventListener('click', () => form.hidden = true);
    fileInput.addEventListener('change', () => {
        document.getElementById('file-scelti').replaceChildren(...[...fileInput.files].map(f => {
            const li = document.createElement('li'); li.textContent = f.name + ' (' + Math.round(f.size / 1024) + ' KB)'; return li;
        }));
    });

    // Le foto del telefono pesano 3-8 MB: si portano a 2000 px JPEG prima dell'invio. Se il browser non le sa leggere, parte l'originale.
    const rimpicciolisci = async f => {
        if (!f.type.startsWith('image/') || f.type === 'image/gif') return f;
        try {
            const img = await createImageBitmap(f, { imageOrientation: 'from-image' });
            const k = Math.min(1, 2000 / Math.max(img.width, img.height));
            const c = document.createElement('canvas');
            c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
            c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
            const blob = await new Promise(r => c.toBlob(r, 'image/jpeg', 0.85));
            return blob ? new File([blob], f.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }) : f;
        } catch (e) { return f; }
    };

    form.addEventListener('submit', async e => {
        e.preventDefault();
        errore.textContent = '';
        if (!testo.value.trim()) { errore.textContent = "Scrivi il testo dell'appunto."; testo.focus(); return; }
        invia.disabled = true;
        stato.textContent = fileInput.files.length ? 'Preparo le foto…' : 'Salvo…';
        const fd = new FormData();
        fd.append('tipo', form.querySelector('input[name=tipo]:checked').value);
        fd.append('testo', testo.value);
        form.querySelectorAll('input[name="macchine[]"]:checked').forEach(c => fd.append('macchine[]', c.value));
        for (const f of fileInput.files) fd.append('allegati[]', await rimpicciolisci(f));
        stato.textContent = 'Invio…';
        try {
            const r = await fetch(form.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }, body: fd });
            const j = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(j.errors ? Object.values(j.errors)[0][0] : (j.message || 'errore ' + r.status));
            scrivi('');
            stato.textContent = 'Salvato. Verrà elaborato al prossimo controllo orario.';
            setTimeout(() => location.reload(), 700);
        } catch (err) {
            errore.textContent = 'Non salvato: ' + err.message + '. Il testo resta qui come bozza.';
            stato.textContent = '';
            invia.disabled = false;
        }
    });

    // Elimina (solo appunti non ancora elaborati): primo clic arma, secondo conferma.
    document.addEventListener('click', async e => {
        const b = e.target.closest('[data-elimina]');
        if (!b) return;
        if (!b.dataset.armato) { b.dataset.armato = '1'; b.textContent = 'Conferma eliminazione'; return; }
        b.disabled = true;
        const r = await fetch(b.dataset.elimina, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } });
        if (r.ok) location.reload(); else { const j = await r.json().catch(() => ({})); b.textContent = j.errore || 'Non eliminato'; }
    });
})();
