<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessaggio extends Model
{
    protected $table = 'chat_messaggi';

    protected $fillable = ['project_id', 'user_id', 'ruolo', 'testo', 'uso'];

    protected function casts(): array
    {
        return ['uso' => 'array'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
