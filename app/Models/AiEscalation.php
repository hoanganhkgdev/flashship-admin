<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiEscalation extends Model
{
    protected $fillable = [
        'sender_id',
        'platform',
        'source_type',
        'source_id',
        'reason',
        'urgency',
        'conversation_summary',
        'status',
        'assigned_to',
        'resolution_note',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function source()
    {
        return $this->morphTo();
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function urgencyLabel(): string
    {
        return match ($this->urgency) {
            'high'   => '🔴 Khẩn cấp',
            'medium' => '🟡 Trung bình',
            'low'    => '🟢 Thấp',
            default  => $this->urgency,
        };
    }
}
