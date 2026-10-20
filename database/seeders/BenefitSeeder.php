<?php

namespace Database\Seeders;

use App\Models\Benefit;
use Illuminate\Database\Seeder;

class BenefitSeeder extends Seeder
{
    public function run(): void
    {
        $benefits = [
            ['name' => '5 Días Complemento al Aguinaldo', 'collection_month' => 12, 'collection_day' => 20],
            ['name' => '9 Días Otras Prestaciones de Fin de Año', 'collection_month' => 12, 'collection_day' => 20],
            ['name' => 'Aguinaldo 40 Días', 'collection_month' => 12, 'collection_day' => 20],
            ['name' => 'Ajuste al Calendario 5 Días', 'collection_month' => 12, 'collection_day' => 31],
            ['name' => 'Bono Navideño', 'collection_month' => 12, 'collection_day' => 15],
            ['name' => 'Día de la Madre', 'collection_month' => 5, 'collection_day' => 10],
            ['name' => 'Día del Maestro', 'collection_month' => 5, 'collection_day' => 15],
            ['name' => 'Día del Padre', 'collection_month' => 6, 'collection_day' => 21],
            ['name' => 'Día del Servidor Público', 'collection_month' => 6, 'collection_day' => 30],
            ['name' => 'Prima Vacacional 12 Días, 1er. Periodo', 'collection_month' => 6, 'collection_day' => 1],
            ['name' => 'Prima Vacacional 12 Días, 2do. Periodo', 'collection_month' => 12, 'collection_day' => 1],
            ['name' => 'Retroactivo', 'collection_month' => 8, 'collection_day' => 15],
        ];

        foreach ($benefits as $benefit) {
            Benefit::firstOrCreate(
                ['name' => $benefit['name']],
                [
                    'collection_month' => $benefit['collection_month'],
                    'collection_day' => $benefit['collection_day'],
                    'is_active' => true,
                ]
            );
        }
    }
}
