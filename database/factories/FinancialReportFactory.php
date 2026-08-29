<?php

namespace Database\Factories;

use App\Models\FinancialReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialReport>
 */
class FinancialReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year' => fake()->unique()->numberBetween(2000, (int) now()->year),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Configure the factory to attach a document to the financial_reports collection.
     */
    public function withDocument(string $filename = 'reporte-financiero.pdf'): static
    {
        return $this->afterCreating(function (FinancialReport $record) use ($filename) {
            $record->addMediaFromString('Contenido de prueba de reporte financiero')
                ->setName(pathinfo($filename, PATHINFO_FILENAME))
                ->setFileName($filename)
                ->toMediaCollection('financial_reports');
        });
    }
}
