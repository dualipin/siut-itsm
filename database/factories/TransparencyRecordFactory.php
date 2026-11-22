<?php

namespace Database\Factories;

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use App\Models\TransparencyRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;

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
        $type = fake()->randomElement(TransparencyRecordType::cases());
        $documentDate = $type->usesDocumentDate()
            ? fake()->dateTimeBetween('2023-01-01', '2026-12-31')
            : null;
        $fiscalYear = $documentDate !== null
            ? (int) $documentDate->format('Y')
            : fake()->numberBetween(2023, 2026);

        return [
            'name' => fake()->randomElement([
                'Informe Financiero de Ingresos y Egresos',
                'Presupuesto de Operación Anual',
                'Oficio de Gestión Sindical',
                'Acta de Asamblea General Extraordinaria',
                'Convenio de Colaboración Institucional',
                'Reglamento Interior de Trabajo',
            ]).' '.fake()->numberBetween(2023, 2026),
            'summary' => fake()->paragraph(),
            'fiscal_year' => $fiscalYear,
            'period' => $type->usesPeriod()
                ? fake()->randomElement(['1er Trimestre', '2do Trimestre', '3er Trimestre', '4to Trimestre', 'Anual'])
                : null,
            'document_date' => $documentDate?->format('Y-m-d'),
            'reference_number' => $type->usesFolio()
                ? fake()->optional(0.5)->numerify('SIUT-###/'.fake()->unique()->numberBetween(1000, 9999))
                : null,
            'observations' => fake()->optional(0.4)->sentence(),
            'type' => $type,
            'status' => TransparencyRecordStatus::Interno,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the record is publicly downloadable.
     */
    public function publico(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransparencyRecordStatus::Publico,
        ]);
    }

    /**
     * Indicate that the record is internal consultation only.
     */
    public function interno(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransparencyRecordStatus::Interno,
        ]);
    }

    /**
     * Configure the factory to attach media documents directly to the record.
     */
    public function withDocuments(int $count = 3): static
    {
        return $this->afterCreating(function (TransparencyRecord $record) use ($count) {
            for ($i = 0; $i < $count; $i++) {
                $fileName = fake()->unique()->word().'.pdf';
                $file = UploadedFile::fake()->createWithContent($fileName, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

                $record->addMedia($file)
                    ->setName(fake()->word())
                    ->setFileName($fileName)
                    ->toMediaCollection(TransparencyRecord::DOCUMENTS_COLLECTION);
            }
        });
    }
}
