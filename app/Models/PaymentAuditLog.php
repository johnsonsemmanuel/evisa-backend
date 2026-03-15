<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class PaymentAuditLog extends Model
{
    use HasFactory;

    /**
     * NO $fillable - use create() with explicit fields only.
     * This prevents accidental mass assignment of audit data.
     */
    protected $guarded = ['id'];

    /**
     * NO updated_at - audit logs are immutable.
     */
    public const UPDATED_AT = null;

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
            'created_at' => 'datetime',
        ];
    }

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the payment that this audit log belongs to.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the application that this audit log belongs to.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the user who performed the action (if applicable).
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    // ==================== STATIC HELPER METHODS ====================

    /**
     * Create a payment audit log entry.
     * 
     * @param string $event Event type (e.g., 'payment_initiated', 'payment_status_changed')
     * @param Payment $payment The payment being audited
     * @param array $data Additional data (old_value, new_value, notes, etc.)
     * @return self
     */
    public static function log(string $event, Payment $payment, array $data = []): self
    {
        // Determine actor information
        $actorId = $data['actor_id'] ?? Auth::id();
        $actorType = $data['actor_type'] ?? self::determineActorType($data);
        
        // Get IP address and user agent from request if available
        $ipAddress = $data['ip_address'] ?? request()?->ip();
        $userAgent = $data['user_agent'] ?? request()?->userAgent();

        return self::create([
            'event_type' => $event,
            'payment_id' => $payment->id,
            'application_id' => $payment->application_id,
            'actor_id' => $actorId,
            'actor_type' => $actorType,
            'old_value' => $data['old_value'] ?? null,
            'new_value' => $data['new_value'] ?? null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Determine actor type based on context.
     */
    protected static function determineActorType(array $data): string
    {
        if (isset($data['actor_type'])) {
            return $data['actor_type'];
        }

        // If there's an authenticated user, it's a user action
        if (Auth::check()) {
            return 'user';
        }

        // If the request is from a webhook endpoint, it's a gateway action
        if (request()?->is('api/webhooks/*')) {
            return 'gateway';
        }

        // Default to system
        return 'system';
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope: Filter by event type.
     */
    public function scopeOfType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope: Filter by payment.
     */
    public function scopeForPayment($query, int $paymentId)
    {
        return $query->where('payment_id', $paymentId);
    }

    /**
     * Scope: Filter by application.
     */
    public function scopeForApplication($query, int $applicationId)
    {
        return $query->where('application_id', $applicationId);
    }

    /**
     * Scope: Filter by actor.
     */
    public function scopeByActor($query, int $actorId)
    {
        return $query->where('actor_id', $actorId);
    }

    /**
     * Scope: Filter by actor type.
     */
    public function scopeByActorType($query, string $actorType)
    {
        return $query->where('actor_type', $actorType);
    }

    /**
     * Scope: Get critical events (amount changes, suspicious activity).
     */
    public function scopeCritical($query)
    {
        return $query->whereIn('event_type', [
            'payment_amount_changed',
            'payment_suspicious_activity',
            'payment_fraud_detected',
        ]);
    }

    /**
     * Scope: Get timeline for a payment.
     */
    public function scopeTimeline($query, int $paymentId)
    {
        return $query->where('payment_id', $paymentId)
                     ->orderBy('created_at', 'asc');
    }
}
