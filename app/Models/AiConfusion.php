<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiConfusion extends Model
{
    protected $fillable = [
        'sender_id',
        'platform',
        'confused_message',
        'clarified_message',
        'resolved_action',
        'resolved_args',
        'is_learned',
        'ai_knowledge_id',
        'city_id',
    ];

    protected $casts = [
        'resolved_args' => 'array',
        'is_learned'    => 'boolean',
    ];
}
