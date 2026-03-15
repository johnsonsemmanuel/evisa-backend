<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ICAO-mandated audit trail for all Aeropass interactions.
 * request_payload and response_payload are encrypted (may contain PII).
 */
class AeropassAuditLog extends Model
{
    protected $fillable = [
        'application_id',
        'interaction_type',
        'request_payload',
        'response_payload',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
