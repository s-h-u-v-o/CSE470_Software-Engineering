<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Model;

/** Append-only ticket history. Posted events are never edited or deleted. */
class TicketEvent extends Model
{
    protected $fillable = [
        'ticket_id', 'event_type', 'from_state', 'to_state', 'from_severity', 'to_severity',
        'actor_id', 'actor_role', 'reason', 'occurred_at', 'recorded_at',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'recorded_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ticket events are append-only.'));
        static::deleting(fn () => throw new LogicException('Ticket events are append-only.'));
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
