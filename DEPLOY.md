# Deploy — rollisalpa.omniasoft.app

Stesso schema di omnia-hosting (`C:\apps\hosting\DEPLOY.md`, da leggere per i dettagli e le trappole gia' incontrate). Nessun ambiente locale: push su `main` → GitHub Actions (test, `vendor`, zip) → FTPS su cPanel.

| | |
|---|---|
| Server | 46.28.4.124 (`d01t5s-app.sphostserver.com`), account cPanel `omniapp` |
| Sottodominio | `rollisalpa.omniasoft.app` |
| Cartella applicazione | `/home/omniapp/public_html/rollisalpa.omniasoft.app` |
| Document root | `/home/omniapp/public_html/rollisalpa.omniasoft.app/public` |
| PHP | 8.3 (MultiPHP Manager + `public/.htaccess` + GitHub Action) |
| Database | `omniapp_rollisalpa`, utente `omniapp_rollisalpa` |
| Repository | `OmniasoftDev/RolliSalpa`, branch `main` |

## Preparazione — una volta sola (Francesco)

Stato al 02/10/2026: passi 1–9 e 11 **fatti** (deploy FTP, `vendor` caricato da File Manager, `.env`, tabelle importate); 10 (utente) da fare; 12 AutoSSL avviato, certificato da verificare.

1. **DNS** — FATTO (02/10/2026): record A `rollisalpa` e `www.rollisalpa` → `46.28.4.124` sui nameserver Aruba Business (zona SOA 2026100202), verificati su ns.abdns.info.
2. **Sottodominio** — FATTO (02/10/2026): `rollisalpa.omniasoft.app`, document root `/home/omniapp/public_html/rollisalpa.omniasoft.app/public`. Il modulo "Crea un nuovo dominio" di cPanel rispondeva *"You must specify a subdomain"*: creato con l'API UAPI `SubDomain/addsubdomain` (domain=rollisalpa, rootdomain=omniasoft.app, dir=public_html/rollisalpa.omniasoft.app/public) dalla sessione cPanel.
3. **PHP** — FATTO (02/10/2026): `ea-php83` sul sottodominio (UAPI `LangPHP/php_set_vhost_versions`). Il blocco `sp-ea-php83` in `public/.htaccess` serve comunque (vedi omnia-hosting § 2): non toglierlo.
4. **Database** — database `omniapp_rollisalpa` FATTO (02/10/2026). Da fare (Francesco): utente `omniapp_rollisalpa` con password dal generatore, associato al database con tutti i privilegi.
5. **Utente FTP** — cPanel → FTP Accounts, directory `/home/omniapp/public_html/rollisalpa.omniasoft.app` (il workflow usa `server-dir: ./`, relativo alla home dell'utente FTP).
6. **GitHub** — `OmniasoftDev/RolliSalpa` → Settings → Secrets and variables → Actions:
   - secrets `FTP_SERVER` = `46.28.4.124`, `FTP_USERNAME`, `FTP_PASSWORD`;
   - variabile `FTP_ATTIVO` = `si` (finche' manca, la Action fa solo test e zip).
7. **Primo caricamento** — dalla build su GitHub scaricare l'artifact `rollisalpa` (zip dentro zip: scompattarlo una volta sul PC), caricare `rollisalpa.zip` da File Manager nella cartella applicazione ed estrarlo li' (devono comparire `artisan`, `public/`, `vendor/` e il file nascosto `.htaccess`).
8. **`.env`** — da File Manager, partendo da `.env.example`:
   - `APP_KEY`: `php -r "echo 'base64:'.base64_encode(random_bytes(32));"` (il prefisso `base64:` fa parte della chiave);
   - `DB_PASSWORD`;
   - `PANNELLO_SYNC_TOKEN`: lo stesso valore di `token` in `C:\apps\rolli-salpa\automazione\pannello.config.json`.
9. **Tabelle** — phpMyAdmin → `omniapp_rollisalpa` → Importa `database/schema-iniziale.sql`.
10. **Utente** — hash della password: `php -r "echo password_hash('LATUAPASSWORD', PASSWORD_BCRYPT);"`, poi in phpMyAdmin:
    ```sql
    INSERT INTO users (name, email, password, created_at, updated_at)
    VALUES ('Francesco Guerrieri', 'info@omniasoft.it', '<hash>', NOW(), NOW());
    ```
11. **Cartelle e permessi** — il deploy esclude il contenuto di `storage/framework`, quindi `storage/framework/views`, `sessions` e `cache/data` vanno create a mano la prima volta: senza, ogni pagina risponde 500 (successo il 02/10/2026; create da File Manager / API `Fileman::mkdir`). `storage` e `bootstrap/cache` devono essere scrivibili dall'utente `omniapp` (con PHP in FastCGI basta `755`).
12. **HTTPS** — AutoSSL avviato il 02/10/2026 dopo la propagazione DNS (UAPI `SSL/start_autossl_check`): verificare in SSL/TLS Status che il certificato copra `rollisalpa` e `www.rollisalpa`. Il token di sincronizzazione viaggia nell'header: mai in HTTP.
13. **Prova** — `https://rollisalpa.omniasoft.app/login`; poi sul PC, da PowerShell: `& C:\apps\rolli-salpa\automazione\pubblica-pannello.ps1 -Forza` deve rispondere `pannello: inviati: salpa ... ; rolli ...`. Controllare anche che `https://omniasoft.app/rollisalpa.omniasoft.app/.env` risponda 403.

## Deploy quotidiano

Push su `main`. La Action:
1. esegue i test (`tests/Feature`); se falliscono, si ferma;
2. compila `vendor` e pubblica l'artifact `rollisalpa.zip`;
3. se `FTP_ATTIVO=si`, carica via FTPS tutto **tranne `vendor`**, `.env`, log e cache;
4. avvisa se `composer.lock` e' cambiato: allora `vendor/` va ricaricato a mano dall'artifact.

Nuove tabelle o colonne: migration **piu'** SQL equivalente in `database/sql/`, da importare in phpMyAdmin **prima** del push.
