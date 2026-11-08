<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlaPolicy extends Model
{
    protected $fillable = ['severity', 'response_minutes', 'resolution_minutes', 'pause_allowed'];

    protected function casts(): array
    {
        return [
            'response_minutes' => 'integer',
            'resolution_minutes' => 'integer',
            'pause_allowed' => 'boolean',
        ];
    }

    public static function forSeverity(?string $severity): ?self
    {
        return $severity ? static::where('severity', $severity)->first() : null;
    }
}
