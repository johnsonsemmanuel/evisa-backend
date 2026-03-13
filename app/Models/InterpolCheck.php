<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterpolCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'unique_reference_id',
        'first_name',
        'surname',
        'date_of_birth',
        'status',
        'interpol_nominal_matched',
        'aeropass_response',
        'callback_received_at',
        'retry_count',
        'last_error',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'aeropass_response' => 'array',
        'callback_received_at' => 'datetime',
        'interpol_nominal_matched' => 'boolean',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function isMatched(): bool
    {
        return $this->interpol_nominal_matched === true;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['matched', 'no_match']);
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}