// Mappe a blocchi e frecce (Progetti 4.0, schermo intero, Mappa impianto): tocco su un blocco o
// su una freccia = pannello di dettaglio; un blocco scelto accende i suoi collegamenti e spegne gli altri.
(() => {
    const funzioni = [];
    document.querySelectorAll('[data-mappa]').forEach(radice => {
        const pannelli = radice.querySelectorAll('.pj-dett');
        const elementi = radice.querySelectorAll('.pj-mappa [data-dett]');
        const dettagli = radice.querySelector('.pj-dettagli');
        const mostra = (id, scorri) => {
            if (!radice.querySelector('[id="' + CSS.escape(id) + '"]')) return;
            pannelli.forEach(p => p.hidden = p.id !== id);
            elementi.forEach(e => e.classList.toggle('sel', e.dataset.dett === id));
            const nodo = id.startsWith('n-') ? id.slice(2) : null;
            radice.querySelectorAll('.pj-mappa .pj-k').forEach(k => {
                k.classList.toggle('spento', nodo !== null && k.dataset.da !== nodo && k.dataset.a !== nodo);
            });
            if (scorri && dettagli) dettagli.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        };
        elementi.forEach(e => {
            e.addEventListener('click', () => mostra(e.dataset.dett, matchMedia('(max-width: 900px)').matches));
            e.addEventListener('keydown', ev => { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); mostra(e.dataset.dett, false); } });
        });
        funzioni.push({ radice, mostra });
    });
    // i pulsanti "data-apri" (dentro il dettaglio, nell'elenco dei collegamenti o nelle strade) aprono
    // il dettaglio nella mappa che li contiene, o nella prima della pagina
    document.addEventListener('click', ev => {
        const b = ev.target.closest('[data-apri]');
        if (!b || !funzioni.length) return;
        const f = funzioni.find(x => x.radice.contains(b)) || funzioni[0];
        f.mostra(b.dataset.apri, true);
    });
    // filtro per macchina della Mappa impianto: spegne blocchi e frecce delle macchine non scelte
    const filtro = document.querySelector('[data-filtro-macchina]');
    if (filtro) {
        const applica = () => {
            const m = filtro.value;
            document.querySelectorAll('.pj-mappa [data-macchine]').forEach(e => {
                e.classList.toggle('spento', m !== '' && !e.dataset.macchine.split(' ').includes(m));
            });
        };
        filtro.addEventListener('change', applica);
        applica();
    }
})();
