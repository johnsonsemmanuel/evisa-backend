<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * PIIMaskingProcessor
 * 
 * Monolog processor that masks Personally Identifiable Information (PII) in log records.
 * Implements ISO 27001 A.8.11 (Data masking) compliance.
 * 
 * SECURITY: Prevents PII leakage in application logs, stack traces, and debug output.
 * 
 * @package App\Logging
 */
class PIIMaskingProcessor implements ProcessorInterface
{
    /**
     * PII field keys to mask
     */
    private const PII_KEYS = [
        'passport_number',
        'passport',
        'pan',
        'dob',
        'date_of_birth',
        'address',
        'phone',
        'phone_number',
        'email', // Only in applicant context
        'national_id',
        'mother_name',
        'mother_maiden_name',
        'place_of_birth',
        'home_address',
        'residential_address',
        'passport_issue_date',
        'passport_expiry',
        'profession',
        'marital_status',
        'country_of_birth',
    ];

    /**
     * Regex patterns for PII detection in string values
     */
    private const PII_PATTERNS = [
        'passport' => '/\b[A-Z]{1,2}[0-9]{6,9}\b/',
        'date_of_birth' => '/\b\d{4}-\d{2}-\d{2}\b/',
        'phone' => '/\b\+?[0-9]{10,15}\b/',
    ];

    /**
     * Counter for masked fields (for audit logging)
     */
    private int $maskedCount = 0;

    /**
     * Process a log record and mask PII
     *
     * @param LogRecord $record
     * @return LogRecord
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $this->maskedCount = 0;

        // Mask PII in context array
        $context = $this->maskArray($record->context);

        // Mask PII in extra array
        $extra = $this->maskArray($record->extra);

        // If any fields were masked, add a note to the context
        if ($this->maskedCount > 0) {
            $context['_pii_masked'] = "{$this->maskedCount} PII fields masked from log entry";
        }

        // Return new LogRecord with masked data
        return new LogRecord(
            datetime: $record->datetime,
            channel: $record->channel,
            level: $record->level,
            message: $record->message, // NEVER mask the message itself
            context: $context,
            extra: $extra,
            formatted: $record->formatted
        );
    }

    /**
     * Recursively mask PII in an array
     *
     * @param array $data
     * @return array
     */
    private function maskArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Recursively process nested arrays
                $data[$key] = $this->maskArray($value);
            } elseif ($this->shouldMaskKey($key)) {
                // Mask by key name
                $data[$key] = '***REDACTED***';
                $this->maskedCount++;
            } elseif (is_string($value)) {
                // Scan string values for PII patterns
                $data[$key] = $this->maskPatterns($value, $key, $data);
            }
        }

        return $data;
    }

    /**
     * Check if a key should be masked
     *
     * @param string $key
     * @return bool
     */
    private function shouldMaskKey(string $key): bool
    {
        $lowerKey = strtolower($key);

        // Check if key matches any PII keys
        foreach (self::PII_KEYS as $piiKey) {
            if (str_contains($lowerKey, $piiKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mask PII patterns in string values
     *
     * @param string $value
     * @param string $key
     * @param array $context
     * @return string
     */
    private function maskPatterns(string $value, string $key, array $context): string
    {
        $originalValue = $value;
        $lowerKey = strtolower($key);
        $contextString = strtolower(json_encode($context));

        // Mask passport numbers
        if (preg_match(self::PII_PATTERNS['passport'], $value)) {
            $value = preg_replace(self::PII_PATTERNS['passport'], 'PASSPORT-***', $value);
        }

        // Mask dates of birth (only if context suggests it's a birth date)
        if (str_contains($contextString, 'birth') || str_contains($lowerKey, 'birth') || str_contains($lowerKey, 'dob')) {
            if (preg_match(self::PII_PATTERNS['date_of_birth'], $value)) {
                $value = preg_replace(self::PII_PATTERNS['date_of_birth'], 'DOB-***', $value);
            }
        }

        // Mask phone numbers
        if (preg_match(self::PII_PATTERNS['phone'], $value)) {
            $value = preg_replace(self::PII_PATTERNS['phone'], 'PHONE-***', $value);
        }

        // Increment counter if value was modified
        if ($value !== $originalValue) {
            $this->maskedCount++;
        }

        return $value;
    }

    /**
     * Get the count of masked fields (for testing)
     *
     * @return int
     */
    public function getMaskedCount(): int
    {
        return $this->maskedCount;
    }
}
