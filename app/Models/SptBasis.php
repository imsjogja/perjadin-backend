<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SptBasis extends Model
{
    use HasUuids;

    protected $fillable = [
        'content',
        'sort_order',
    ];

    public function spt(): BelongsTo
    {
        return $this->belongsTo(Spt::class);
    }
}
