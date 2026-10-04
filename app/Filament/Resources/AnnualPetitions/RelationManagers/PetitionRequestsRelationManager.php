<?php

namespace App\Filament\Resources\AnnualPetitions\RelationManagers;

use App\Filament\Resources\PetitionRequests\Schemas\PetitionRequestInfolist;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PetitionRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'petitionRequests';

    protected static ?string $title = 'Peticiones del Pliego';

    protected static ?string $modelLabel = 'Petición';

    protected static ?string $pluralModelLabel = 'Peticiones';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return PetitionRequestInfolist::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('proposal')
            ->columns([
                TextColumn::make('agremiado_name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('curp')
                    ->label('CURP')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('proposal')
                    ->label('Propuesta')
                    ->limit(80)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('media_count')
                    ->label('Adjuntos')
                    ->counts('media')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Fecha de Actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('created_at')
                    ->label('Periodo de Registro')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Desde')
                            ->native(false),
                        DatePicker::make('hasta')
                            ->label('Hasta')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date)
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canEdit($record): bool
    {
        return false;
    }

    public function canDelete($record): bool
    {
        return false;
    }
}
