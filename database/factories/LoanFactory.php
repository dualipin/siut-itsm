<?php

namespace Database\Factories;

use App\Enums\LoanPaymentType;
use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        return [
            'folio' => Loan::generateFolio(),
            'user_id' => User::factory(),
            'status' => LoanStatus::Borrador,
            'payment_type' => LoanPaymentType::Nomina,
            'requested_amount' => 10000.00,
            'monthly_rate' => 6.00,
            'salary_commitment_amount' => 1500.00,
            'nomina_amount' => 10000.00,
            'nomina_fortnights' => 4,
            'first_fortnight_date' => now()->addDays(25)->setDay(15)->format('Y-m-d'),
            'bank_name' => 'BBVA',
            'bank_account' => '1234567890',
            'interbank_clabe' => '012180001234567890',
            'documents_generated' => false,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LoanStatus::Enviada,
            'submitted_at' => now(),
            'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'signature_type' => 'drawn',
            'signature_hash' => 'dummy_hash',
            'signed_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->submitted()->state(fn (array $attributes) => [
            'status' => LoanStatus::Aprobada,
            'approved_amount' => $attributes['requested_amount'] ?? 10000.00,
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
            'documents_generated' => true,
        ]);
    }

    public function disbursed(): static
    {
        return $this->approved()->state(fn (array $attributes) => [
            'status' => LoanStatus::Desembolsada,
            'disbursed_at' => now()->format('Y-m-d'),
            'disbursed_amount' => $attributes['approved_amount'] ?? 10000.00,
            'disbursement_method' => 'transferencia',
            'disbursement_reference' => 'SPEI-TEST-1234',
            'disbursed_by' => User::factory()->admin(),
        ]);
    }
}
