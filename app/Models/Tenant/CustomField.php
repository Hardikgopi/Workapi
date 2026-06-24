<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class CustomField extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'module',
        'name',
        'label',
        'ui_type',
        'options',
        'is_required',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
    ];
}
