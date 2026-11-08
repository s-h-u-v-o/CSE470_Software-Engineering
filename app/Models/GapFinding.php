<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GapFinding extends Model
{
    protected $fillable = [
        'code', 'fingerprint', 'subject_type', 'subject_id', 'message', 'records', 'next_step',
        'first_detected_at', 'last_seen_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'records' => 'array',
            'first_detected_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
