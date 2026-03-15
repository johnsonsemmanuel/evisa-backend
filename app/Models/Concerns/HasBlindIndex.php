<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * HasBlindIndex Trait - Searchable Encrypted Fields
 * 
 * Provides blind indexing for encrypted PII fields that need to be searchable.
 * A blind index is a one-way hash (HMAC-SHA256) of the plaintext value,
 * stored alongside the encrypted value for equality searches.
 * 
 * SECURITY:
 * - Uses separate BLIND_INDEX_KEY from .env (NOT the APP_KEY)
 * - One-way hash - cannot be reversed to get plaintext
 * - Only supports equality searches (not LIKE, range, etc.)
 * - Index column is NOT encrypted (it's a hash for searching)
 * 
 * USAGE:
 * 1. Add trait to model: use HasBlindIndex;
 * 2. Add index column to migration: $table->string('passport_number_idx', 64)->index();
 * 3. Override boot() to auto-generate index on save
 * 4. Use scope for searching: Application::wherePassportNumber('ABC123')->get();
 */
trait HasBlindIndex
{
    /**
     * Boot the trait and register model events.
     */
    protected static function bootHasBlindIndex(): void
    {
        // Automatically generate blind indexes before saving
        static::saving(function ($model) {
            $model->generateBlindIndexes();
        });
    }

    /**
     * Generate blind indexes for all indexed fields.
     * 
     * Override this method in your model to specify which fields need blind indexes.
     */
    protected function generateBlindIndexes(): void
    {
        // Check if passport_number has changed and generate index
        if ($this->isDirty('passport_number_encrypted') && $this->passport_number_encrypted) {
            // Decrypt to get plaintext for hashing
            $plaintext = $this->getAttributeFromArray('passport_number');
            if ($plaintext) {
                $this->attributes['passport_number_idx'] = $this->generateBlindIndex($plaintext);
            }
        }

        // Add more indexed fields here as needed
        // Example: email_idx, phone_idx, etc.
    }

    /**
     * Generate a blind index (HMAC-SHA256) for a plaintext value.
     * 
     * @param string $plaintext
     * @return string 64-character hex string
     */
    protected function generateBlindIndex(string $plaintext): string
    {
        $key = config('app.blind_index_key');

        if (!$key) {
            throw new \RuntimeException('BLIND_INDEX_KEY not configured in .env');
        }

        // Generate HMAC-SHA256 hash
        // This is a one-way hash that cannot be reversed
        return hash_hmac('sha256', strtoupper(trim($plaintext)), $key);
    }

    /**
     * Scope: Search by passport number using blind index.
     * 
     * USAGE: Application::wherePassportNumber('ABC123')->get();
     * 
     * @param Builder $query
     * @param string $passportNumber
     * @return Builder
     */
    public function scopeWherePassportNumber(Builder $query, string $passportNumber): Builder
    {
        $index = $this->generateBlindIndex($passportNumber);
        return $query->where('passport_number_idx', $index);
    }

    /**
     * Scope: Search by email using blind index.
     * 
     * USAGE: Application::whereEmail('user@example.com')->get();
     * 
     * @param Builder $query
     * @param string $email
     * @return Builder
     */
    public function scopeWhereEmail(Builder $query, string $email): Builder
    {
        $index = $this->generateBlindIndex($email);
        return $query->where('email_idx', $index);
    }

    /**
     * Scope: Search by phone number using blind index.
     * 
     * USAGE: Application::wherePhone('+233123456789')->get();
     * 
     * @param Builder $query
     * @param string $phone
     * @return Builder
     */
    public function scopeWherePhone(Builder $query, string $phone): Builder
    {
        $index = $this->generateBlindIndex($phone);
        return $query->where('phone_idx', $index);
    }
}
