<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $connection = 'tenant';
    protected $table = 'tasks';

    protected $fillable = [
        'title', 'description', 'priority', 'status',
        'due_date', 'related_type', 'related_id',
        'assigned_to', 'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];
}
