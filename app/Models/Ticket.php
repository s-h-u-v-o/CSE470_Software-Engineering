<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'asset_id', 'reported_by', 'assigned_to', 'state', 'severity',
        'description', 'occurred_at', 'recorded_at',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'recorded_at' => 'datetime'];
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
