<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $connection = 'tenant';
    protected $table = 'payments';

    protected $fillable = [
        'razorpay_payment_link_id',
        'reference_id',
        'ticket_id',
        'created_by',
        'amount',
        'amount_paid',
        'amount_due',
        'currency',
        'description',
        'customer_name',
        'customer_email',
        'customer_contact',
        'short_url',
        'status',
        'expires_at',
        'paid_at',
        'cancelled_at',
        'expired_at',
        'provider_payload',
    ];

    protected $casts = [
        'amount' => 'integer',
        'amount_paid' => 'integer',
        'amount_due' => 'integer',
        'provider_payload' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expired_at' => 'datetime',
    ];
}
