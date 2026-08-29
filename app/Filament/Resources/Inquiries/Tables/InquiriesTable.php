<?php

namespace App\Filament\Resources\Inquiries\Tables;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InquiriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Título de la Duda')
                    ->searchable()
                    ->sortable()
                    ->limit(45)
                    ->weight('bold'),
                TextColumn::make('category')
                    ->label('Categoría')
                    ->badge()
                    ->searchable()
                    ->placeholder('General'),
                TextColumn::make('author')
                    ->label('Autor')
                    ->state(fn (Inquiry $record): string => $record->getAuthorDisplayName())
                    ->description(fn (Inquiry $record): string => $record->user ? 'Agremiado registrado' : 'Visitante web')
                    ->searchable(query: function ($query, string $search) {
                        $query->where('guest_name', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                    }),
                ToggleColumn::make('is_public')
                    ->label('¿Pública?')
                    ->sortable(),
                TextColumn::make('answers_count')
                    ->label('Respuestas')
                    ->counts('answers')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_public')
                    ->label('Visibilidad')
                    ->options([
                        '1' => 'Públicas',
                        '0' => 'Privadas',
                    ]),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(InquiryStatus::class),
                SelectFilter::make('category')
                    ->label('Categoría')
                    ->options([
                        'Trámites' => 'Trámites',
                        'Escalafón' => 'Escalafón',
                        'Prestaciones' => 'Prestaciones',
                        'Cuotas y Finanzas' => 'Cuotas y Finanzas',
                        'Afiliación' => 'Afiliación',
                        'General' => 'General',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
