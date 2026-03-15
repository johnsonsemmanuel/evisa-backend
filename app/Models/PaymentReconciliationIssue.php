<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReconciliationIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'gateway',
        'gateway_reference',
        'issue_type',
        'local_status',
        'gateway_status',
        'local_amount',
        'gateway_amount',
        'resolved_at',
        'resolved_by',
        'notes',
        'gateway_data',
        'reconciliation_date',
    ];

    protected function casts(): array
    {
        return [
            'gateway_data' => 'array',
            'resolved_at' => 'datetime',
            'reconciliation_date' => 'date',
            'local_amount' => 'integer',
            'gateway_amount' => 'integer',
        ];
    }

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the payment this issue relates to.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the user who resolved this issue.
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // ==================== ACCESSORS ====================

    /**
     * Get the local amount in currency units.
     */
    public function getLocalAmountInCurrencyAttribute(): ?float
    {
        return $this->local_amount ? $this->local_amount / 100 : null;
    }

    /**
     * Get the gateway amount in currency units.
     */
    public function getGatewayAmountInCurrencyAttribute(): ?float
    {
        return $this->gateway_amount ? $this->gateway_amount / 100 : null;
    }

    /**
     * Get the amount difference in pesewas.
     */
    public function getAmountDifferenceAttribute(): ?int
    {
        if ($this->local_amount === null || $this->gateway_amount === null) {
            return null;
        }
        
        return abs($this->local_amount - $this->gateway_amount);
    }

    /**
     * Get the severity level of this issue.
     */
    public function getSeverityAttribute(): string
    {
        return match ($this->issue_type) {
            'LOCAL_PAID_GATEWAY_FAILED', 'MISSING_LOCAL', 'AMOUNT_MISMATCH' => 'CRITICAL',
            'LOCAL_FAILED_GATEWAY_PAID', 'REFERENCE_NOT_FOUND' => 'HIGH',
            default => 'MEDIUM',
        };
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope: Filter unresolved issues.
     */
    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Scope: Filter resolved issues.
     */
    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }

    /**
     * Scope: Filter by gateway.
     */
    public function scopeForGateway($query, string $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    /**
     * Scope: Filter by issue type.
     */
    public function scopeOfType($query, string $issueType)
    {
        return $query->where('issue_type', $issueType);
    }

    /**
     * Scope: Filter by severity.
     */
    public function scopeBySeverity($query, string $severity)
    {
        $types = match ($severity) {
            'CRITICAL' => ['LOCAL_PAID_GATEWAY_FAILED', 'MISSING_LOCAL', 'AMOUNT_MISMATCH'],
            'HIGH' => ['LOCAL_FAILED_GATEWAY_PAID', 'REFERENCE_NOT_FOUND'],
            default => [],
        };

        return $query->whereIn('issue_type', $types);
    }

    /**
     * Scope: Filter by reconciliation date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('reconciliation_date', $date);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Check if this issue is resolved.
     */
    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * Check if this issue is critical.
     */
    public function isCritical(): bool
    {
        return $this->severity === 'CRITICAL';
    }

    /**
     * Mark this issue as resolved.
     */
    public function markAsResolved(?int $resolvedBy = null, ?string $notes = null): void
    {
        $this->update([
            'resolved_at' => now(),
            'resolved_by' => $resolvedBy ?? auth()->id(),
            'notes' => $notes ? ($this->notes ? $this->notes . "\n\n" . $notes : $notes) : $this->notes,
        ]);
    }

    /**
     * Add notes to this issue.
     */
    public function addNotes(string $notes): void
    {
        $existingNotes = $this->notes ?? '';
        $timestamp = now()->format('Y-m-d H:i:s');
        $newNotes = $existingNotes ? $existingNotes . "\n\n[{$timestamp}] {$notes}" : "[{$timestamp}] {$notes}";
        
        $this->update(['notes' => $newNotes]);
    }

    /**
     * Get a human-readable description of this issue.
     */
    public function getDescriptionAttribute(): string
    {
        return match ($this->issue_type) {
            'LOCAL_PAID_GATEWAY_FAILED' => "Payment marked as paid locally but gateway reports it as failed",
            'LOCAL_FAILED_GATEWAY_PAID' => "Payment marked as failed locally but gateway reports it as paid",
            'MISSING_LOCAL' => "Gateway has a transaction record that we don't have locally",
            'AMOUNT_MISMATCH' => "Payment amounts differ between local and gateway records",
            'REFERENCE_NOT_FOUND' => "Payment reference not found at gateway",
            default => "Unknown reconciliation issue",
        };
    }
}