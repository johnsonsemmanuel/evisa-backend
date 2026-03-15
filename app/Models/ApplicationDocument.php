<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class ApplicationDocument extends Model
{
    use HasFactory, Auditable, SoftDeletes;

    protected $fillable = [
        'application_id',
        'document_type',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
        'ocr_status',
        'ocr_result',
        'verification_status',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'ocr_status' => \App\Enums\OcrStatus::class,
            'verification_status' => \App\Enums\DocumentVerificationStatus::class,
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function needsReupload(): bool
    {
        return $this->verification_status === \App\Enums\DocumentVerificationStatus::ReuploadRequested 
            || $this->ocr_status === \App\Enums\OcrStatus::Failed;
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope: Filter documents for a specific application
     */
    public function scopeForApplication(Builder $query, int $applicationId): Builder
    {
        return $query->where('application_id', $applicationId);
    }

    /**
     * Scope: Filter documents by type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('document_type', $type);
    }

    /**
     * Scope: Filter verified documents
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification_status', \App\Enums\DocumentVerificationStatus::Accepted->value);
    }

    /**
     * Scope: Filter documents pending verification
     */
    public function scopePendingVerification(Builder $query): Builder
    {
        return $query->where('verification_status', \App\Enums\DocumentVerificationStatus::Pending->value);
    }

    /**
     * Scope: Filter documents that need reupload
     */
    public function scopeNeedsReupload(Builder $query): Builder
    {
        return $query->where(function($q) {
            $q->where('verification_status', \App\Enums\DocumentVerificationStatus::ReuploadRequested->value)
              ->orWhere('ocr_status', \App\Enums\OcrStatus::Failed->value);
        });
    }
}
