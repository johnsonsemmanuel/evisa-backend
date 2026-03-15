<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * EncryptedString Cast - AES-256 Encryption for PII
 * 
 * Implements field-level encryption using Laravel's built-in Crypt facade.
 * Required by Ghana's Data Protection Act and ISO 27001.
 * 
 * SECURITY FEATURES:
 * - Uses APP_KEY for AES-256-CBC encryption
 * - Never encrypts already-encrypted values (checks Laravel's encryption prefix)
 * - Never logs plaintext values
 * - Gracefully handles decryption failures (returns null, logs warning)
 * - Handles null values correctly
 * 
 * USAGE:
 * protected $casts = [
 *     'passport_number' => EncryptedString::class,
 *     'date_of_birth' => EncryptedString::class,
 * ];
 */
class EncryptedString implements CastsAttributes
{
    /**
     * Cast the given value to encrypted string.
     *
     * @param  array<string, mixed>  $attributes
     * @return string|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        // Handle null values
        if ($value === null) {
            return null;
        }

        // Handle empty strings
        if ($value === '') {
            return '';
        }

        try {
            // Attempt to decrypt the value
            return Crypt::decryptString($value);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            // SECURITY: Log warning WITHOUT the encrypted value
            Log::warning('PII decryption failed', [
                'model' => get_class($model),
                'model_id' => $model->getKey(),
                'field' => $key,
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);

            // Return null instead of throwing exception
            // This prevents exposing which records have bad encryption keys
            return null;
        } catch (\Exception $e) {
            // Catch any other exceptions (malformed data, etc.)
            Log::error('PII decryption error', [
                'model' => get_class($model),
                'model_id' => $model->getKey(),
                'field' => $key,
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);

            return null;
        }
    }

    /**
     * Prepare the given value for storage (encryption).
     *
     * @param  array<string, mixed>  $attributes
     * @return string|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        // Handle null values
        if ($value === null) {
            return null;
        }

        // Handle empty strings
        if ($value === '') {
            return '';
        }

        // Convert to string if not already
        $value = (string) $value;

        // CRITICAL: Check if value is already encrypted
        // Laravel encrypted strings start with "eyJpdiI6" (base64 of {"iv":)
        // This prevents double-encryption
        if ($this->isAlreadyEncrypted($value)) {
            return $value;
        }

        try {
            // Encrypt the value using AES-256-CBC
            return Crypt::encryptString($value);
        } catch (\Exception $e) {
            // SECURITY: Log error WITHOUT the plaintext value
            Log::error('PII encryption failed', [
                'model' => get_class($model),
                'model_id' => $model->getKey(),
                'field' => $key,
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);

            // Re-throw exception as encryption failure is critical
            throw $e;
        }
    }

    /**
     * Check if a value is already encrypted.
     * 
     * Laravel's Crypt::encryptString() produces a JSON payload that is base64 encoded.
     * The JSON structure is: {"iv":"...","value":"...","mac":"..."}
     * When base64 encoded, it starts with "eyJpdiI6" (base64 of {"iv":)
     * 
     * @param string $value
     * @return bool
     */
    protected function isAlreadyEncrypted(string $value): bool
    {
        // Check for Laravel's encryption prefix
        if (str_starts_with($value, 'eyJpdiI6')) {
            return true;
        }

        // Additional check: try to decode as JSON and verify structure
        $decoded = base64_decode($value, true);
        if ($decoded !== false) {
            $json = json_decode($decoded, true);
            if (is_array($json) && isset($json['iv'], $json['value'], $json['mac'])) {
                return true;
            }
        }

        return false;
    }
}
