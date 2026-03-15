<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'application_id',
        'gateway',
        'gateway_refund_reference',
        'amount',
        'reason',
        'attachments',
        'status',
        'initiated_by',
        'approved_by',
        'rejected_by',
        'initiated_at',
        'approved_at',
        'rejected_at',
        'processed_at',
        'gateway_response',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'attachments' => 'array',
            'gateway_response' => 'array',
            'initiated_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the payment this refund is for.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the application this refund is for.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the user who initiated the refund.
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /**
     * Get the user who approved the refund.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who rejected the refund.
     */
    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    // ==================== ACCESSORS ====================

    /**
     * Get the amount in currency units.
     */
    public function getAmountInCurrencyAttribute(): float
    {
        return $this->amount / 100;
    }

    /**
     * Check if refund requires dual approval.
     */
    public function requiresDualApproval(): bool
    {
        return $this->amount > 50000; // > GHS 500
    }

    /**
     * Check if refund is pending.
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['pending_approval', 'awaiting_second_approval']);
    }

    /**
     * Check if refund is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if refund is processed.
     */
    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    /**
     * Check if refund is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if refund failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope: Filter pending refunds.
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending_approval', 'awaiting_second_approval']);
    }

    /**
     * Scope: Filter refunds awaiting second approval.
     */
    public function scopeAwaitingSecondApproval($query)
    {
        return $query->where('status', 'awaiting_second_approval');
    }

    /**
     * Scope: Filter approved refunds.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Filter processed refunds.
     */
    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    /**
     * Scope: Filter rejected refunds.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope: Filter failed refunds.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: Filter by gateway.
     */
    public function scopeForGateway($query, string $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    /**
     * Scope: Filter by initiator.
     */
    public function scopeInitiatedBy($query, int $userId)
    {
        return $query->where('initiated_by', $userId);
    }

    /**
     * Scope: Filter refunds requiring dual approval.
     */
    public function scopeRequiringDualApproval($query)
    {
        return $query->where('amount', '>', 50000);
    }

    /**
     * Scope: Filter refunds not initiated by user.
     */
    public function scopeNotInitiatedBy($query, int $userId)
    {
        return $query->where('initiated_by', '!=', $userId);
    }
}
