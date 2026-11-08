<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlaPause extends Model
{
    protected $fillable = [
        'ticket_id', 'reason_code', 'note', 'ticket_state',
        'paused_at', 'paused_by', 'paused_by_role',
        'resumed_at', 'resumed_by', 'resumed_by_role', 'resume_reason',
    ];

    protected function casts(): array
    {
        return ['paused_at' => 'datetime', 'resumed_at' => 'datetime'];
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function pausedBy()
    {
        return $this->belongsTo(User::class, 'paused_by');
    }

    public function resumedBy()
    {
        return $this->belongsTo(User::class, 'resumed_by');
    }

    public function isActive(): bool
    {
        return $this->resumed_at === null;
    }
}
