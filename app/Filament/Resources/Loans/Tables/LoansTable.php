<?php

namespace App\Filament\Resources\Loans\Tables;

use App\Enums\LoanPaymentType;
use App\Enums\LoanStatus;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\Loans\Pages\ApplyLoan;
use App\Models\Loan;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LoansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('folio')
                    ->label('Folio')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('user.name')
                    ->label('Solicitante')
                    ->formatStateUsing(fn ($record) => $record->user?->full_name ?? 'N/A')
                    ->searchable(query: function ($query, $search) {
                        $query->whereHas('user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('surnames', 'like', "%{$search}%")
                                ->orWhere('curp', 'like', "%{$search}%");
                        });
                    })
                    ->visible(fn () => auth()->user()?->isLeaderOrAdmin() ?? false),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),

                TextColumn::make('payment_type')
                    ->label('Forma de Pago')
                    ->badge(),

                TextColumn::make('requested_amount')
                    ->label('Monto Solicitado')
                    ->money('MXN')
                    ->sortable(),

                TextColumn::make('approved_amount')
                    ->label('Monto Aprobado')
                    ->money('MXN')
                    ->placeholder('Pendiente')
                    ->sortable(),

                TextColumn::make('monthly_rate')
                    ->label('Tasa Mensual')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2).'%' : null)
                    ->placeholder('Por definir'),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(collect(LoanStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])),

                SelectFilter::make('payment_type')
                    ->label('Forma de Pago')
                    ->options(collect(LoanPaymentType::cases())->mapWithKeys(fn ($p) => [$p->value => $p->getLabel()])),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver Detalle'),
                Action::make('reviewDocuments')
                    ->label('Validar Evidencias')
                    ->icon(Heroicon::DocumentCheck)
                    ->color('info')
                    ->visible(fn (Loan $record): bool => (auth()->user()?->isLeaderOrAdmin() ?? false) && $record->evidences()->exists())
                    ->url(fn (Loan $record): string => LoanResource::getUrl('review-documents', ['record' => $record])),
            ])
            ->headerActions([
                Action::make('createLoan')
                    ->label('Nueva Solicitud')
                    ->icon(Heroicon::Plus)
                    ->color('primary')
                    ->url(fn (): string => ApplyLoan::getUrl()),
            ]);
    }
}
