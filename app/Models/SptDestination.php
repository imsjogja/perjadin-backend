<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SptDestination extends Model
{
    use HasUuids;

    protected $fillable = [
        'transportation',
        'departure_place',
        'destination_place',
        'duration_days',
    ];

    public function spt(): BelongsTo
    {
        return $this->belongsTo(Spt::class);
    }
}
