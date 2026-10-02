# CLAUDE.md — rollisalpa.omniasoft.app

Pannello web dei progetti Industria 4.0 **Salpa** e **Rolli** seguiti da Omniasoft (Francesco). Sostituisce i pannelli artifact su claude.ai. Il lavoro di consulenza (mail, rolling, schede macchina) sta in `C:\apps\rolli-salpa`: questa app ne mostra soltanto i dati.

## Regole
- **Niente ambiente locale**: non si installa né si avvia nulla sul PC (niente composer install, artisan, server o database locali). Si scrive il codice, push su `main`, la GitHub Action fa i test, compila `vendor` e carica via FTPS su cPanel; si prova sul server.
- **Salpa e Rolli sono due progetti separati**: stesso software, dati separati per `projects.slug` (`salpa`, `rolli`). Niente viste o contatori che mescolano i due.
- **La fonte dei dati e' sul PC**: `C:\apps\rolli-salpa\pannello\<slug>.json`, inviati da `automazione\pubblica-pannello.ps1` a `POST /api/sync` (fotografia completa: cio' che manca dal file viene tolto). Non si modificano macchine, note o eventi dal web.
- **Dati che nascono sul web**: le spunte delle domande (`questions.fatto_web`, vincono sul file finche' il PC non le riprende da `GET /api/spunte`) e gli **appunti** (`appunti`, `appunti_allegati`): sopralluoghi, riunioni, decisioni e note di Francesco con foto/PDF. Il PC li scarica da `GET /api/appunti` + `GET /api/allegati/{id}`, Claude li elabora come le mail e poi `POST /api/appunti/elaborati`. Un appunto elaborato non si cancella piu' dal sito.
- Lo SCADA Omniasoft per Rolli (da AbruzzoResineNew) **non** va in questa app.

## Struttura
- `app/Services/Sincronizzazione.php` — applica un file di progetto al DB (transazione unica).
- `app/Http/Controllers/Api/SyncController.php` — `/api/sync`, `/api/spunte`, protetti da `TokenSincronizzazione` (Bearer = `PANNELLO_SYNC_TOKEN`).
- `app/Http/Controllers/PannelloController.php` — `/` (primo progetto), `/{slug}`, `POST /domande/{id}` (spunta).
- Tabelle: `projects` (info e fasi in JSON), `machines` (dati in JSON), `questions`, `events`. SQL per phpMyAdmin in `database/schema-iniziale.sql`, allineato alle migration.
- Viste: `resources/views/pannello/show.blade.php`; CSS in `public/css/pannello.css` (niente build npm).
- Test in `tests/Feature/PannelloTest.php`, eseguiti dalla GitHub Action prima del deploy.

## Formato di pannello/<slug>.json
```json
{ "progetto": "rolli", "nome": "Rolli", "ordine": 2,
  "info": { "titolo": "...", "sottotitolo": "...", "fase": "...", "mail": "gg/mm/aaaa hh:mm", "rolling": "...",
            "aggiornato": "...", "scadenza": "2026-11-30", "richiesta": "...", "posizione": "...", "titoloTabella": "...",
            "contatori": [ {"tipo": "giorni|fase|rischio|domande", "fase": "opcua", "etichetta": "..."} ] },
  "fasi": [ ["rete", "Rete"], ["opcua", "Server OPC UA"] ],
  "macchine": [ { "codice": "r01", "ordine": 1, "num": "01", "gruppo": "...", "nome": "...", "fornitore": "...",
                  "fasi": { "rete": "ok|corso|attesa|rischio|no|na" }, "note": [ {"testo": "...", "fonte": "..."} ],
                  "chiedere": [ {"id": "q1", "chi": "...", "cosa": "...", "fatto": false, "fattoIl": "aaaa-mm-gg", "risposta": "..."} ],
                  "prossimo": "...", "escludiDaiConteggi": false } ],
  "eventi": [ { "id": "eAAAAMMGG-HHMM", "data": "aaaa-mm-gg", "ora": "hh:mm", "tipo": "mail|decisione|amministrazione|rolling|verbale|nota",
                "chi": "...", "macchine": ["r00"], "testo": "..." } ] }
```
Gli `id` delle domande devono restare stabili: sono la chiave che lega la spunta del web alla domanda.

## Deploy
Vedi `DEPLOY.md` (stesso schema di omnia-hosting: `vendor` escluso dall'FTP, `.htaccess` di sicurezza nella radice, handler PHP 8.3 in `public/.htaccess`).
