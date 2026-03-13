<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIConversationContext extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'conversation_id',
        'user_id',
        'context_data',
        'last_query',
        'last_intent',
        'message_count',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context_data' => 'array',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the conversation context.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the conversation context has expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Extend the expiration time by the specified minutes.
     *
     * @param int $minutes
     * @return void
     */
    public function extendExpiration(int $minutes = 30): void
    {
        $this->expires_at = now()->addMinutes($minutes);
        $this->save();
    }

    /**
     * Clear the conversation context data.
     *
     * @return void
     */
    public function clearContext(): void
    {
        $this->context_data = [
            'messages' => [],
            'last_intent' => null,
            'last_entities' => [],
        ];
        $this->last_query = null;
        $this->last_intent = null;
        $this->message_count = 0;
        $this->save();
    }
}
