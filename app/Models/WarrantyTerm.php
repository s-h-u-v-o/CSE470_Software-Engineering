<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarrantyTerm extends Model
{
    protected $fillable = ['asset_id', 'provider_name', 'term_type', 'start_date', 'end_date'];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}