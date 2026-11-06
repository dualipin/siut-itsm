<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Enums\DisbursementMethod;
use App\Enums\LoanStatus;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\Loans\Schemas\LoanInfolist;
use App\Models\Loan;
use App\Services\LoanCalendarService;
use App\Services\LoanPdfGenerationService;
use App\Services\LoanService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Throwable;

class ViewLoan extends ViewRecord
{
    protected static string $resource = LoanResource::class;

    protected static ?string $title = 'Detalle de Solicitud';

    public function infolist(Schema $schema): Schema
    {
        return LoanInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        /** @var Loan $record */
        $record = $this->getRecord();
        $isLeaderOrAdmin = auth()->user()?->isLeaderOrAdmin() ?? false;

        if (! $isLeaderOrAdmin) {
            return [];
        }

        $calendarService = app(LoanCalendarService::class);

        return [
            // Manual Evidence Validation Action (navigates to split-view review-documents)
            Action::make('validateEvidence')
                ->label('Validar Evidencia Manual')
                ->icon(Heroicon::DocumentCheck)
                ->color('info')
                ->visible(fn (): bool => $record->evidences()->exists())
                ->badge(fn (): ?string => ($count = $record->evidences()->where('manual_review_status', 'pending')->count()) > 0 ? (string) $count : null)
                ->badgeColor('warning')
                ->url(fn (): string => LoanResource::getUrl('review-documents', ['record' => $record])),

            // Approve Action (Primary CTA)
            Action::make('approveLoan')
                ->label('Aprobar Préstamo')
                ->icon(Heroicon::CheckCircle)
                ->color('success')
                ->visible(fn (): bool => in_array($record->status, [LoanStatus::Enviada, LoanStatus::EnRevision, LoanStatus::ListaEspera], true))
                ->requiresConfirmation()
                ->modalHeading(fn (): string => "¿Aprobar la solicitud {$record->folio}?")
                ->modalDescription('Esta acción autorizará el préstamo, persistirá la corrida financiera y generará el expediente privado de PDFs.')
                ->modalWidth('2xl')
                ->form([
                    Section::make('Condiciones de la Tasa y Monto Total')
                        ->schema([
                            Grid::make(2)->schema([
                                Select::make('monthly_rate')
                                    ->label('Tasa de Interés Mensual')
                                    ->options([
                                        '6.00' => '6.00% mensual — Agremiado y ahorrador',
                                        '7.50' => '7.50% mensual — Agremiado y no ahorrador',
                                        '8.00' => '8.00% mensual — Ahorrador no agremiado',
                                        '9.50' => '9.50% mensual — No ahorrador y no agremiado',
                                        'custom' => 'Tasa personalizada...',
                                    ])
                                    ->default(function () use ($record): string {
                                        $standard = ['6.00', '7.50', '8.00', '9.50'];
                                        $rate = $record->monthly_rate !== null ? number_format((float) $record->monthly_rate, 2, '.', '') : '6.00';

                                        return in_array($rate, $standard, true) ? $rate : 'custom';
                                    })
                                    ->live()
                                    ->required()
                                    ->columnSpan(fn (Get $get): int => $get('monthly_rate') === 'custom' ? 1 : 2),

                                TextInput::make('custom_monthly_rate')
                                    ->label('Tasa Personalizada (% mensual)')
                                    ->numeric()
                                    ->suffix('% mensual')
                                    ->minValue(0.01)
                                    ->maxValue(100.0)
                                    ->step(0.01)
                                    ->default(function () use ($record): ?float {
                                        $standard = ['6.00', '7.50', '8.00', '9.50'];
                                        $rate = $record->monthly_rate !== null ? number_format((float) $record->monthly_rate, 2, '.', '') : '6.00';

                                        return ! in_array($rate, $standard, true) && $record->monthly_rate !== null ? (float) $record->monthly_rate : null;
                                    })
                                    ->visible(fn (Get $get): bool => $get('monthly_rate') === 'custom')
                                    ->required(fn (Get $get): bool => $get('monthly_rate') === 'custom')
                                    ->columnSpan(1),
                            ]),

                            TextInput::make('approved_amount')
                                ->label('Monto Aprobado Total ($ MXN)')
                                ->numeric()
                                ->prefix('$')
                                ->readOnly()
                                ->dehydrated()
                                ->default(function () use ($record): string {
                                    $total = 0.0;
                                    if ($record->payment_type->hasNomina()) {
                                        $total += (float) ($record->nomina_amount ?? 0);
                                    }
                                    if ($record->payment_type->hasBenefits()) {
                                        foreach ($record->loanBenefits as $lb) {
                                            $total += (float) ($lb->approved_amount ?? $lb->requested_amount ?? 0);
                                        }
                                    }

                                    return number_format($total > 0 ? $total : (float) $record->requested_amount, 2, '.', '');
                                })
                                ->helperText('Calculado automáticamente como la sumatoria del monto por cada forma de pago.')
                                ->required(),
                        ]),

                    Section::make('Forma de Pago: Nómina')
                        ->description('Descuento quincenal vía nómina')
                        ->visible($record->payment_type->hasNomina())
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('nomina_amount')
                                    ->label('Monto por Nómina ($ MXN)')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default($record->nomina_amount)
                                    ->required($record->payment_type->hasNomina())
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set) use ($record): void {
                                        $total = (float) ($get('nomina_amount') ?? 0);
                                        if ($record->payment_type->hasBenefits()) {
                                            foreach ($record->loanBenefits as $lb) {
                                                $total += (float) ($get("benefit_amount_{$lb->benefit_id}") ?? 0);
                                            }
                                        }
                                        $set('approved_amount', number_format($total, 2, '.', ''));
                                    }),

                                Select::make('nomina_fortnights')
                                    ->label('Plazo en Quincenas')
                                    ->options(function () use ($record, $calendarService): array {
                                        $firstDate = $record->first_fortnight_date
                                            ? Carbon::parse($record->first_fortnight_date)
                                            : ($calendarService->getFirstEligibleFortnight() ?? now()->addDays(20));

                                        $options = [];
                                        $year = $firstDate->year;
                                        $deadline = Carbon::create($year, 11, 30)->endOfDay();
                                        $current = $firstDate->copy();
                                        $i = 1;
                                        while ($current->lte($deadline) && $i <= 24) {
                                            $monthName = $current->translatedFormat('F');
                                            $dateLabel = "{$current->day} de ".ucfirst($monthName)." de {$current->year}";
                                            $options[$i] = "{$i} ".($i === 1 ? 'quincena' : 'quincenas')." (Termina: {$dateLabel})";
                                            $current = $calendarService->nextFortnight($current);
                                            $i++;
                                        }
                                        if ($record->nomina_fortnights && ! isset($options[$record->nomina_fortnights])) {
                                            $options[$record->nomina_fortnights] = "{$record->nomina_fortnights} quincenas";
                                        }

                                        return $options;
                                    })
                                    ->default($record->nomina_fortnights ?? 1)
                                    ->live()
                                    ->required($record->payment_type->hasNomina()),
                            ]),

                            Placeholder::make('nomina_completion_date')
                                ->label('Fecha en que se terminará de pagar por nómina')
                                ->content(function (Get $get) use ($record, $calendarService): string {
                                    $fortnights = (int) ($get('nomina_fortnights') ?? $record->nomina_fortnights ?? 1);
                                    if ($fortnights <= 0) {
                                        return 'No especificado';
                                    }
                                    $firstDate = $record->first_fortnight_date
                                        ? Carbon::parse($record->first_fortnight_date)
                                        : ($calendarService->getFirstEligibleFortnight() ?? now()->addDays(20));

                                    $endDate = $calendarService->calculateFortnightDate($firstDate, $fortnights);
                                    $monthName = $endDate->translatedFormat('F');

                                    return "{$endDate->day} de ".ucfirst($monthName)." de {$endDate->year} (Quincena {$fortnights} de {$fortnights})";
                                }),
                        ]),

                    Section::make('Forma de Pago: Prestación(es)')
                        ->description('Descuento programado sobre prestaciones laborales')
                        ->visible($record->payment_type->hasBenefits() && $record->loanBenefits->isNotEmpty())
                        ->schema(function () use ($record): array {
                            $components = [];
                            foreach ($record->loanBenefits as $lb) {
                                $benefitName = $lb->benefit?->name ?? 'Prestación';
                                $collectionDate = $lb->collection_date ? Carbon::parse($lb->collection_date) : null;
                                $collectionLabel = $collectionDate
                                    ? "{$collectionDate->day} de ".ucfirst($collectionDate->translatedFormat('F'))." de {$collectionDate->year}"
                                    : 'Fecha no registrada';

                                $components[] = Grid::make(2)->schema([
                                    TextInput::make("benefit_amount_{$lb->benefit_id}")
                                        ->label("Monto por {$benefitName} ($ MXN)")
                                        ->numeric()
                                        ->prefix('$')
                                        ->default($lb->approved_amount ?? $lb->requested_amount)
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Get $get, Set $set) use ($record): void {
                                            $total = 0.0;
                                            if ($record->payment_type->hasNomina()) {
                                                $total += (float) ($get('nomina_amount') ?? 0);
                                            }
                                            foreach ($record->loanBenefits as $otherLb) {
                                                $total += (float) ($get("benefit_amount_{$otherLb->benefit_id}") ?? 0);
                                            }
                                            $set('approved_amount', number_format($total, 2, '.', ''));
                                        }),

                                    Placeholder::make("benefit_date_{$lb->benefit_id}")
                                        ->label("Fecha de cobro / liquidación ({$benefitName})")
                                        ->content($collectionLabel),
                                ]);
                            }

                            return $components;
                        }),

                    Textarea::make('comment')
                        ->label('Observaciones de Aprobación')
                        ->placeholder('Comentario del revisor (opcional)'),
                ])
                ->action(function (array $data, LoanService $loanService) use ($record): void {
                    try {
                        $monthlyRate = ($data['monthly_rate'] ?? null) === 'custom'
                            ? (float) ($data['custom_monthly_rate'] ?? 0)
                            : (float) ($data['monthly_rate'] ?? 6.0);
                        $data['monthly_rate'] = $monthlyRate;

                        $benefitsTotal = 0.0;
                        if ($record->payment_type->hasBenefits()) {
                            $benefitsData = [];
                            foreach ($record->loanBenefits as $lb) {
                                $amount = (float) ($data["benefit_amount_{$lb->benefit_id}"] ?? $lb->approved_amount ?? $lb->requested_amount);
                                $benefitsTotal += $amount;
                                $benefitsData[] = [
                                    'benefit_id' => $lb->benefit_id,
                                    'approved_amount' => $amount,
                                ];
                            }
                            $data['benefits'] = $benefitsData;
                        }

                        $nominaTotal = $record->payment_type->hasNomina() ? (float) ($data['nomina_amount'] ?? 0) : 0.0;
                        $calculatedApprovedAmount = $nominaTotal + $benefitsTotal;

                        if ($calculatedApprovedAmount > 0) {
                            $data['approved_amount'] = $calculatedApprovedAmount;
                        }

                        $loanService->approve($this->getRecord(), auth()->user(), $data);
                        Notification::make()
                            ->title('Préstamo Aprobado')
                            ->body('La solicitud fue aprobada exitosamente y los documentos fueron generados.')
                            ->success()
                            ->send();

                        $this->redirect(LoanResource::getUrl('view', ['record' => $this->getRecord()]));
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Error al aprobar')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // ActionGroup for Secondary Actions (Rechazar, Lista de Espera)
            ActionGroup::make([
                Action::make('waitlistLoan')
                    ->label('Lista de Espera')
                    ->icon(Heroicon::Clock)
                    ->color('warning')
                    ->visible(fn (): bool => in_array($record->status, [LoanStatus::Enviada, LoanStatus::EnRevision], true))
                    ->requiresConfirmation()
                    ->modalHeading('Colocar en Lista de Espera')
                    ->form([
                        Textarea::make('reason')
                            ->label('Motivo / Justificación de la Espera')
                            ->required(),
                    ])
                    ->action(function (array $data, LoanService $loanService): void {
                        try {
                            $loanService->waitlist($this->getRecord(), auth()->user(), $data['reason']);
                            Notification::make()
                                ->title('Solicitud en Lista de Espera')
                                ->body('El estado fue actualizado a Lista de Espera.')
                                ->warning()
                                ->send();

                            $this->redirect(LoanResource::getUrl('view', ['record' => $this->getRecord()]));
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Error al cambiar estado')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('rejectLoan')
                    ->label('Rechazar')
                    ->icon(Heroicon::XCircle)
                    ->color('danger')
                    ->visible(fn (): bool => in_array($record->status, [LoanStatus::Enviada, LoanStatus::EnRevision, LoanStatus::ListaEspera], true))
                    ->requiresConfirmation()
                    ->modalHeading('Rechazar Solicitud de Préstamo')
                    ->form([
                        Textarea::make('reason')
                            ->label('Motivo del Rechazo')
                            ->required()
                            ->minLength(5),
                    ])
                    ->action(function (array $data, LoanService $loanService): void {
                        try {
                            $loanService->reject($this->getRecord(), auth()->user(), $data['reason']);
                            Notification::make()
                                ->title('Solicitud Rechazada')
                                ->body('Se ha registrado el rechazo y se notificó al solicitante.')
                                ->warning()
                                ->send();

                            $this->redirect(LoanResource::getUrl('view', ['record' => $this->getRecord()]));
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Error al rechazar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
                ->label('Más opciones')
                ->icon(Heroicon::ChevronDown)
                ->color('gray')
                ->button()
                ->visible(fn (): bool => in_array($record->status, [LoanStatus::Enviada, LoanStatus::EnRevision, LoanStatus::ListaEspera], true)),

            // Disburse Action
            Action::make('disburseLoan')
                ->label('Registrar Desembolso')
                ->icon(Heroicon::Banknotes)
                ->color('success')
                ->visible(fn (): bool => $record->status === LoanStatus::Aprobada && $record->disbursed_at === null)
                ->requiresConfirmation()
                ->modalHeading('Registrar Desembolso de Recursos')
                ->modalDescription('Esta acción registra la entrega efectiva del recurso, recalculando la corrida con la fecha real de desembolso.')
                ->form([
                    DatePicker::make('disbursed_at')
                        ->label('Fecha Real de Desembolso')
                        ->default(now())
                        ->required(),
                    TextInput::make('disbursed_amount')
                        ->label('Monto Desembolsado ($ MXN)')
                        ->numeric()
                        ->default($record->approved_amount ?? $record->requested_amount)
                        ->required(),
                    Select::make('disbursement_method')
                        ->label('Método de Desembolso')
                        ->options(collect(DisbursementMethod::cases())->mapWithKeys(fn ($m) => [$m->value => $m->getLabel()]))
                        ->default(DisbursementMethod::Transferencia->value)
                        ->required(),
                    TextInput::make('disbursement_reference')
                        ->label('Referencia / Folio de Operación / Cheque')
                        ->placeholder('Ej. SPEI-89213123 o Ch-0045'),
                ])
                ->action(function (array $data, LoanService $loanService): void {
                    try {
                        $loanService->disburse($this->getRecord(), auth()->user(), $data);
                        Notification::make()
                            ->title('Desembolso Registrado')
                            ->body('El préstamo ha sido marcado como desembolsado y la corrida fue actualizada.')
                            ->success()
                            ->send();

                        $this->redirect(LoanResource::getUrl('view', ['record' => $this->getRecord()]));
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Error al registrar desembolso')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // Regenerate PDFs Action
            Action::make('regeneratePdfs')
                ->label('Regenerar Documentos')
                ->icon(Heroicon::ArrowPath)
                ->color('gray')
                ->visible(fn (): bool => in_array($record->status, [LoanStatus::Aprobada, LoanStatus::Desembolsada], true))
                ->requiresConfirmation()
                ->modalHeading('Regenerar Expediente de Documentos PDF')
                ->modalDescription('Se generará una nueva versión de los 4 PDFs oficiales (Solicitud, Pagaré, Anuencia y Corrida) conservando el historial previo.')
                ->action(function (LoanPdfGenerationService $pdfService): void {
                    $result = $pdfService->generateAllDocuments($this->getRecord(), auth()->user());
                    if ($result['success']) {
                        Notification::make()
                            ->title('Documentos Regenerados')
                            ->body('Se generaron exitosamente las nuevas versiones de los PDFs.')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Generación Parcial con Errores')
                            ->body(implode('; ', $result['errors']))
                            ->warning()
                            ->send();
                    }

                    $this->redirect(LoanResource::getUrl('view', ['record' => $this->getRecord()]));
                }),
        ];
    }
}
