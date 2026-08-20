<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationSetting extends Model
{
    public const KEY_SPT_NUMBER_FORMAT = 'document_number_format.spt';
    public const KEY_SPPD_NUMBER_FORMAT = 'document_number_format.sppd';

    protected $fillable = [
        'key',
        'value',
    ];
}
