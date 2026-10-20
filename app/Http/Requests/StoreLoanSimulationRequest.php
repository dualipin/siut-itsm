<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanSimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $paymentType = $this->input('payment_type');
        $hasNomina = in_array($paymentType, ['nomina', 'mixed'], true);
        $hasBenefits = in_array($paymentType, ['benefit', 'mixed'], true);

        $novemberThirty = now()->format('Y').'-11-30';
        $minFirstFortnight = now()->addDays(20)->format('Y-m-d');

        return [
            'monthly_rate' => ['required', Rule::in([6, 7.5, 8, 9.5])],
            'payment_type' => ['required', Rule::in(['nomina', 'benefit', 'mixed'])],

            // Nómina fields
            'nomina' => [$hasNomina ? 'required' : 'nullable', 'array'],
            'nomina.amount' => [$hasNomina ? 'required' : 'nullable', 'numeric', 'min:1'],
            'nomina.fortnights' => [$hasNomina ? 'required' : 'nullable', 'integer', 'min:1'],
            'nomina.first_fortnight' => [
                $hasNomina ? 'required' : 'nullable',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:'.$minFirstFortnight,
                'before_or_equal:'.$novemberThirty,
            ],

            // Benefits fields
            'benefits' => array_filter([$hasBenefits ? 'required' : 'nullable', 'array', $hasBenefits ? 'min:1' : null]),
            'benefits.*.benefit_id' => [
                $hasBenefits ? 'required' : 'nullable',
                'integer',
                Rule::exists('benefits', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'benefits.*.amount' => [$hasBenefits ? 'required' : 'nullable', 'numeric', 'min:1'],
            'benefits.*.collection_date' => [$hasBenefits ? 'required' : 'nullable', 'date', 'date_format:Y-m-d', 'after:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monthly_rate.required' => 'La tasa de interés mensual es obligatoria.',
            'monthly_rate.in' => 'La tasa seleccionada no es válida. Opciones: 6, 7.5, 8 o 9.5.',
            'payment_type.required' => 'Debe seleccionar una forma de pago.',
            'payment_type.in' => 'La forma de pago seleccionada no es válida.',
            'nomina.required' => 'Los datos de nómina son obligatorios para este tipo de pago.',
            'nomina.amount.required' => 'El monto de nómina es obligatorio.',
            'nomina.amount.numeric' => 'El monto de nómina debe ser un número.',
            'nomina.amount.min' => 'El monto de nómina debe ser mayor a cero.',
            'nomina.fortnights.required' => 'El número de quincenas es obligatorio.',
            'nomina.fortnights.integer' => 'El número de quincenas debe ser un entero.',
            'nomina.fortnights.min' => 'Debe seleccionar al menos una quincena.',
            'nomina.first_fortnight.required' => 'La primera quincena es obligatoria.',
            'nomina.first_fortnight.date' => 'La primera quincena debe ser una fecha válida.',
            'nomina.first_fortnight.after_or_equal' => 'La primera quincena debe ser al menos 20 días a partir de hoy.',
            'nomina.first_fortnight.before_or_equal' => 'La última fecha válida para nómina es el 30 de noviembre del año en curso.',
            'benefits.required' => 'Debe seleccionar al menos una prestación.',
            'benefits.*.benefit_id.required' => 'El ID de la prestación es obligatorio.',
            'benefits.*.benefit_id.exists' => 'La prestación seleccionada no existe o no está activa.',
            'benefits.*.amount.required' => 'El monto por prestación es obligatorio.',
            'benefits.*.amount.min' => 'El monto por prestación debe ser mayor a cero.',
            'benefits.*.collection_date.required' => 'La fecha de cobro de la prestación es obligatoria.',
            'benefits.*.collection_date.after' => 'La fecha de cobro de la prestación debe ser posterior a hoy.',
        ];
    }
}
