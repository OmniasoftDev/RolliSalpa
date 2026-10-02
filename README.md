# Rolli Salpa 4.0 — pannello web

`https://rollisalpa.omniasoft.app` — quadro dei progetti Industria 4.0 Salpa e Rolli (Omniasoft).

- Laravel 13, PHP 8.3, MySQL; Blade e CSS semplice, nessuna build npm.
- I dati arrivano ogni ora dal PC di Francesco (`C:\apps\rolli-salpa\automazione\pubblica-pannello.ps1` → `POST /api/sync`).
- Dal web si consultano i progetti e si spuntano le domande fatte; le spunte tornano al PC con `GET /api/spunte`.
- Accesso con email e password (utenti creati a mano, vedi `DEPLOY.md`).

Installazione e deploy: `DEPLOY.md`. Note per Claude: `CLAUDE.md`.
