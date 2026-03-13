<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionCountryMapping extends Model
{
    protected $table = 'mission_country_mappings';

    protected $fillable = [
        'mfa_mission_id',
        'country_code',
        'country_name',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(MfaMission::class, 'mfa_mission_id');
    }
}
