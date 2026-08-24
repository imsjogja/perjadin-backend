<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LegacyImportRecord extends Model
{
    use HasUuids;

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_QUARANTINED = 'quarantined';

    protected $fillable = [
        'batch_id',
        'source_database',
        'source_table',
        'source_id',
        'target_table',
        'target_id',
        'source_checksum',
        'status',
        'message',
    ];
}
