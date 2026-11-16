<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperatorAuthorization extends Model
{
    protected $fillable = ['user_id', 'asset_category_id', 'valid_from', 'valid_to'];
}
