<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Mail di progetto trovata in Outlook (info@omniasoft.it) dal controllo orario. */
class MailRegistro extends Model
{
    protected $table = 'mail_registro';

    protected $fillable = ['codice', 'ricevuta', 'cartella', 'da', 'a', 'cc', 'oggetto', 'testo', 'allegati', 'inviata', 'elaborata_at', 'trovata_controllo', 'vista_at'];

    protected function casts(): array
    {
        return ['ricevuta' => 'datetime', 'allegati' => 'array', 'inviata' => 'boolean', 'elaborata_at' => 'datetime', 'vista_at' => 'datetime'];
    }
}
