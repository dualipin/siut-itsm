<?php

namespace Database\Factories;

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use App\Models\TransparencyRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransparencyRecord>
 */
class TransparencyRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(TransparencyRecordStatus::cases());

        return [
            'name' => fake()->randomElement([
                'Informe Financiero de Ingresos y Egresos',
                'Presupuesto de Operación Anual',
                'Tabulador Salarial y Prestaciones',
                'Auditoría y Dictamen Contable',
                'Acta de Asamblea General Extraordinaria',
                'Convenio de Colaboración Institucional',
                'Reglamento Interior de Trabajo',
                'Padrón de Afiliados Actualizado',
            ]).' '.fake()->numberBetween(2023, 2026),
            'summary' => fake()->paragraph(),
            'fiscal_year' => fake()->numberBetween(2023, 2026),
            'period' => fake()->randomElement(['1er Trimestre', '2do Trimestre', '3er Trimestre', '4to Trimestre', 'Anual']),
            'observations' => fake()->optional(0.4)->sentence(),
            'type' => fake()->randomElement(TransparencyRecordType::cases()),
            'status' => $status,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the record is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransparencyRecordStatus::Publicado,
        ]);
    }

    /**
     * Indicate that the record is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransparencyRecordStatus::Borrador,
        ]);
    }

    /**
     * Configure the factory to create associated documents.
     */
    public function withDocuments(int $count = 3): static
    {
        return $this->afterCreating(function (TransparencyRecord $record) use ($count) {
            for ($i = 0; $i < $count; $i++) {
                $record->addMediaFromString('Contenido de prueba')
                    ->setName(fake()->word())
                    ->setFileName(fake()->word().'.pdf')
                    ->toMediaCollection('documents');
            }
        });
    }
}
