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
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Loan $record): string => LoanResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('folio')
                    ->label('Folio')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Folio copiado')
                    ->fontFamily('mono')
                    ->weight('bold'),

                TextColumn::make('user.name')
                    ->label('Solicitante')
                    ->formatStateUsing(fn ($record) => $record->user?->full_name ?? 'N/A')
                    ->description(fn (Loan $record): ?string => $record->user?->curp ? "CURP: {$record->user->curp}" : null)
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
                    ->alignment('right')
                    ->sortable(),

                TextColumn::make('approved_amount')
                    ->label('Monto Aprobado')
                    ->money('MXN')
                    ->placeholder('—')
                    ->alignment('right')
                    ->sortable(),

                TextColumn::make('monthly_rate')
                    ->label('Tasa Mensual')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2).'%' : null)
                    ->placeholder('—')
                    ->alignment('center')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(LoanStatus::class),

                SelectFilter::make('payment_type')
                    ->label('Forma de Pago')
                    ->options(LoanPaymentType::class),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver Detalle'),
                Action::make('reviewDocuments')
                    ->label('Validar Evidencias')
                    ->icon(Heroicon::DocumentCheck)
                    ->color('info')
                    ->authorize('review')
                    ->visible(fn (Loan $record): bool => $record->relationLoaded('evidences') ? $record->evidences->isNotEmpty() : ($record->evidences_exists ?? $record->evidences()->exists()))
                    ->url(fn (Loan $record): string => LoanResource::getUrl('review-documents', ['record' => $record])),
            ])
            ->emptyStateHeading('Sin solicitudes de préstamo')
            ->emptyStateDescription('Aún no se han registrado solicitudes de préstamo.')
            ->emptyStateIcon(Heroicon::Banknotes)
            ->emptyStateActions([
                Action::make('createLoanEmpty')
                    ->label('Nueva Solicitud')
                    ->icon(Heroicon::Plus)
                    ->color('primary')
                    ->url(fn (): string => ApplyLoan::getUrl())
                    ->visible(fn (): bool => auth()->user()?->is_active ?? false),
            ]);
    }
}
