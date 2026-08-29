<?php

namespace App\Filament\Resources\Themes\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Slider;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ThemeForm
{
    public static function configure(Schema $schema): Schema
    {
        $others = [
            ['name' => 'border_width', 'label' => 'Ancho del Borde'],
            ['name' => 'radius_box', 'label' => 'Radio del Borde de los Contenedores'],
            ['name' => 'radius_selector', 'label' => 'Radio del Borde de los Selectores'],
            ['name' => 'radius_field', 'label' => 'Radio del Borde de los Campos de Entrada'],
            ['name' => 'size_field', 'label' => 'Tamaño de los Campos de Entrada'],
            ['name' => 'size_selector', 'label' => 'Tamaño de los Selectores'],
        ];

        return $schema
            ->components([
                ColorPicker::make('color_primary')->label('Color Primario'),
                ColorPicker::make('color_secondary')->label('Color Secundario'),
                ColorPicker::make('color_neutral')->label('Color Neutro'),
                ColorPicker::make('color_accent')->label('Color Acento'),
                ColorPicker::make('color_success')->label('Color Exitoso'),
                ColorPicker::make('color_warning')->label('Color Advertencia'),
                ColorPicker::make('color_info')->label('Color Información'),
                ColorPicker::make('color_error')->label('Color Error'),

                Section::make('Otros')->description('Configuraciones adicionales del tema (Solo se aplican en la apariencia de la interfaz de usuario de Inicio)')
                    ->inlineLabel()
                    ->schema(
                        array_map(fn ($item) => Slider::make($item['name'])->label($item['label'])->minValue(0)->maxValue(5)->step(0.1), $others)
                    ),
            ]);
    }
}
