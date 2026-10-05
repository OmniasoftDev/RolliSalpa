<?php

return [
    // Token con cui lo script orario sul PC di Francesco chiama /api/sync e /api/spunte.
    // Stringa lunga a caso, uguale in .env del server e in automazione/pannello.config.json sul PC.
    'token' => env('PANNELLO_SYNC_TOKEN', ''),

    // Calendario Google in cui il link "Google Calendar" di "Il mio lavoro" apre gli eventi: "Salpa-Rolli" di Francesco,
    // condiviso con l'account Omniasoft (Francesco, 05/10/2026).
    'calendario' => env('PANNELLO_GCAL', '15d308d04bcc0b634523e7a3fe5ea816fc2ddfb6a69286adc4e2ae30ad027361@group.calendar.google.com'),
];
