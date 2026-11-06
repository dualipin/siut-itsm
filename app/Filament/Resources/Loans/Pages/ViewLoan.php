<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Enums\DisbursementMethod;
use App\Enums\LoanStatus;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\Loans\Schemas\LoanInfolist;
use App\Models\Loan;
use App\Models\LoanEvidence;
use App\Services\LoanPdfGenerationService;
use App\Services\LoanService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

class ViewLoan extends ViewRecord
{
    protected static string $resource = LoanResource::class;

    protected static ?string $title = 'Detalle de Solicitud de Préstamo';

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

        return [
            // Manual Evidence Validation Action
            Action::make('validateEvidence')
                ->label('Validar Evidencia Manual')
                ->icon(Heroicon::DocumentCheck)
                ->color('info')
                ->visible(fn (): bool => $record->evidences()->where('manual_review_status', 'pending')->exists())
                ->form([
                    Select::make('evidence_id')
                        ->label('Seleccionar Evidencia a Dictaminar')
                        ->options(function () use ($record) {
                            return $record->evidences()
                                ->get()
                                ->mapWithKeys(fn (LoanEvidence $e) => [
                                    $e->id => "{$e->source_type}: ".($e->source_type === 'nomina' ? 'Recibo Nómina' : ($e->benefit?->name ?? 'Prestación'))." ({$e->ai_status->getLabel()})",
                                ]);
                        })
                        ->required(),
                    Select::make('decision')
                        ->label('Decisión del Revisor')
                        ->options([
                            'approved' => 'Aprobar Documento',
                            'rejected' => 'Rechazar Documento',
                        ])
                        ->required(),
                    Textarea::make('notes')
                        ->label('Notas de Revisión')
                        ->placeholder('Justificación de la validación manual')
                        ->required(),
                ])
                ->action(function (array $data, LoanService $loanService): void {
                    $evidence = LoanEvidence::findOrFail((int) $data['evidence_id']);
                    $loanService->reviewEvidence($evidence, auth()->user(), $data['decision'], $data['notes']);

                    Notification::make()
                        ->title('Evidencia dictaminada')
                        ->body("La evidencia fue marcada como {$data['decision']}.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'evidences']);
                }),

            // Approve Action
            Action::make('approveLoan')
                ->label('Aprobar Préstamo')
                ->icon(Heroicon::CheckCircle)
                ->color('success')
                ->visible(fn (): bool => in_array($record->status, [LoanStatus::Enviada, LoanStatus::EnRevision, LoanStatus::ListaEspera], true))
                ->requiresConfirmation()
                ->modalHeading(fn (): string => "¿Aprobar la solicitud {$record->folio}?")
                ->modalDescription('Esta acción autorizará el préstamo, persistirá la corrida financiera y generará el expediente privado de PDFs.')
                ->form([
                    TextInput::make('approved_amount')
                        ->label('Monto Aprobado ($ MXN)')
                        ->numeric()
                        ->default($record->requested_amount)
                        ->required(),
                    Select::make('monthly_rate')
                        ->label('Tasa de Interés Mensual')
                        ->options([
                            '6.00' => '6.00% mensual — Agremiado y ahorrador',
                            '7.50' => '7.50% mensual — Agremiado y no ahorrador',
                            '8.00' => '8.00% mensual — Ahorrador no agremiado',
                            '9.50' => '9.50% mensual — No ahorrador y no agremiado',
                        ])
                        ->default($record->monthly_rate ? number_format((float) $record->monthly_rate, 2, '.', '') : '6.00')
                        ->required(),
                    TextInput::make('nomina_amount')
                        ->label('Monto por Nómina')
                        ->numeric()
                        ->default($record->nomina_amount)
                        ->visible($record->payment_type->hasNomina()),
                    TextInput::make('nomina_fortnights')
                        ->label('Quincenas de Nómina')
                        ->numeric()
                        ->default($record->nomina_fortnights)
                        ->visible($record->payment_type->hasNomina()),
                    Textarea::make('comment')
                        ->label('Observaciones de Aprobación')
                        ->placeholder('Comentario del revisor'),
                ])
                ->action(function (array $data, LoanService $loanService): void {
                    try {
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

            // Reject Action
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

            // Waitlist Action
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
