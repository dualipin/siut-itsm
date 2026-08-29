<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\TransparencyRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = fake()->randomElement(['pdf', 'xlsx', 'docx']);
        $mimeMap = [
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        $title = fake()->randomElement([
            'Informe_Financiero_Detallado',
            'Comprobante_Fiscal_Consolidado',
            'Anexo_Gastos_Operativos',
            'Dictamen_Auditoria_Externa',
            'Relacion_Activos_Fijos',
            'Acta_Aprobacion_Presupuesto',
        ]);

        $fileName = Str::slug($title).'_'.fake()->randomNumber(4, true).'.'.$extension;

        return [
            'record_id' => TransparencyRecord::factory(),
            'display_name' => str_replace('_', ' ', $title).' ('.strtoupper($extension).')',
            'file_path' => 'documents/transparency/'.$fileName,
            'file_name' => $fileName,
            'mime_type' => $mimeMap[$extension],
            'file_size' => fake()->numberBetween(100_000, 15_000_000),
            'owner_id' => User::factory(),
            'version' => 1.0,
            'uploaded_by' => User::factory(),
            'is_public' => true,
        ];
    }

    /**
     * Indicate that the document is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * Indicate that the document is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }

    /**
     * Indicate that the document is a PDF.
     */
    public function pdf(): static
    {
        return $this->state(function (array $attributes) {
            $fileName = Str::slug($attributes['display_name'] ?? 'documento').'_'.fake()->randomNumber(4, true).'.pdf';

            return [
                'mime_type' => 'application/pdf',
                'file_name' => $fileName,
                'file_path' => 'documents/transparency/'.$fileName,
            ];
        });
    }
}
