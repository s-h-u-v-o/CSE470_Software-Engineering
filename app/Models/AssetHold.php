<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetHold extends Model
{
    protected $fillable = [
        'asset_id', 'type', 'reason', 'started_at', 'ended_at', 'actor_id',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
