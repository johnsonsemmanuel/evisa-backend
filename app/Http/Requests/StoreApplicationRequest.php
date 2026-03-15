<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreApplicationRequest extends BaseApplicationRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return array_merge(
            $this->visaTypeRules(),
            $this->personalInfoRules(),
            $this->contactInfoRules(),
            $this->passportRules(),
            $this->dateRules(),
            [
                // Additional fields specific to application creation
                'duration_days' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:365'
                ],
                'purpose_of_visit' => [
                    'required',
                    'string',
                    'max:100',
                    Rule::in(['Tourism', 'Business', 'Study', 'Medical', 'Transit', 'Diplomatic', 'Other'])
                ],
                'visa_channel' => [
                    'sometimes',
                    'string',
                    Rule::in(['e-visa', 'regular'])
                ],
                'accommodation_address' => [
                    'sometimes',
                    'string',
                    'max:500'
                ],
                'contact_in_ghana' => [
                    'sometimes',
                    'string',
                    'max:500'
                ],
            ]
        );
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'duration_days.min' => 'Duration must be at least 1 day.',
            'duration_days.max' => 'Duration cannot exceed 365 days.',
            'purpose_of_visit.in' => 'Please select a valid purpose of visit.',
        ]);
    }
}
