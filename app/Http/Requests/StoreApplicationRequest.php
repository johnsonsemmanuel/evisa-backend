<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreApplicationRequest extends BaseApiRequest
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
        return [
            'visa_type_id' => [
                'required',
                'integer',
                'exists:visa_types,id,is_active,1'
            ],
            'first_name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[a-zA-Z\s\-\'\.]+$/'
            ],
            'last_name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[a-zA-Z\s\-\'\.]+$/'
            ],
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
            'passport_number' => [
                'required',
                'string',
                'min:6',
                'max:20',
                'regex:/^[A-Z0-9\s\-]+$/'
            ],
            'nationality' => [
                'required',
                'string',
                'max:100',
                'exists:countries,name'
            ],
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
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'first_name.regex' => 'First name can only contain letters, spaces, hyphens, dots, and apostrophes.',
            'last_name.regex' => 'Last name can only contain letters, spaces, hyphens, dots, and apostrophes.',
            'email.regex' => 'Please provide a valid email address.',
            'phone.regex' => 'Phone number can only contain numbers, spaces, plus sign, hyphens, and parentheses.',
            'passport_number.regex' => 'Passport number can only contain uppercase letters, numbers, spaces, and hyphens.',
            'date_of_birth.before' => 'Date of birth must be before today.',
            'date_of_birth.after' => 'Date of birth cannot be more than 120 years ago.',
            'intended_arrival.after' => 'Intended arrival date must be after today.',
            'intended_arrival.before' => 'Intended arrival date cannot be more than 1 year from now.',
            'nationality.exists' => 'The selected nationality is not valid.',
            'visa_type_id.exists' => 'The selected visa type is not available.',
        ]);
    }
}
