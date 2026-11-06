<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\LoanEfirmaSignature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanEfirmaSignature>
 */
class LoanEfirmaSignatureFactory extends Factory
{
    protected $model = LoanEfirmaSignature::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'loan_id' => Loan::factory(),
            'certificate' => base64_encode(fake()->sha256()),
            'certificate_serial' => '00001000000500000000',
            'rfc' => 'XAXX010101000',
            'curp' => 'XEXX010101HNEXXXA4',
            'signer_name' => fake()->name(),
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addYears(3),
            'original_chain' => '||SIUT-ITSM-PRESTAMO|v1|'.fake()->sha256().'|'.now()->utc()->format('Y-m-d\TH:i:s\Z').'||',
            'seal' => base64_encode(fake()->sha256()),
            'algorithm' => 'RSA-SHA256',
            'signed_at' => now(),
            'validated_at' => now(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
