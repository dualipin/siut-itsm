<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\Loans\Schemas\ReviewLoanDocumentsInfolist;
use App\Models\Loan;
use App\Models\LoanEvidence;
use App\Services\LoanDocumentAIService;
use App\Services\LoanService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReviewLoanDocuments extends ViewRecord
{
    protected static string $resource = LoanResource::class;

    public ?int $activeEvidenceId = null;

    protected ?Loan $cachedPreviousRecord = null;

    protected bool $hasCheckedPreviousRecord = false;

    protected ?Loan $cachedNextRecord = null;

    protected bool $hasCheckedNextRecord = false;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless(auth()->user()?->can('review', $this->getRecord()), 403, 'No tienes autorización para revisar evidencias.');

        /** @var Loan $loan */
        $loan = $this->getRecord();

        $requested = (int) request()->query('evidence', 0);
        if ($requested > 0 && $loan->evidences()->where('id', $requested)->exists()) {
            $this->activeEvidenceId = $requested;
        } else {
            $this->activeEvidenceId = $loan->evidences()->first()?->id;
        }
    }

    public function getTitle(): string
    {
        /** @var Loan $record */
        $record = $this->getRecord();

        $folio = str_replace('-', "\u{2011}", (string) $record->folio);

        return "Revisión de Evidencias — {$folio}";
    }

    public function infolist(Schema $schema): Schema
    {
        return ReviewLoanDocumentsInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        /** @var Loan $loan */
        $loan = $this->getRecord();

        return [
            Action::make('viewLoan')
                ->label('Volver a la Solicitud')
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->url(fn (): string => LoanResource::getUrl('view', ['record' => $loan])),

            Action::make('previousRecord')
                ->label('Anterior')
                ->icon(Heroicon::ChevronLeft)
                ->color('gray')
                ->url(fn (): ?string => ($prev = $this->getPreviousRecord())
                    ? LoanResource::getUrl('review-documents', ['record' => $prev])
                    : null
                )
                ->disabled(fn (): bool => $this->getPreviousRecord() === null),

            Action::make('nextRecord')
                ->label('Siguiente')
                ->icon(Heroicon::ChevronRight)
                ->iconPosition('after')
                ->color('gray')
                ->url(fn (): ?string => ($next = $this->getNextRecord())
                    ? LoanResource::getUrl('review-documents', ['record' => $next])
                    : null
                )
                ->disabled(fn (): bool => $this->getNextRecord() === null),

            Action::make('approveAllEvidences')
                ->label('Aprobar todas')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->visible(fn (): bool => $loan->evidences()->where('manual_review_status', '!=', 'approved')->exists())
                ->requiresConfirmation()
                ->modalHeading(fn (): string => "¿Aprobar todas las evidencias de {$loan->folio}?")
                ->modalDescription('Esta acción marcará como aprobadas todas las evidencias documentales de este préstamo.')
                ->action(function (LoanService $loanService): void {
                    /** @var Loan $loan */
                    $loan = $this->getRecord();
                    $approvedCount = 0;

                    foreach ($loan->evidences as $evidence) {
                        if ($evidence->manual_review_status !== 'approved') {
                            $loanService->reviewEvidence($evidence, auth()->user(), 'approved');
                            $approvedCount++;
                        }
                    }

                    Notification::make()
                        ->title('Evidencias aprobadas')
                        ->body("Se aprobaron {$approvedCount} evidencia(s) documental(es) de la solicitud {$loan->folio}.")
                        ->success()
                        ->send();

                    $this->refreshFormData([]);
                }),
        ];
    }

    public function approveEvidenceAction(): Action
    {
        return Action::make('approveEvidence')
            ->label('Aprobar')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Aprobar evidencia: '.$this->getEvidenceTitle((int) ($arguments['id'] ?? $this->activeEvidenceId)))
            ->modalDescription('Esta acción marcará el comprobante como válido y aprobado manualmente.')
            ->action(function (array $arguments, LoanService $loanService): void {
                $evidenceId = (int) ($arguments['id'] ?? $this->activeEvidenceId);
                $evidence = LoanEvidence::where('loan_id', $this->getRecord()->id)->findOrFail($evidenceId);

                $loanService->reviewEvidence($evidence, auth()->user(), 'approved', null);

                Notification::make()
                    ->title('Evidencia aprobada')
                    ->body("La evidencia '{$this->getEvidenceTitle($evidenceId)}' fue aprobada exitosamente.")
                    ->success()
                    ->send();

                $this->refreshFormData([]);
            });
    }

    public function rejectEvidenceAction(): Action
    {
        return Action::make('rejectEvidence')
            ->label('Rechazar')
            ->icon('heroicon-m-x-circle')
            ->color('danger')
            ->modalHeading(fn (array $arguments): string => 'Rechazar evidencia: '.$this->getEvidenceTitle((int) ($arguments['id'] ?? $this->activeEvidenceId)))
            ->form([
                Textarea::make('notes')
                    ->label('Motivo u observación de rechazo')
                    ->placeholder('Describe qué dato, sello o página es ilegible o incorrecto...')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data, array $arguments, LoanService $loanService): void {
                $evidenceId = (int) ($arguments['id'] ?? $this->activeEvidenceId);
                $evidence = LoanEvidence::where('loan_id', $this->getRecord()->id)->findOrFail($evidenceId);

                $loanService->reviewEvidence($evidence, auth()->user(), 'rejected', $data['notes']);

                Notification::make()
                    ->title('Evidencia rechazada')
                    ->body("La evidencia '{$this->getEvidenceTitle($evidenceId)}' fue marcada como rechazada.")
                    ->danger()
                    ->send();

                $this->refreshFormData([]);
            });
    }

    public function resetEvidenceAction(): Action
    {
        return Action::make('resetEvidence')
            ->label('Restablecer a Pendiente')
            ->icon('heroicon-m-arrow-path')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Restablecer evidencia: '.$this->getEvidenceTitle((int) ($arguments['id'] ?? $this->activeEvidenceId)))
            ->modalDescription('El estado volverá a Pendiente de dictamen manual.')
            ->action(function (array $arguments, LoanService $loanService): void {
                $evidenceId = (int) ($arguments['id'] ?? $this->activeEvidenceId);
                $evidence = LoanEvidence::where('loan_id', $this->getRecord()->id)->findOrFail($evidenceId);

                $loanService->reviewEvidence($evidence, auth()->user(), 'pending', null);

                Notification::make()
                    ->title('Evidencia restablecida')
                    ->body('El estado de la evidencia ha vuelto a Pendiente.')
                    ->info()
                    ->send();

                $this->refreshFormData([]);
            });
    }

    public function analyzeWithAiAction(): Action
    {
        return Action::make('analyzeWithAi')
            ->label('Analizar con IA')
            ->icon('heroicon-m-cpu-chip')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Analizar con IA: '.$this->getEvidenceTitle((int) ($arguments['id'] ?? $this->activeEvidenceId)))
            ->modalDescription('Se enviará el documento al servicio de IA para extraer datos y comparar con el perfil del solicitante.')
            ->action(function (array $arguments, LoanDocumentAIService $aiService): void {
                $evidenceId = (int) ($arguments['id'] ?? $this->activeEvidenceId);
                /** @var Loan $loan */
                $loan = $this->getRecord();
                $evidence = LoanEvidence::where('loan_id', $loan->id)->findOrFail($evidenceId);

                $result = $aiService->evaluate(
                    $evidence->file_path,
                    $evidence->source_type,
                    $loan->user,
                    $evidence->benefit
                );

                $evidence->update([
                    'ai_status' => $result->aiStatus,
                    'ai_classification' => $result->classification,
                    'ai_confidence' => $result->confidence,
                    'ai_model' => $result->model,
                    'ai_prompt_version' => $result->promptVersion,
                    'extracted_data' => $result->extractedFields,
                    'comparison_data' => $result->comparison,
                ]);

                Notification::make()
                    ->title('Análisis completado')
                    ->body("La IA clasificó el comprobante como '{$result->classification}' con ".round($result->confidence * 100).'% de confianza.')
                    ->success()
                    ->send();

                $this->refreshFormData([]);
            });
    }

    public function replaceEvidenceAction(): Action
    {
        return Action::make('replaceEvidence')
            ->label('Reemplazar archivo')
            ->icon('heroicon-m-arrow-path')
            ->color('gray')
            ->modalHeading(fn (array $arguments): string => 'Subir o reemplazar archivo: '.$this->getEvidenceTitle((int) ($arguments['id'] ?? $this->activeEvidenceId)))
            ->modalDescription('Sube el archivo correspondiente en formato PDF o imagen. El estado se restablecerá a Pendiente.')
            ->form([
                FileUpload::make('document_file')
                    ->label('Archivo del Comprobante')
                    ->disk('local')
                    ->directory(fn () => 'loans/'.$this->getRecord()->id.'/evidences')
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->required(),
            ])
            ->action(function (array $data, array $arguments, LoanDocumentAIService $aiService): void {
                $evidenceId = (int) ($arguments['id'] ?? $this->activeEvidenceId);
                /** @var Loan $loan */
                $loan = $this->getRecord();
                $evidence = LoanEvidence::where('loan_id', $loan->id)->findOrFail($evidenceId);

                $filePath = $data['document_file'];
                $fullPath = Storage::disk('local')->path($filePath);
                $mime = (string) (mime_content_type($fullPath) ?: 'application/octet-stream');
                $size = (int) (filesize($fullPath) ?: 0);
                $hash = (string) (hash_file('sha256', $fullPath) ?: '');
                $origName = basename($filePath);

                $evidence->update([
                    'file_path' => $filePath,
                    'file_name' => $origName,
                    'mime_type' => $mime,
                    'file_size' => $size,
                    'file_hash' => $hash,
                    'manual_review_status' => 'pending',
                    'manual_review_notes' => null,
                ]);

                try {
                    $result = $aiService->evaluate($filePath, $evidence->source_type, $loan->user, $evidence->benefit);
                    $evidence->update([
                        'ai_status' => $result->aiStatus,
                        'ai_classification' => $result->classification,
                        'ai_confidence' => $result->confidence,
                        'ai_model' => $result->model,
                        'ai_prompt_version' => $result->promptVersion,
                        'extracted_data' => $result->extractedFields,
                        'comparison_data' => $result->comparison,
                    ]);
                } catch (Throwable) {
                    // Ignore AI failure on manual replace
                }

                Notification::make()
                    ->title('Archivo guardado')
                    ->body('El archivo fue actualizado y el estado se restableció a Pendiente.')
                    ->success()
                    ->send();

                $this->refreshFormData([]);
            });
    }

    protected function getEvidenceTitle(?int $id): string
    {
        if (! $id) {
            return 'Evidencia';
        }

        $ev = $this->getRecord()->evidences()->find($id);
        if (! $ev) {
            return 'Evidencia';
        }

        return $ev->source_type === 'nomina'
            ? 'Comprobante de Nómina'
            : ($ev->benefit?->name ?? 'Comprobante de Prestación');
    }

    private function getPreviousRecord(): ?Loan
    {
        if ($this->hasCheckedPreviousRecord) {
            return $this->cachedPreviousRecord;
        }

        $this->hasCheckedPreviousRecord = true;
        /** @var Loan $current */
        $current = $this->getRecord();

        return $this->cachedPreviousRecord = Loan::query()
            ->where('id', '<', $current->id)
            ->whereHas('evidences')
            ->orderBy('id', 'desc')
            ->first();
    }

    private function getNextRecord(): ?Loan
    {
        if ($this->hasCheckedNextRecord) {
            return $this->cachedNextRecord;
        }

        $this->hasCheckedNextRecord = true;
        /** @var Loan $current */
        $current = $this->getRecord();

        return $this->cachedNextRecord = Loan::query()
            ->where('id', '>', $current->id)
            ->whereHas('evidences')
            ->orderBy('id', 'asc')
            ->first();
    }
}
