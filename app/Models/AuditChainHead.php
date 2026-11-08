<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Single-row pointer to the latest audit entry (id = 1). Locked while appending. */
class AuditChainHead extends Model
{
    public $incrementing = false;

    protected $fillable = ['last_sequence', 'last_hash'];

    protected function casts(): array
    {
        return ['last_sequence' => 'integer'];
    }
}
