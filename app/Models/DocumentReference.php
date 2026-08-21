<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentReference extends Model
{
    public const CATEGORY_BUDGET_ACCOUNT = 'budget_account';
    public const CATEGORY_TRANSPORTATION = 'transportation';
    public const CATEGORY_TRAVEL_LEVEL = 'travel_level';
    public const CATEGORY_TRAVEL_TYPE = 'travel_type';

    protected $fillable = [
        'category',
        'value',
    ];
}
