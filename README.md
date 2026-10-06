# Rolli Salpa 4.0 — pannello web

`https://rollisalpa.omniasoft.app` — pannello dei progetti Industria 4.0 **Salpa** e **Rolli** seguiti da Omniasoft (Francesco). Due progetti separati: stesso software, dati mai mescolati.

- Laravel 13, PHP 8.3, MySQL; Blade e CSS semplice, nessuna build npm.
- Nessun ambiente locale: push su `main` → GitHub Action (test, `vendor`, FTPS su cPanel). Vedi `DEPLOY.md`.
- Accesso con email e password (utenti creati a mano, vedi `DEPLOY.md`).

## Pagine (per ogni progetto, `/{slug}`)

| Pagina | Cosa fa |
|---|---|
| **Quadro** `/{slug}` | macchine e fasi, domande da spuntare, ultimi fatti, "Da guardare" (decisioni, novità dell'automazione, mail escluse), appunti con foto e PDF; `?m=<codice>` apre la scheda di una macchina |
| **Il mio lavoro** `/{slug}/lavoro` | compiti di Francesco, decisioni, risposte attese, domande per destinatario, bozze di mail scritte da Claude, calendario proposto e appuntamenti da accettare per Google Calendar |
| **Workflow** `/{slug}/workflow` | calendario a mese e timeline per macchina con fasi del planning, appuntamenti, eventi del calendario Google Salpa-Rolli, scadenze dei compiti e mail; ogni voce porta a scheda macchina, compiti, lavoro o registro |
| **Persone e compiti** `/{slug}/compiti` | rubrica del progetto e compiti assegnati, proposti dal PC o nati sul web |
| **Chat con Claude** `/{slug}/chat` | domande sul progetto; una risposta si registra come decisione |
| **Registro** `/registro` | controlli del PC ogni 5 minuti, quadratura delle mail, mail da leggere |

## Scambio con il PC di Francesco (`C:\apps\rolli-salpa\automazione`)

Il controllo ogni 5 minuti (lun–ven 8–20) usa le API protette dal token (`PANNELLO_SYNC_TOKEN`):

- `POST /api/sync` — fotografia completa di `pannello/<slug>.json` (macchine, domande, eventi, decisioni, persone, compiti proposti, appuntamenti, planning);
- `GET /api/spunte` — quello che nasce sul web (spunte, decisioni, compiti, appuntamenti accettati) torna al PC;
- `POST /api/stato` — striscia di stato, mail escluse dal filtro, eventi del calendario Google (letti dal PC dall'indirizzo iCal);
- `POST /api/controlli` — registro dei controlli e delle mail, con la quadratura;
- `GET /api/appunti`, `POST /api/appunti/elaborati`, `GET /api/allegati/{id}` — appunti presi dal sito.

Il sito non usa API Google e non invia mail: in Google Calendar va solo ciò che Francesco accetta, e lo crea il PC.

Installazione e deploy: `DEPLOY.md`. Regole e formato dei dati per Claude: `CLAUDE.md`.
