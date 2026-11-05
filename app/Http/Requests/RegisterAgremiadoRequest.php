<?php

namespace App\Http\Requests;

use App\Rules\UniqueCurp;
use App\Services\RegistrationStaging;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterAgremiadoRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'surnames' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['required', 'date', 'before_or_equal:-18 years'],
            'curp' => ['required', 'string', 'size:18', 'regex:/^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z\d]\d$/i', new UniqueCurp],
            'nss' => ['nullable', 'string', 'max:20', 'unique:users,nss'],
            'category' => ['nullable', 'string', 'max:255'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'address' => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'ine_token' => ['required', 'string'],
            'nomina_token' => ['required', 'string'],
            'photo_token' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'surnames.required' => 'Los apellidos son obligatorios.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no es válido.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'birth_date.required' => 'La fecha de nacimiento es obligatoria.',
            'birth_date.before_or_equal' => 'Debes ser mayor de 18 años para solicitar tu alta sindical.',
            'curp.required' => 'La CURP es obligatoria.',
            'curp.size' => 'La CURP debe tener exactamente 18 caracteres.',
            'curp.regex' => 'El formato de la CURP no es válido. Verifica que tenga 18 caracteres correctos.',
            'nss.unique' => 'Este número de seguridad social (NSS) ya está registrado.',
            'salary.numeric' => 'El salario debe ser un valor numérico.',
            'salary.min' => 'El salario no puede ser negativo.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'ine_token.required' => 'Debes subir tu identificación INE.',
            'nomina_token.required' => 'Debes subir tu comprobante de pago.',
            'photo_token.required' => 'La fotografía de perfil es obligatoria para tu credencial digital.',
        ];
    }

    /**
     * Configure the validator instance with deterministic checks.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var RegistrationStaging $staging */
            $staging = app(RegistrationStaging::class);

            $ineToken = (string) $this->input('ine_token', '');
            $nominaToken = (string) $this->input('nomina_token', '');
            $photoToken = (string) $this->input('photo_token', '');

            $ine = $staging->resolve($ineToken, 'ine');
            if (! $ine) {
                $validator->errors()->add('ine_token', 'El documento INE no es válido o ha expirado. Por favor súbelo nuevamente.');
            }

            $nomina = $staging->resolve($nominaToken, 'nomina');
            if (! $nomina) {
                $validator->errors()->add('nomina_token', 'El comprobante de pago no es válido o ha expirado. Por favor súbelo nuevamente.');
            }

            $photo = $staging->resolve($photoToken, 'photo');
            if (! $photo) {
                $validator->errors()->add('photo_token', 'La fotografía de perfil no es válida o ha expirado. Por favor súbela nuevamente.');
            }

            // Deterministic: birth date embedded in CURP must match the birth_date input
            $curp = mb_strtoupper(trim((string) $this->input('curp', '')));
            $birthDate = $this->input('birth_date');

            if (filled($curp) && filled($birthDate) && strlen($curp) === 18) {
                try {
                    $curpDob = substr($curp, 4, 6);
                    $formDob = Carbon::parse($birthDate)->format('ymd');

                    if ($curpDob !== $formDob) {
                        $validator->errors()->add('birth_date', 'La fecha de nacimiento no coincide con la CURP proporcionada.');
                    }
                } catch (\Exception) {
                    // Non-parseable date already caught by rule 'date'
                }
            }

            // Deterministic: INE vigencia check from AI preview (server session)
            if ($ine) {
                $vigencia = data_get($ine, 'ai.extracted_fields.vigencia', '');
                if (filled($vigencia) && is_numeric($vigencia) && (int) $vigencia < now()->year) {
                    $validator->errors()->add('ine_token', 'La INE parece estar vencida (vigencia: '.$vigencia.'). Sube una credencial vigente.');
                }
            }

            // Deterministic: payslip date check from AI preview (server session)
            if ($nomina) {
                $fechaPago = data_get($nomina, 'ai.extracted_fields.fecha_pago', '');
                if (filled($fechaPago)) {
                    try {
                        $payDate = Carbon::parse($fechaPago);
                        if ($payDate->diffInDays(now()) > 31) {
                            $validator->errors()->add('nomina_token', 'El comprobante de pago tiene más de 31 días de antigüedad. Sube un comprobante del mes en curso.');
                        }
                    } catch (\Exception) {
                        // Unparseable: let human review
                    }
                }
            }
        });
    }
}
