<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'score',
        'risk_level',
        'scoring_engine',
        'factors',
        'assessed_by',
        'assessed_at',
        'is_current',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'score'       => 'integer',
            'risk_level'  => \App\Enums\RiskLevel::class,
            'factors'     => 'array',
            'assessed_at' => 'datetime',
            'is_current'  => 'boolean',
        ];
    }

    // ── Relationships ─────────────────────────────────────

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    // ── Scopes ────────────────────────────────────────────

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeByEngine($query, string $engine)
    {
        return $query->where('scoring_engine', $engine);
    }

    public function scopeHighRisk($query)
    {
        return $query->whereIn('risk_level', ['high', 'critical']);
    }

    // ── Helpers ───────────────────────────────────────────

    public function isHighRisk(): bool
    {
        return in_array($this->risk_level, ['high', 'critical']);
    }

    public function isCritical(): bool
    {
        return $this->risk_level === 'critical';
    }

    public function getRiskLevelColorAttribute(): string
    {
        return match($this->risk_level) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'critical' => 'red',
            default => 'gray'
        };
    }

    /**
     * Mark this score as current and unmark all others for the same application
     */
    public function markAsCurrent(): void
    {
        // Start transaction
        \DB::transaction(function () {
            // Unmark all other scores for this application
            static::where('application_id', $this->application_id)
                ->where('id', '!=', $this->id)
                ->update(['is_current' => false]);
            
            // Mark this one as current
            $this->update(['is_current' => true]);
        });
    }
}
