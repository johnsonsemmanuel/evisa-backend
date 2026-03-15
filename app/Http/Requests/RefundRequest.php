<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefundRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by middleware and gates
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_id' => [
                'required',
                'integer',
                'exists:payments,id',
                function ($attribute, $value, $fail) {
                    $payment = Payment::find($value);
                    
                    if (!$payment) {
                        $fail('The selected payment does not exist.');
                        return;
                    }
                    
                    if ($payment->status !== 'paid') {
                        $fail('Only paid payments can be refunded. Current status: ' . $payment->status);
                    }
                },
            ],
            'reason' => [
                'required',
                'string',
                'min:20',
                'max:1000',
            ],
            'amount' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    $paymentId = $this->input('payment_id');
                    if (!$paymentId) {
                        return; // Will be caught by payment_id validation
                    }
                    
                    $payment = Payment::find($paymentId);
                    if (!$payment) {
                        return; // Will be caught by payment_id validation
                    }
                    
                    if ($value > $payment->amount) {
                        $fail('Refund amount cannot exceed the original payment amount of ' . 
                              number_format($payment->amount / 100, 2) . ' GHS.');
                    }
                },
            ],
            'attachments' => [
                'nullable',
                'array',
                'max:5', // Maximum 5 attachments
            ],
            'attachments.*' => [
                'integer',
                // In production, you'd validate these are valid file IDs
                // 'exists:files,id',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'payment_id.required' => 'Payment ID is required.',
            'payment_id.exists' => 'The selected payment does not exist.',
            'reason.required' => 'A refund reason is required for audit purposes.',
            'reason.min' => 'The refund reason must be at least 20 characters to ensure proper documentation.',
            'amount.required' => 'Refund amount is required.',
            'amount.integer' => 'Refund amount must be in pesewas (integer).',
            'amount.min' => 'Refund amount must be at least 1 pesewa.',
            'attachments.array' => 'Attachments must be an array of file IDs.',
            'attachments.max' => 'You can attach a maximum of 5 files.',
        ];
    }

    /**
     * Get the payment being refunded.
     */
    public function getPayment(): ?Payment
    {
        return Payment::find($this->input('payment_id'));
    }
}
