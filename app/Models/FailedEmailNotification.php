<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailedEmailNotification extends Model
{
    protected $fillable = [
        'mailable_class',
        'application_id',
        'attempted_at',
        'failure_reason',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function markResolved(): void
    {
        $this->update(['resolved_at' => now()]);
    }
}
