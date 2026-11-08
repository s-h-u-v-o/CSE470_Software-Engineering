<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** F26: append-only audit chain entry. Posted entries are never edited or deleted. */
class AuditEntry extends Model
{
    protected $fillable = [
        'sequence', 'event_type', 'actor_id', 'actor_role', 'subject_type', 'subject_id',
        'payload', 'occurred_at', 'prev_hash', 'hash',
    ];

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'payload' => 'array', 'occurred_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit entries are append-only.'));
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
