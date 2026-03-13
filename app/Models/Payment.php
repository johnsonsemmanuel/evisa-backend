<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'application_id',
        'user_id',
        'transaction_reference',
        'payment_provider',
        'provider_reference',
        'amount',
        'currency',
        'status',
        'provider_response',
        'metadata',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'            => 'decimal:2',
            'provider_response' => 'array',
            'metadata'          => 'array',
            'paid_at'           => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
