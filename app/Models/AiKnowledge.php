<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiKnowledge extends Model
{
    protected $table = 'ai_knowledges';

    protected $fillable = [
        'city_id',
        'title',
        'type',
        'input_text',
        'output_data',
        'is_active',
    ];

    protected $casts = [
        'output_data' => 'array',
        'is_active' => 'boolean',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
