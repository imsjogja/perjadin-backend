<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SppdSignatory extends Model
{
    use HasUuids;

    protected $fillable = [
        'sikkepo_pegawai_id',
        'employee_snapshot',
        'behalf_of',
        'signatory_role',
        'is_acting',
    ];

    protected $casts = [
        'employee_snapshot' => 'array',
        'is_acting' => 'boolean',
    ];

    public function sppd(): BelongsTo
    {
        return $this->belongsTo(Sppd::class);
    }
}
