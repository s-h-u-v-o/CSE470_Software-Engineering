<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusEvent extends Model
{
    // This allows the LifecycleService to save data into these columns
    protected $fillable = [
        'asset_id', 
        'previous_state', 
        'next_state', 
        'occurred_at', 
        'recorded_at', 
        'cause_type',
        'ticket_id',
        'actor_id', 
        'reason'
    ];

    // Relationships
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}