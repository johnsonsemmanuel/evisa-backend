<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class BorderCrossing extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'eta_application_id',
        'crossing_type',
        'port_of_entry',
        'passport_number_encrypted',
        'nationality',
        'traveler_name_encrypted',
        'verification_status',
        'verification_notes',
        'flight_number',
        'airline',
        'officer_id',
        'officer_badge',
        'crossed_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'crossed_at' => 'datetime',
        'crossing_type' => \App\Enums\BorderCrossingType::class,
        'verification_status' => \App\Enums\BorderVerificationStatus::class,
    ];

    protected $hidden = [
        'passport_number_encrypted',
        'traveler_name_encrypted',
    ];

    protected $appends = [
        'passport_number_masked',
        'traveler_name',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function etaApplication(): BelongsTo
    {
        return $this->belongsTo(EtaApplication::class);
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }

    public function getPassportNumberMaskedAttribute(): ?string
    {
        if (!$this->passport_number_encrypted) {
            return null;
        }
        $passport = Crypt::decryptString($this->passport_number_encrypted);
        return substr($passport, 0, 3) . '****' . substr($passport, -2);
    }

    public function getTravelerNameAttribute(): ?string
    {
        return $this->traveler_name_encrypted 
            ? Crypt::decryptString($this->traveler_name_encrypted) 
            : null;
    }

    public function scopeEntries($query)
    {
        return $query->where('crossing_type', \App\Enums\BorderCrossingType::Entry->value);
    }

    public function scopeExits($query)
    {
        return $query->where('crossing_type', \App\Enums\BorderCrossingType::Exit->value);
    }

    public function scopeAtPort($query, string $port)
    {
        return $query->where('port_of_entry', $port);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('crossed_at', today());
    }
}
