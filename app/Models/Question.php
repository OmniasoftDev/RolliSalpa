<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    protected $fillable = ['machine_id', 'chiave', 'ordine', 'chi', 'cosa', 'fatto', 'fatto_il', 'risposta', 'fatto_web'];

    protected function casts(): array
    {
        return [
            'fatto' => 'boolean',
            'fatto_web' => 'boolean',
            'fatto_il' => 'date',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
