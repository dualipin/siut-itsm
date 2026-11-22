<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\ExportUsersPdf;
use App\Actions\ExportUsersWithDocuments;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Padrón de Usuarios';

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('exportPdf')
                    ->label('Listado en PDF')
                    ->icon(Heroicon::DocumentArrowDown)
                    ->visible(fn (): bool => auth()->user()?->isLeaderOrAdmin() ?? false)
                    ->modalHeading('Exportar padrón en PDF')
                    ->modalDescription('Elige entre 1 y 5 columnas para mantener la tabla legible al imprimir.')
                    ->modalSubmitActionLabel('Descargar PDF')
                    ->schema([
                        CheckboxList::make('columns')
                            ->label('Columnas del reporte')
                            ->helperText('Con 4 o 5 columnas el documento se genera en orientación horizontal.')
                            ->options(ExportUsersPdf::AVAILABLE_COLUMNS)
                            ->default(ExportUsersPdf::DEFAULT_COLUMNS)
                            ->columns(2)
                            ->required()
                            ->minItems(1)
                            ->maxItems(ExportUsersPdf::MAX_COLUMNS)
                            ->rules([
                                'array',
                                'min:1',
                                'max:'.ExportUsersPdf::MAX_COLUMNS,
                            ])
                            ->nestedRecursiveRules([
                                Rule::in(array_keys(ExportUsersPdf::AVAILABLE_COLUMNS)),
                            ])
                            ->validationMessages([
                                'required' => 'Selecciona al menos una columna para generar el PDF.',
                                'min' => 'Selecciona al menos una columna para generar el PDF.',
                                'max' => 'Selecciona como máximo 5 columnas para que el reporte sea legible.',
                            ]),
                    ])
                    ->action(function (array $data): StreamedResponse {
                        abort_unless(auth()->user()?->isLeaderOrAdmin(), 403);

                        $pdf = app(ExportUsersPdf::class)->execute(
                            $this->getTableQueryForExport(),
                            $data['columns'],
                        );

                        $filename = 'padron-usuarios-'.now()->format('Y-m-d').'.pdf';

                        return response()->streamDownload(
                            static function () use ($pdf): void {
                                echo $pdf->output();
                            },
                            $filename,
                            ['Content-Type' => 'application/pdf'],
                        );
                    }),
                Action::make('exportWithDocuments')
                    ->label('Padrón y documentos (ZIP)')
                    ->icon(Heroicon::ArchiveBoxArrowDown)
                    ->visible(fn (): bool => auth()->user()?->isLeaderOrAdmin() ?? false)
                    ->action(function (): BinaryFileResponse {
                        abort_unless(auth()->user()?->isLeaderOrAdmin(), 403);

                        $zipPath = app(ExportUsersWithDocuments::class)->execute(
                            $this->getTableQueryForExport(),
                        );

                        $filename = 'padron-usuarios-documentos-'.now()->format('Y-m-d').'.zip';

                        return response()
                            ->download($zipPath, $filename, ['Content-Type' => 'application/zip'])
                            ->deleteFileAfterSend(true);
                    }),
            ])
                ->label('Exportar')
                ->icon(Heroicon::ArrowDownTray)
                ->color('gray')
                ->button()
                ->visible(fn (): bool => auth()->user()?->isLeaderOrAdmin() ?? false),
            Action::make('import')
                ->label('Importar padrón')
                ->icon(Heroicon::ArrowUpTray)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->isLeaderOrAdmin() ?? false)
                ->url(UserResource::getUrl('import')),
            CreateAction::make(),
        ];
    }
}
