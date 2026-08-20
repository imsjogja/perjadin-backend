<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SppdFollower extends Model
{
    use HasUuids;

    protected $fillable = [
        'sikkepo_pegawai_id',
        'employee_snapshot',
    ];

    protected $casts = [
        'employee_snapshot' => 'array',
    ];

    public function sppd(): BelongsTo
    {
        return $this->belongsTo(Sppd::class);
    }
}
