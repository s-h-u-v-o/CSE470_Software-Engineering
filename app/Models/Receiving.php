<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receiving extends Model
{
    // This allows the ReceivingService to save these columns
    protected $fillable = ['asset_id', 'received_by', 'received_date'];

    // Relationship to the Asset
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    // Relationship to the User who received it
    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}