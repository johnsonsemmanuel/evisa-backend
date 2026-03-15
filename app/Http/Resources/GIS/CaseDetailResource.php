<?php

namespace App\Http\Resources\GIS;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'applicant' => [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email,
                'phone' => $this->phone,
                'nationality' => $this->nationality,
                'date_of_birth' => $this->date_of_birth,
                'passport_number' => $this->passport_number,
            ],
            'visa_type' => [
                'id' => $this->visaType->id,
                'name' => $this->visaType->name,
                'processing_time_days' => $this->visaType->processing_time_days,
            ],
            'status' => $this->status,
            'current_queue' => $this->current_queue,
            'tier' => $this->tier,
            'travel_dates' => [
                'arrival_date' => $this->arrival_date,
                'departure_date' => $this->departure_date,
                'purpose_of_visit' => $this->purpose_of_visit,
            ],
            'documents' => $this->documents->map(fn($doc) => [
                'id' => $doc->id,
                'document_type' => $doc->document_type,
                'verification_status' => $doc->verification_status,
                'uploaded_at' => $doc->created_at,
            ]),
            'payment' => $this->payment ? [
                'id' => $this->payment->id,
                'amount' => $this->payment->amount,
                'status' => $this->payment->status,
                'gateway' => $this->payment->gateway,
                'paid_at' => $this->payment->paid_at,
            ] : null,
            'risk_assessment' => $this->riskAssessment ? [
                'risk_score' => $this->riskAssessment->risk_score,
                'risk_level' => $this->riskAssessment->risk_level,
                'risk_reasons' => $this->riskAssessment->risk_reasons,
            ] : null,
            'sla' => [
                'deadline' => $this->sla_deadline,
                'hours_remaining' => $this->slaHoursRemaining(),
                'is_within_sla' => $this->isWithinSla(),
            ],
            'officers' => [
                'assigned' => $this->assignedOfficer ? [
                    'id' => $this->assignedOfficer->id,
                    'name' => $this->assignedOfficer->first_name . ' ' . $this->assignedOfficer->last_name,
                ] : null,
                'reviewing' => $this->reviewingOfficer ? [
                    'id' => $this->reviewingOfficer->id,
                    'name' => $this->reviewingOfficer->first_name . ' ' . $this->reviewingOfficer->last_name,
                ] : null,
                'approval' => $this->approvalOfficer ? [
                    'id' => $this->approvalOfficer->id,
                    'name' => $this->approvalOfficer->first_name . ' ' . $this->approvalOfficer->last_name,
                ] : null,
            ],
            'timestamps' => [
                'created_at' => $this->created_at,
                'submitted_at' => $this->submitted_at,
                'decided_at' => $this->decided_at,
            ],
        ];
    }
}
