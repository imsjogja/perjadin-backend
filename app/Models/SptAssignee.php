<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SptAssignee extends Model
{
    use HasUuids;

    protected $fillable = [
        'sikkepo_pegawai_id',
        'employee_snapshot',
        'assignment_revision',
        'assigned_at',
        'assigned_by',
    ];

    protected $casts = [
        'employee_snapshot' => 'array',
        'assigned_at' => 'datetime',
    ];

    public function spt(): BelongsTo
    {
        return $this->belongsTo(Spt::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
