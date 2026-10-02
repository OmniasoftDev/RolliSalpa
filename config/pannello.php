<?php

return [
    // Token con cui lo script orario sul PC di Francesco chiama /api/sync e /api/spunte.
    // Stringa lunga a caso, uguale in .env del server e in automazione/pannello.config.json sul PC.
    'token' => env('PANNELLO_SYNC_TOKEN', ''),
];
