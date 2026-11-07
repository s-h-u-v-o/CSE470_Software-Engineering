<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissioningChecklist extends Model
{
    protected $fillable = ['asset_id', 'item_name', 'is_mandatory', 'is_passed', 'checked_by'];
}
