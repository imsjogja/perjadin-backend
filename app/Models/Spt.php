<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Spt extends Model
{
    use HasUuids;

    protected $fillable = [
        'unit_id',
        'document_year',
        'sequence_number',
        'registration_number',
        'document_number',
        'dasar',
        'disposisi',
        'dalam_rangka',
        'issued_place',
        'issued_date',
        'assignment_revision',
        'assignment_updated_at',
        'assignment_updated_by',
    ];

    protected $casts = [
        'issued_date' => 'date',
        'assignment_updated_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function destination(): HasOne
    {
        return $this->hasOne(SptDestination::class);
    }

    public function signatory(): HasOne
    {
        return $this->hasOne(SptSignatory::class);
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(SptAssignee::class);
    }

    public function sppds(): HasMany
    {
        return $this->hasMany(Sppd::class);
    }

    public function bases(): HasMany
    {
        return $this->hasMany(SptBasis::class)->orderBy('sort_order');
    }
}
