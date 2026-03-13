<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'risk_score',
        'risk_level',
        'factors',
        'watchlist_match',
        'watchlist_matches',
        'document_verified',
        'document_checks',
        'nationality_risk',
        'travel_history_risk',
        'previous_denial',
        'overstay_history',
        'notes',
        'status',
        'assessed_by_id',
        'assessed_at',
    ];

    protected $casts = [
        'factors' => 'array',
        'watchlist_matches' => 'array',
        'document_checks' => 'array',
        'watchlist_match' => 'boolean',
        'document_verified' => 'boolean',
        'nationality_risk' => 'boolean',
        'travel_history_risk' => 'boolean',
        'previous_denial' => 'boolean',
        'overstay_history' => 'boolean',
        'assessed_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeRequiresManualReview($query)
    {
        return $query->where('status', 'manual_review');
    }

    public function scopeHighRisk($query)
    {
        return $query->whereIn('risk_level', ['high', 'critical']);
    }

    public static function calculateRiskLevel(int $score): string
    {
        if ($score >= 80) {
            return 'critical';
        } elseif ($score >= 60) {
            return 'high';
        } elseif ($score >= 30) {
            return 'medium';
        }
        return 'low';
    }
}
