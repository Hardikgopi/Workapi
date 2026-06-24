<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $connection = 'tenant';
    protected $table = 'contacts';

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone',
        'company', 'job_title', 'address', 'city', 'country',
        'notes', 'assigned_to',
    ];
}
