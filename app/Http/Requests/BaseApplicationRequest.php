<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

/**
 * Base request class for application-related form requests.
 * Contains shared validation rules and error handling.
 */
class BaseApplicationRequest extends FormRequest
{
    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    /**
     * Get shared validation rules for personal information.
     */
    protected function personalInfoRules(): array
    {
        return [
            'first_name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-Z\s\-\'\.]+$/'
            ],
            'last_name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-Z\s\-\'\.]+$/'
            ],
        ];
    }

    /**
     * Get shared validation rules for contact information.
     */
    protected function contactInfoRules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                'max:255',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'
            ],
            'phone' => [
                'required',
                'string',
                'max:20',
                'regex:/^\+?[0-9\s\-\(\)]+$/'
            ],
        ];
    }

    /**
     * Get shared validation rules for passport information.
     */
    protected function passportRules(): array
    {
        return [
            'passport_number' => [
                'required',
                'string',
                'min:6',
                'max:50',
                'regex:/^[A-Z0-9\s\-]+$/i'
            ],
            'nationality' => [
                'required',
                'string',
                'size:2', // ISO 3166-1 alpha-2 country code
            ],
        ];
    }

    /**
     * Get shared validation rules for date fields.
     */
    protected function dateRules(): array
    {
        return [
            'date_of_birth' => [
                'required',
                'date',
                'before:today',
                'after:-120 years'
            ],
            'intended_arrival' => [
                'required',
                'date',
                'after:today',
                'before:+1 year'
            ],
        ];
    }

    /**
     * Get shared validation rules for visa type.
     */
    protected function visaTypeRules(): array
    {
        return [
            'visa_type_id' => [
                'required',
                'integer',
                'exists:visa_types,id'
            ],
        ];
    }

    /**
     * Get shared validation rules for application IDs (batch operations).
     */
    protected function applicationIdsBatchRules(int $min = 1, int $max = 50): array
    {
        return [
            'application_ids' => [
                'required',
                'array',
                "min:{$min}",
                "max:{$max}"
            ],
            'application_ids.*' => [
                'integer',
                'exists:applications,id'
            ],
        ];
    }

    /**
     * Get shared validation rules for notes/comments.
     */
    protected function notesRules(int $maxLength = 2000): array
    {
        return [
            'notes' => [
                'nullable',
                'string',
                "max:{$maxLength}"
            ],
        ];
    }

    /**
     * Get shared validation rules for reference numbers.
     */
    protected function referenceNumberRules(): array
    {
        return [
            'reference_number' => [
                'required',
                'string',
                'max:50'
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            // Generic messages
            'required' => 'The :attribute field is required.',
            'email' => 'Please provide a valid email address.',
            'string' => 'The :attribute must be a string.',
            'integer' => 'The :attribute must be an integer.',
            'date' => 'The :attribute must be a valid date.',
            'mimes' => 'The :attribute must be a file of type: :values.',
            'max' => 'The :attribute may not be greater than :max.',
            'min' => 'The :attribute must be at least :min.',
            'unique' => 'The :attribute has already been taken.',
            'exists' => 'The selected :attribute is invalid.',
            'in' => 'The selected :attribute is invalid.',
            'regex' => 'The :attribute format is invalid.',
            'size' => 'The :attribute must be exactly :size characters.',
            'array' => 'The :attribute must be an array.',
            
            // Personal info messages
            'first_name.regex' => 'First name can only contain letters, spaces, hyphens, dots, and apostrophes.',
            'last_name.regex' => 'Last name can only contain letters, spaces, hyphens, dots, and apostrophes.',
            
            // Contact info messages
            'email.regex' => 'Please provide a valid email address.',
            'phone.regex' => 'Phone number can only contain numbers, spaces, plus sign, hyphens, and parentheses.',
            
            // Passport messages
            'passport_number.regex' => 'Passport number can only contain letters, numbers, spaces, and hyphens.',
            'nationality.size' => 'Nationality must be a valid 2-letter country code.',
            
            // Date messages
            'date_of_birth.before' => 'Date of birth must be before today.',
            'date_of_birth.after' => 'Date of birth cannot be more than 120 years ago.',
            'intended_arrival.after' => 'Intended arrival date must be after today.',
            'intended_arrival.before' => 'Intended arrival date cannot be more than 1 year from now.',
            
            // Visa type messages
            'visa_type_id.exists' => 'The selected visa type is not available.',
            
            // Application IDs messages
            'application_ids.required' => 'At least one application ID is required.',
            'application_ids.array' => 'Application IDs must be provided as an array.',
            'application_ids.*.exists' => 'One or more application IDs are invalid.',
        ];
    }
}
