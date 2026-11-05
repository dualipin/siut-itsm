<?php

namespace App\Http\Requests;

use App\Services\LoanDocumentAIService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadLoanEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.(LoanDocumentAIService::MAX_FILE_SIZE_BYTES / 1024),
                'mimes:'.implode(',', LoanDocumentAIService::ALLOWED_EXTENSIONS),
            ],
            'source_type' => ['required', Rule::in(['nomina', 'benefit'])],
            'benefit_id' => [
                'nullable',
                Rule::requiredIf($this->input('source_type') === 'benefit'),
                Rule::exists('benefits', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'loan_id' => ['nullable', 'integer', 'exists:loans,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Debe adjuntar un archivo de evidencia.',
            'file.max' => 'El archivo no puede exceder los 5 MB.',
            'file.mimes' => 'Solo se admiten archivos en formato PDF, PNG, JPG, JPEG o WEBP.',
            'source_type.required' => 'El tipo de fuente de pago es obligatorio.',
            'source_type.in' => 'El tipo de fuente debe ser nómina o prestación.',
            'benefit_id.required' => 'Debe indicar la prestación a la que corresponde la evidencia.',
            'benefit_id.exists' => 'La prestación seleccionada no es válida.',
        ];
    }
}
