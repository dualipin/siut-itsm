<?php

namespace App\Http\Requests;

use App\Services\PetitionRequestService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StorePetitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'curp' => is_string($this->input('curp'))
                ? $this->normalizeCurp($this->input('curp'))
                : null,
            'agremiado_name' => is_string($this->input('agremiado_name'))
                ? trim($this->input('agremiado_name'))
                : null,
        ], fn (mixed $value): bool => $value !== null));
    }

    private function normalizeCurp(string $curp): string
    {
        $curp = trim($curp);
        $curp = mb_strtoupper($curp, 'UTF-8');
        $curp = str_replace('Ñ', 'X', $curp);
        $curp = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $curp);
        $curp = preg_replace('/[^A-Z0-9]/', '', $curp);

        return $curp;
    }

    public function rules(): array
    {
        return [
            'curp' => ['required', 'string', 'size:18', 'regex:/^[A-Z]{4}[0-9]{6}[HM][A-Z0-9]{7}$/'],
            'agremiado_name' => ['nullable', 'string', 'max:255'],
            'proposals' => ['required', 'array', 'min:1', 'max:'.PetitionRequestService::MAX_PROPOSALS_PER_MEMBER],
            'proposals.*.proposal' => ['required', 'string', 'min:10', 'max:5000'],
            'proposals.*.file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'curp.required' => 'La CURP es obligatoria.',
            'curp.size' => 'La CURP debe tener exactamente 18 caracteres.',
            'curp.regex' => 'La CURP no tiene un formato válido.',
            'agremiado_name.max' => 'El nombre no debe exceder :max caracteres.',
            'proposals.required' => 'Debe registrar al menos una propuesta.',
            'proposals.max' => 'No puede registrar más de :max propuestas por envío.',
            'proposals.*.proposal.required' => 'La descripción de la propuesta es obligatoria.',
            'proposals.*.proposal.min' => 'La descripción de la propuesta debe tener al menos :min caracteres.',
            'proposals.*.file.mimes' => 'El adjunto debe ser un archivo PDF, JPG, JPEG o PNG.',
            'proposals.*.file.max' => 'El adjunto no debe exceder :max kilobytes.',
        ];
    }

    public function memberName(): ?string
    {
        $name = $this->validated('agremiado_name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function proposals(): array
    {
        /** @var array<int, array{proposal: string, file: UploadedFile|null}> $proposals */
        $proposals = $this->validated()['proposals'];

        return array_map(
            fn (array $proposal): array => [
                'proposal' => $proposal['proposal'],
                'file' => $proposal['file'] ?? null,
            ],
            $proposals
        );
    }
}
