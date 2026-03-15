<?php

namespace App\Http\Resources\GIS;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'applicant_name' => $this->first_name . ' ' . $this->last_name,
            'nationality' => $this->nationality,
            'visa_type' => [
                'id' => $this->visaType->id,
                'name' => $this->visaType->name,
            ],
            'status' => $this->status,
            'current_queue' => $this->current_queue,
            'tier' => $this->tier,
            'sla_deadline' => $this->sla_deadline,
            'is_within_sla' => $this->isWithinSla(),
            'assigned_officer' => $this->assignedOfficer ? [
                'id' => $this->assignedOfficer->id,
                'name' => $this->assignedOfficer->first_name . ' ' . $this->assignedOfficer->last_name,
            ] : null,
            'created_at' => $this->created_at,
        ];
    }
}
