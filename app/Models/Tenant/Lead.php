<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $connection = 'tenant';
    protected $table = 'leads';

    protected $fillable = [
        'name', 'email', 'phone', 'company',
        'source', 'status', 'value', 'notes', 'assigned_to',
    ];

    protected $casts = [
        'value' => 'float',
    ];
}
