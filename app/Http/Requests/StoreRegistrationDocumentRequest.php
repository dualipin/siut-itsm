<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegistrationDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $kind = $this->input('kind');

        $fileRules = match ($kind) {
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'ine', 'nomina' => ['required', 'file', 'mimes:pdf,jpeg,jpg,png,webp', 'max:10240'],
            default => ['required', 'file', 'max:10240'],
        };

        return [
            'kind' => ['required', 'string', 'in:ine,nomina,photo'],
            'file' => $fileRules,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kind.required' => 'El tipo de documento es obligatorio.',
            'kind.in' => 'El tipo de documento debe ser ine, nomina o photo.',
            'file.required' => 'Debes seleccionar un archivo.',
            'file.mimes' => 'El formato del archivo no es válido. Solo se aceptan PDF, JPG, PNG o WebP.',
            'file.image' => 'La fotografía debe ser una imagen válida (JPG, PNG o WebP).',
            'file.max' => 'El archivo no debe exceder 10 MB.',
        ];
    }
}
