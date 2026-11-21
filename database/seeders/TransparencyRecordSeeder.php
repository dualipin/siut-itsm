<?php

namespace Database\Seeders;

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use App\Models\TransparencyRecord;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

class TransparencyRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::query()->first() ?? User::factory()->create();

        $recordsData = [
            [
                'name' => 'Informe Financiero Primer Trimestre 2025',
                'summary' => 'Detalle pormenorizado de los ingresos por cuotas gremiales y gastos operativos del primer trimestre.',
                'fiscal_year' => 2025,
                'period' => '1er Trimestre',
                'document_date' => null,
                'reference_number' => null,
                'type' => TransparencyRecordType::Financiero,
                'status' => TransparencyRecordStatus::Publico,
            ],
            [
                'name' => 'Informe Financiero Segundo Trimestre 2025',
                'summary' => 'Informe consolidado de egresos, fondos de resistencia y actividades sindicales del periodo.',
                'fiscal_year' => 2025,
                'period' => '2do Trimestre',
                'document_date' => null,
                'reference_number' => null,
                'type' => TransparencyRecordType::Financiero,
                'status' => TransparencyRecordStatus::Publico,
            ],
            [
                'name' => 'Oficio de Gestión Salarial ante Dirección General',
                'summary' => 'Solicitud formal de revisión y ajuste de prestaciones económicas para el personal sindicalizado.',
                'fiscal_year' => 2025,
                'period' => null,
                'document_date' => '2025-03-18',
                'reference_number' => 'SIUT-ITSM/018/2025',
                'type' => TransparencyRecordType::Oficios,
                'status' => TransparencyRecordStatus::Publico,
            ],
            [
                'name' => 'Acta de Asamblea General Ordinaria Diciembre 2025',
                'summary' => 'Resolutivos y acuerdos tomados durante la asamblea ordinaria de fin de año.',
                'fiscal_year' => 2025,
                'period' => null,
                'document_date' => '2025-12-10',
                'reference_number' => 'ACTA-AGO/2025-04',
                'type' => TransparencyRecordType::Actas,
                'status' => TransparencyRecordStatus::Publico,
            ],
            [
                'name' => 'Convenio Colectivo de Trabajo Revisión Salarial 2025-2027',
                'summary' => 'Acuerdo signado con la directiva institucional referente a incrementos y condiciones laborales.',
                'fiscal_year' => 2025,
                'period' => null,
                'document_date' => '2025-02-01',
                'reference_number' => 'CCT-2025-2027',
                'type' => TransparencyRecordType::Legal,
                'status' => TransparencyRecordStatus::Publico,
            ],
            [
                'name' => 'Memoria Histórica y Fotográfica Institucional',
                'summary' => 'Recopilación documental de aniversarios, eventos y vida institucional.',
                'fiscal_year' => 2024,
                'period' => null,
                'document_date' => '2024-11-15',
                'reference_number' => null,
                'type' => TransparencyRecordType::Otros,
                'status' => TransparencyRecordStatus::Publico,
            ],
            [
                'name' => 'Oficio de Comisión Interna con Datos Personales',
                'summary' => 'Correspondencia interna relativa a situación laboral individual.',
                'fiscal_year' => 2025,
                'period' => null,
                'document_date' => '2025-06-05',
                'reference_number' => 'SIUT-ITSM/042/2025',
                'observations' => 'Contiene datos personales de agremiados.',
                'type' => TransparencyRecordType::Oficios,
                'status' => TransparencyRecordStatus::Interno,
            ],
        ];

        foreach ($recordsData as $data) {
            $record = TransparencyRecord::query()->create([
                ...$data,
                'created_by' => $admin->id,
            ]);

            $count = fake()->numberBetween(1, 2);
            for ($i = 0; $i < $count; $i++) {
                $extension = $record->type === TransparencyRecordType::Financiero
                    ? fake()->randomElement(['pdf', 'xlsx'])
                    : 'pdf';
                $fileName = fake()->unique()->slug(2).'.'.$extension;
                $file = UploadedFile::fake()->createWithContent($fileName, '%PDF-1.4 Contenido de prueba');

                $record->addMedia($file)
                    ->setName($record->name)
                    ->setFileName($fileName)
                    ->toMediaCollection('documents');
            }
        }

        TransparencyRecord::factory()
            ->count(8)
            ->withDocuments(1)
            ->create([
                'created_by' => $admin->id,
            ]);
    }
}
