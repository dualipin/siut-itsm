<?php

namespace Database\Seeders;

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use App\Models\TransparencyDocument;
use App\Models\TransparencyRecord;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransparencyRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $admin = $users->first() ?? User::factory()->create();

        $recordsData = [
            [
                'name' => 'Informe Financiero Primer Trimestre 2025',
                'summary' => 'Detalle pormenorizado de los ingresos por cuotas gremiales y gastos operativos del primer trimestre.',
                'fiscal_year' => 2025,
                'period' => '1er Trimestre',
                'type' => TransparencyRecordType::Financiero,
                'status' => TransparencyRecordStatus::Publicado,
            ],
            [
                'name' => 'Informe Financiero Segundo Trimestre 2025',
                'summary' => 'Informe consolidado de egresos, fondos de resistencia y actividades sindicales del periodo.',
                'fiscal_year' => 2025,
                'period' => '2do Trimestre',
                'type' => TransparencyRecordType::Financiero,
                'status' => TransparencyRecordStatus::Publicado,
            ],
            [
                'name' => 'Informe Financiero Tercer Trimestre 2025',
                'summary' => 'Balance general y estado de resultados del tercer trimestre de actividades.',
                'fiscal_year' => 2025,
                'period' => '3er Trimestre',
                'type' => TransparencyRecordType::Financiero,
                'status' => TransparencyRecordStatus::Publicado,
            ],
            [
                'name' => 'Presupuesto de Operación Anual 2026',
                'summary' => 'Estimación de ingresos presupuestarios y desglose de partidas para el ejercicio 2026.',
                'fiscal_year' => 2026,
                'period' => 'Anual',
                'type' => TransparencyRecordType::Financiero,
                'status' => TransparencyRecordStatus::Publicado,
            ],
            [
                'name' => 'Convenio Colectivo de Trabajo Revisión Salarial 2025-2027',
                'summary' => 'Acuerdo signado con la directiva institucional referente a incrementos y condiciones laborales.',
                'fiscal_year' => 2025,
                'period' => 'Anual',
                'type' => TransparencyRecordType::Convenio,
                'status' => TransparencyRecordStatus::Publicado,
            ],
            [
                'name' => 'Estatuto Sindical Vigente',
                'summary' => 'Normativa interna que rige el funcionamiento, derechos y obligaciones de los agremiados.',
                'fiscal_year' => 2024,
                'period' => 'Anual',
                'type' => TransparencyRecordType::Normativo,
                'status' => TransparencyRecordStatus::Publicado,
            ],
            [
                'name' => 'Acta de Asamblea General Ordinaria Diciembre 2025',
                'summary' => 'Resolutivos y acuerdos tomados durante la asamblea ordinaria de fin de año.',
                'fiscal_year' => 2025,
                'period' => '4to Trimestre',
                'type' => TransparencyRecordType::Acta,
                'status' => TransparencyRecordStatus::Publicado,
            ],
            [
                'name' => 'Informe Financiero Cuarto Trimestre 2025 (En Revisión)',
                'summary' => 'Borrador de balance y arqueo de caja pendiente de dictamen contable.',
                'fiscal_year' => 2025,
                'period' => '4to Trimestre',
                'type' => TransparencyRecordType::Financiero,
                'status' => TransparencyRecordStatus::Revision,
            ],
        ];

        foreach ($recordsData as $data) {
            $record = TransparencyRecord::query()->create([
                ...$data,
                'created_by' => $admin->id,
            ]);

            // Generar 1 a 3 documentos por registro
            $count = fake()->numberBetween(1, 3);
            for ($i = 0; $i < $count; $i++) {
                $extension = fake()->randomElement(['pdf', 'xlsx', 'docx']);
                $docName = fake()->words(3, true);

                $document = TransparencyDocument::query()->create([
                    'transparency_record_id' => $record->id,
                    'name' => $docName,
                    'published_at' => now()->subDays(fake()->numberBetween(1, 30)),
                    'is_public' => $record->status === TransparencyRecordStatus::Publicado,
                    'uploaded_by' => $admin->id,
                ]);

                $document->addMediaFromString('Contenido de prueba')
                    ->setName($docName)
                    ->setFileName(fake()->word().'.'.$extension)
                    ->toMediaCollection('file');
            }
        }

        // Generar registros adicionales variados vía factory
        TransparencyRecord::factory()
            ->count(12)
            ->withDocuments(fake()->numberBetween(1, 2))
            ->create([
                'created_by' => $admin->id,
            ]);
    }
}
