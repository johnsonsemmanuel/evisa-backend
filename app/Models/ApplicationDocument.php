<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationDocument extends Model
{
    use HasFactory, Auditable;

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
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function needsReupload(): bool
    {
        return $this->verification_status === 'reupload_requested' || $this->ocr_status === 'failed';
    }
}
