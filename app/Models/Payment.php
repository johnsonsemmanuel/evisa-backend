<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, Auditable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     * amount is in pesewas/cents (BIGINT); set only via trusted code paths.
     */
    protected $fillable = [
        'application_id',
        'user_id',
        'gateway',
        'transaction_reference',
        'payment_provider',
        'gateway_reference',
        'currency',
        'amount',
        'status',
        'provider_response',
        'raw_response',
        'metadata',
        'paid_at',
        'failure_reason',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'amount'            => 'integer',        // Stored as pesewas/cents (BIGINT)
            'status'            => \App\Enums\PaymentStatus::class,
            'gateway'           => \App\Enums\PaymentGateway::class,
            'provider_response' => 'array',
            'raw_response'      => 'array',
            'metadata'          => 'array',
            'paid_at'           => 'datetime',
            'deleted_at'        => 'datetime',
        ];
    }

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'raw_response',      // Contains sensitive gateway data
        'provider_response', // Contains sensitive gateway data
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the application that owns the payment.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    /**
     * Get the user that owns the payment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the audit logs for this payment.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(PaymentAuditLog::class, 'payment_id');
    }

    // ==================== ACCESSORS & MUTATORS ====================

    /**
     * Get the amount in currency units (e.g., cedis, dollars).
     * Converts from pesewas/cents to main currency unit.
     */
    public function getAmountInCurrencyAttribute(): float
    {
        return $this->amount / 100;
    }

    /**
     * Set the amount from currency units.
     * Converts from main currency unit to pesewas/cents.
     */
    public function setAmountFromCurrencyAttribute(float $value): void
    {
        $this->attributes['amount'] = (int) round($value * 100);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Check if payment is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === \App\Enums\PaymentStatus::Paid;
    }

    /**
     * Check if payment is pending.
     */
    public function isPending(): bool
    {
        return in_array($this->status, [
            \App\Enums\PaymentStatus::Initiated,
            \App\Enums\PaymentStatus::Processing
        ]);
    }

    /**
     * Check if payment failed.
     */
    public function isFailed(): bool
    {
        return $this->status === \App\Enums\PaymentStatus::Failed;
    }

    /**
     * Check if payment is suspicious (amount mismatch detected).
     */
    public function isSuspicious(): bool
    {
        return $this->status === \App\Enums\PaymentStatus::Suspicious;
    }

    /**
     * Transition payment to a new status with validation
     * 
     * @param \App\Enums\PaymentStatus $newStatus
     * @throws \App\Exceptions\InvalidStatusTransitionException
     */
    public function transitionTo(\App\Enums\PaymentStatus $newStatus): void
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new \App\Exceptions\InvalidStatusTransitionException(
                "Cannot transition payment from {$this->status->value} to {$newStatus->value}"
            );
        }

        $this->update(['status' => $newStatus]);
    }

    /**
     * Mark payment as paid.
     */
    public function markAsPaid(): void
    {
        $this->transitionTo(\App\Enums\PaymentStatus::Paid);
        $this->update(['paid_at' => now()]);
    }

    /**
     * Mark payment as failed.
     */
    public function markAsFailed(string $reason = null): void
    {
        $this->transitionTo(\App\Enums\PaymentStatus::Failed);
        if ($reason) {
            $this->update(['failure_reason' => $reason]);
        }
    }

    /**
     * Mark payment as suspicious (amount mismatch).
     */
    public function markAsSuspicious(string $reason = null): void
    {
        $this->transitionTo(\App\Enums\PaymentStatus::Suspicious);
        if ($reason) {
            $this->update(['failure_reason' => $reason]);
        }
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope: Filter completed payments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', \App\Enums\PaymentStatus::Paid->value);
    }

    /**
     * Scope: Filter pending payments.
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [
            \App\Enums\PaymentStatus::Initiated->value,
            \App\Enums\PaymentStatus::Processing->value
        ]);
    }

    /**
     * Scope: Filter failed payments.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', \App\Enums\PaymentStatus::Failed->value);
    }

    /**
     * Scope: Filter suspicious payments.
     */
    public function scopeSuspicious($query)
    {
        return $query->where('status', \App\Enums\PaymentStatus::Suspicious->value);
    }

    /**
     * Scope: Filter by gateway.
     */
    public function scopeForGateway($query, string $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    /**
     * Scope: Filter by application.
     */
    public function scopeForApplication($query, int $applicationId)
    {
        return $query->where('application_id', $applicationId);
    }

    /**
     * Scope: Filter by currency.
     */
    public function scopeForCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }

    /**
     * Scope: Filter payments within date range.
     */
    public function scopeWithinDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope: Filter payments that need reconciliation.
     */
    public function scopeNeedsReconciliation($query)
    {
        return $query->whereIn('status', [
                    \App\Enums\PaymentStatus::Suspicious->value,
                    \App\Enums\PaymentStatus::Failed->value
                ])
                ->orWhere(function ($q) {
                    $q->where('status', \App\Enums\PaymentStatus::Processing->value)
                      ->where('created_at', '<', now()->subHours(24));
                });
    }
}