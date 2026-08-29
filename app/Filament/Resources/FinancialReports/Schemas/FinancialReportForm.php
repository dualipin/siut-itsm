<?php

namespace App\Filament\Resources\FinancialReports\Schemas;

use App\Filament\Resources\FinancialReports\FinancialReportResource;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FinancialReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('created_by')
                    ->default(fn () => auth()->id()),
                TextInput::make('year')
                    ->label('Año')
                    ->required()
                    ->numeric()
                    ->maxValue(now()->year)
                    ->default(now()->year)
                    ->unique(ignoreRecord: true),
                SpatieMediaLibraryFileUpload::make('file')
                    ->label('Archivo')
                    ->collection('financial_reports')
                    ->maxFiles(1)
                    ->downloadable()
                    ->openable()
                    ->columnSpanFull()
                    ->required()
                    ->getUploadedFileUsing(static function (SpatieMediaLibraryFileUpload $component, string $file): ?array {
                        if (! $component->getRecord()) {
                            return null;
                        }

                        $media = $component->getRecord()->getRelationValue('media')?->firstWhere('uuid', $file);

                        if (! $media) {
                            return null;
                        }

                        return [
                            'name' => $media->getAttributeValue('name') ?? $media->getAttributeValue('file_name'),
                            'size' => $media->getAttributeValue('size'),
                            'type' => $media->getAttributeValue('mime_type'),
                            'url' => FinancialReportResource::getUrl('document', ['record' => $component->getRecord()]),
                        ];
                    })
                    ->validationMessages([
                        'required' => 'El archivo es obligatorio para guardar el reporte financiero.',
                    ]),
            ]);
    }
}
