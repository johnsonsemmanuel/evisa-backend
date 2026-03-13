<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Watchlist extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'list_type',
        'first_name_encrypted',
        'last_name_encrypted',
        'date_of_birth',
        'nationality',
        'passport_number_encrypted',
        'id_number_encrypted',
        'reason',
        'source',
        'source_reference',
        'severity',
        'effective_from',
        'effective_until',
        'is_active',
        'added_by_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'first_name_encrypted',
        'last_name_encrypted',
        'passport_number_encrypted',
        'id_number_encrypted',
    ];

    protected $appends = [
        'first_name',
        'last_name',
        'passport_number_masked',
    ];

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function getFirstNameAttribute(): ?string
    {
        return $this->first_name_encrypted ? Crypt::decryptString($this->first_name_encrypted) : null;
    }

    public function getLastNameAttribute(): ?string
    {
        return $this->last_name_encrypted ? Crypt::decryptString($this->last_name_encrypted) : null;
    }

    public function getPassportNumberMaskedAttribute(): ?string
    {
        if (!$this->passport_number_encrypted) {
            return null;
        }
        $passport = Crypt::decryptString($this->passport_number_encrypted);
        return substr($passport, 0, 3) . '****' . substr($passport, -2);
    }

    public function getPassportNumberAttribute(): ?string
    {
        return $this->passport_number_encrypted ? Crypt::decryptString($this->passport_number_encrypted) : null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            });
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('list_type', $type);
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public static function checkMatch(string $firstName, string $lastName, ?string $passportNumber = null, ?string $nationality = null, ?\DateTime $dob = null): array
    {
        $matches = [];

        $query = self::active();

        // Get all active watchlist entries and check against encrypted values
        $entries = $query->get();

        foreach ($entries as $entry) {
            $score = 0;
            $matchedFields = [];

            // Check name match (fuzzy)
            $entryFirstName = strtolower($entry->first_name ?? '');
            $entryLastName = strtolower($entry->last_name ?? '');
            $checkFirstName = strtolower($firstName);
            $checkLastName = strtolower($lastName);

            if ($entryFirstName === $checkFirstName && $entryLastName === $checkLastName) {
                $score += 50;
                $matchedFields[] = 'full_name';
            } elseif ($entryLastName === $checkLastName) {
                $score += 25;
                $matchedFields[] = 'last_name';
            }

            // Check passport match
            if ($passportNumber && $entry->passport_number_encrypted) {
                $entryPassport = strtoupper($entry->passport_number);
                if ($entryPassport === strtoupper($passportNumber)) {
                    $score += 40;
                    $matchedFields[] = 'passport_number';
                }
            }

            // Check nationality match
            if ($nationality && $entry->nationality === strtoupper($nationality)) {
                $score += 5;
                $matchedFields[] = 'nationality';
            }

            // Check DOB match
            if ($dob && $entry->date_of_birth && $entry->date_of_birth->format('Y-m-d') === $dob->format('Y-m-d')) {
                $score += 10;
                $matchedFields[] = 'date_of_birth';
            }

            // Only consider it a match if score is significant
            if ($score >= 50) {
                $matches[] = [
                    'watchlist_id' => $entry->id,
                    'list_type' => $entry->list_type,
                    'severity' => $entry->severity,
                    'match_score' => $score,
                    'matched_fields' => $matchedFields,
                    'reason' => $entry->reason,
                    'source' => $entry->source,
                ];
            }
        }

        return $matches;
    }
}
