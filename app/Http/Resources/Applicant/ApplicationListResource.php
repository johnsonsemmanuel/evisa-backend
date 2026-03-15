<?php

namespace App\Http\Resources\Applicant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'visa_type' => [
                'id' => $this->visaType->id,
                'name' => $this->visaType->name,
            ],
            'status' => $this->status,
            'payment_status' => $this->payment?->status,
            'arrival_date' => $this->arrival_date,
            'created_at' => $this->created_at,
            'submitted_at' => $this->submitted_at,
        ];
    }
}
