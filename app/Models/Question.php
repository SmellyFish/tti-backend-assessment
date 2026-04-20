<?php

namespace App\Models;

use App\Enums\ResponseType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    protected $fillable = [
        'instrument_id',
        'prompt',
        'response_type',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'response_type' => ResponseType::class,
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }
}
