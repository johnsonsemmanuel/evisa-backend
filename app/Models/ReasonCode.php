<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReasonCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'action_type',
        'reason',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForAction($query, string $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    public function scopeApproval($query)
    {
        return $query->where('action_type', 'approve');
    }

    public function scopeRejection($query)
    {
        return $query->where('action_type', 'reject');
    }

    public function scopeRequestInfo($query)
    {
        return $query->where('action_type', 'request_info');
    }

    public function scopeBorder($query)
    {
        return $query->whereIn('action_type', ['border_admit', 'border_deny', 'border_secondary']);
    }
}
