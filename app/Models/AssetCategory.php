<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetCategory extends Model
{
    // Add this line to fix the error!
    protected $fillable = ['name', 'description', 'requires_calibration'];

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }
}