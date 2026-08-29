<?php

namespace App\Filament\Resources\Inquiries\RelationManagers;

use App\Enums\InquiryStatus;
use App\Models\InquiryAnswer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AnswersRelationManager extends RelationManager
{
    protected static string $relationship = 'answers';

    protected static ?string $title = 'Respuestas';

    protected static ?string $modelLabel = 'Respuesta';

    protected static ?string $pluralModelLabel = 'Respuestas';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('user_id')
                    ->default(fn () => auth()->id()),
                Textarea::make('body')
                    ->label('Contenido de la Respuesta')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),
                Toggle::make('is_official')
                    ->label('Respuesta Oficial del Sindicato')
                    ->helperText('Marca esta respuesta con un distintivo oficial institucional')
                    ->default(fn () => auth()->user()?->isLeaderOrAdmin() ?? false),
                SpatieMediaLibraryFileUpload::make('attachments')
                    ->label('Archivos Adjuntos (Documentos, Guías, PDFs)')
                    ->collection('attachments')
                    ->multiple()
                    ->reorderable()
                    ->downloadable()
                    ->openable()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Respondido por')
                    ->weight('bold')
                    ->description(fn (InquiryAnswer $record): string => $record->user?->role?->getLabel() ?? ''),
                TextColumn::make('body')
                    ->label('Respuesta')
                    ->limit(60),
                IconColumn::make('is_official')
                    ->label('Oficial')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nueva Respuesta')
                    ->after(function () {
                        $this->getOwnerRecord()->update([
                            'status' => InquiryStatus::Answered,
                        ]);
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
