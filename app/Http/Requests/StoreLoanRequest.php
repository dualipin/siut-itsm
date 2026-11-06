<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active;
    }

    public function rules(): array
    {
        $paymentType = (string) $this->input('payment_type');
        $hasNomina = in_array($paymentType, ['nomina', 'mixed'], true);
        $hasBenefits = in_array($paymentType, ['benefit', 'mixed'], true);

        $rules = [
            'payment_type' => ['required', Rule::in(['nomina', 'benefit', 'mixed'])],
            'requested_amount' => ['required', 'numeric', 'min:100'],
            'salary_commitment_amount' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['required', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:50', 'required_without:interbank_clabe'],
            'interbank_clabe' => ['nullable', 'string', 'max:18', 'regex:/^\d{1,18}$/', 'required_without:bank_account'],
            'signature_data' => ['nullable', 'string'],

            // Nómina
            'nomina_amount' => [$hasNomina ? 'required' : 'nullable', 'numeric', 'min:1'],
            'nomina_fortnights' => [$hasNomina ? 'required' : 'nullable', 'integer', 'min:1', 'max:24'],

            // Benefits
            'benefits' => [$hasBenefits ? 'required' : 'nullable', 'array', $hasBenefits ? 'min:1' : 'nullable'],
            'benefits.*.benefit_id' => [
                $hasBenefits ? 'required' : 'nullable',
                Rule::exists('benefits', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'benefits.*.amount' => [$hasBenefits ? 'required' : 'nullable', 'numeric', 'min:1'],
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'payment_type.required' => 'Debe seleccionar una forma de pago válida.',
            'payment_type.in' => 'La forma de pago no es válida.',
            'requested_amount.required' => 'El monto solicitado es obligatorio.',
            'requested_amount.min' => 'El monto solicitado debe ser de al menos $100.00.',
            'nomina_amount.required' => 'El monto asignado a nómina es obligatorio.',
            'nomina_fortnights.required' => 'El número de quincenas es obligatorio.',
            'benefits.required' => 'Debe seleccionar al menos una prestación para este tipo de pago.',
            'benefits.*.benefit_id.exists' => 'La prestación seleccionada no es válida o no está activa.',
            'benefits.*.amount.required' => 'El monto por prestación es obligatorio.',
            'bank_name.required' => 'El banco es obligatorio.',
            'bank_account.required_without' => 'Debes capturar la cuenta o la CLABE interbancaria.',
            'interbank_clabe.required_without' => 'Debes capturar la cuenta o la CLABE interbancaria.',
            'interbank_clabe.max' => 'La CLABE interbancaria no puede tener más de 18 dígitos.',
            'interbank_clabe.regex' => 'La CLABE interbancaria solo debe contener dígitos.',
            'interbank_clabe.size' => 'La CLABE interbancaria debe tener exactamente 18 dígitos.',
        ];
    }
}
