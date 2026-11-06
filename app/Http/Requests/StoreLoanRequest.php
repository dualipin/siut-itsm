<?php

namespace App\Http\Requests;

use App\Services\LoanCalendarService;
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
        $calendar = app(LoanCalendarService::class);
        $paymentType = (string) $this->input('payment_type');
        $hasNomina = in_array($paymentType, ['nomina', 'mixed'], true);
        $hasBenefits = in_array($paymentType, ['benefit', 'mixed'], true);

        $rules = [
            'payment_type' => ['required', Rule::in(['nomina', 'benefit', 'mixed'])],
            'requested_amount' => ['required', 'numeric', 'min:100'],
            'monthly_rate' => ['nullable', 'numeric', 'min:1', 'max:50'],
            'salary_commitment_amount' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:50'],
            'interbank_clabe' => ['nullable', 'string', 'size:18'],
            'signature_data' => ['nullable', 'string'],

            // Nómina
            'nomina_amount' => [$hasNomina ? 'required' : 'nullable', 'numeric', 'min:1'],
            'nomina_fortnights' => [$hasNomina ? 'required' : 'nullable', 'integer', 'min:1', 'max:24'],
            'first_fortnight_date' => [
                $hasNomina ? 'required' : 'nullable',
                'date',
                'date_format:Y-m-d',
            ],

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
            'monthly_rate.numeric' => 'La tasa de interés debe ser un valor numérico.',
            'nomina_amount.required' => 'El monto asignado a nómina es obligatorio.',
            'nomina_fortnights.required' => 'El número de quincenas es obligatorio.',
            'first_fortnight_date.required' => 'La fecha de la primera quincena es obligatoria.',
            'benefits.required' => 'Debe seleccionar al menos una prestación para este tipo de pago.',
            'benefits.*.benefit_id.exists' => 'La prestación seleccionada no es válida o no está activa.',
            'benefits.*.amount.required' => 'El monto por prestación es obligatorio.',
            'interbank_clabe.size' => 'La CLABE interbancaria debe tener exactamente 18 dígitos.',
        ];
    }
}
