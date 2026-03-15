<?php

namespace App\Http\Resources\Applicant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'visa_type' => [
                'id' => $this->visaType->id,
                'name' => $this->visaType->name,
                'processing_time_days' => $this->visaType->processing_time_days,
            ],
            'status' => $this->status,
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
            'status_history' => $this->statusHistory->map(fn($history) => [
                'status' => $history->status,
                'notes' => $history->notes,
                'changed_at' => $history->created_at,
            ]),
            'timestamps' => [
                'created_at' => $this->created_at,
                'submitted_at' => $this->submitted_at,
                'decided_at' => $this->decided_at,
            ],
        ];
    }
}
