<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Ticket extends Model
{
    protected $connection = 'tenant';
    protected $table = 'tickets';

    protected $fillable = [
        'title', 'description', 'priority', 'status',
        'category', 'raised_by', 'assigned_to', 'resolved_at',
        'attachment_path', 'attachment_name',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    protected $appends = [
        'attachment_url',
    ];

    public function getAttachmentUrlAttribute(): ?string
    {
        if (empty($this->attachment_path)) {
            return null;
        }

        try {
            return Storage::disk('r2')->temporaryUrl(
                $this->attachment_path,
                now()->addMinutes(30)
            );
        } catch (\Throwable $e) {
            try {
                return Storage::disk('r2')->url($this->attachment_path);
            } catch (\Throwable $e) {
                return null;
            }
        }
    }
}
