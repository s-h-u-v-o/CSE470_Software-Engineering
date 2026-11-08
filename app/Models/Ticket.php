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

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function events()
    {
        return $this->hasMany(TicketEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function slaPauses()
    {
        return $this->hasMany(SlaPause::class)->orderBy('paused_at')->orderBy('id');
    }
}
