<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = ['asset_category_id', 'name', 'serial_number', 'lifecycle_state'];

    public function category()
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }
    public function statusEvents()
    {
        return $this->hasMany(StatusEvent::class);
    }
    public function commissioningChecklists()
    {
        return $this->hasMany(CommissioningChecklist::class);
    }
}