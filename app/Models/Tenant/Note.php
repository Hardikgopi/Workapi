<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected $connection = 'tenant';
    protected $table = 'notes';

    protected $fillable = [
        'content', 'related_type', 'related_id', 'created_by',
    ];
}
