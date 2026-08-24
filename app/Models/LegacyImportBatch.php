<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LegacyImportBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'source_connection',
        'source_database',
        'dry_run',
        'status',
        'summary',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'dry_run' => 'boolean',
        'summary' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
