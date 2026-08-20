<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sppd extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_VERIFIED = 'verified';

    protected $fillable = [
        'spt_id',
        'unit_id',
        'sikkepo_pegawai_id',
        'employee_snapshot',
        'document_year',
        'sequence_number',
        'registration_number',
        'document_number',
        'order_giver',
        'travel_level',
        'travel_type',
        'departure_date',
        'return_date',
        'budget_agency',
        'budget_account',
        'description',
        'issued_place',
        'issued_date',
        'status',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'employee_snapshot' => 'array',
        'departure_date' => 'date',
        'return_date' => 'date',
        'issued_date' => 'date',
        'verified_at' => 'datetime',
    ];

    public function spt(): BelongsTo
    {
        return $this->belongsTo(Spt::class);
    }

    public function followers(): HasMany
    {
        return $this->hasMany(SppdFollower::class);
    }

    public function signatory(): HasOne
    {
        return $this->hasOne(SppdSignatory::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
