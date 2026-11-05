<?php

namespace App\Services;

use App\Enums\LoanGeneratedDocumentType;
use App\Models\Loan;
use App\Models\LoanGeneratedDocument;
use App\Models\User;
use App\Support\DocumentThemePalette;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class LoanPdfGenerationService
{
    public function __construct(
        protected readonly DocumentThemePalette $documentThemePalette,
        protected readonly LoanSimulationService $loanSimulationService,
    ) {}

    /**
     * Generate all 4 required loan documents privately and versioned.
     * Catches any PDF errors to prevent rolling back approval/disbursement.
     *
     * @return array{success: bool, errors: list<string>}
     */
    public function generateAllDocuments(Loan $loan, ?User $generatedBy = null): array
    {
        $loan->loadMissing(['user', 'loanBenefits.benefit', 'amortizationRows', 'financialSummary']);

        $types = [
            LoanGeneratedDocumentType::Solicitud,
            LoanGeneratedDocumentType::Pagare,
            LoanGeneratedDocumentType::Anuencia,
            LoanGeneratedDocumentType::CorridaFinanciera,
        ];

        $errors = [];

        foreach ($types as $type) {
            try {
                $this->generateSingleDocument($loan, $type, $generatedBy);
            } catch (Throwable $e) {
                Log::warning('LoanPdfGeneration: fallo al generar documento.', [
                    'loan_id' => $loan->id,
                    'document_type' => $type->value,
                    'error' => $e->getMessage(),
                ]);
                $errors[] = "Error al generar {$type->getLabel()}: {$e->getMessage()}";
            }
        }

        $allSucceeded = empty($errors);
        $loan->updateQuietly([
            'documents_generated' => $allSucceeded,
            'pdf_generation_errors' => $allSucceeded ? null : $errors,
        ]);

        return [
            'success' => $allSucceeded,
            'errors' => $errors,
        ];
    }

    /**
     * Generate or regenerate a single document type for the loan.
     *
     * @throws Throwable
     */
    public function generateSingleDocument(Loan $loan, LoanGeneratedDocumentType $type, ?User $generatedBy = null): LoanGeneratedDocument
    {
        $loan->loadMissing(['user', 'loanBenefits.benefit', 'amortizationRows', 'financialSummary']);
        $user = $loan->user;

        $nextVersion = ((int) $loan->generatedDocuments()->where('document_type', $type->value)->max('version')) + 1;

        $viewData = $this->prepareViewData($loan, $user);

        $pdfOutput = match ($type) {
            LoanGeneratedDocumentType::Solicitud => DomPdf::loadView('pdf.loans.solicitud', $viewData)->output(),
            LoanGeneratedDocumentType::Pagare => DomPdf::loadView('pdf.loans.pagare', $viewData)->output(),
            LoanGeneratedDocumentType::Anuencia => DomPdf::loadView('pdf.loans.anuencia', $viewData)->output(),
            LoanGeneratedDocumentType::CorridaFinanciera => DomPdf::loadView('pdf.loans.corrida', $viewData)->output(),
        };

        $hash = hash('sha256', $pdfOutput);
        $fileName = "{$type->value}_v{$nextVersion}.pdf";
        $storagePath = "loans/{$loan->id}/generated_docs/{$fileName}";

        Storage::disk('local')->put($storagePath, $pdfOutput);

        /** @var LoanGeneratedDocument */
        return LoanGeneratedDocument::create([
            'loan_id' => $loan->id,
            'document_type' => $type,
            'version' => $nextVersion,
            'file_path' => $storagePath,
            'file_hash' => $hash,
            'file_size' => strlen($pdfOutput),
            'generated_by' => $generatedBy?->id ?? auth()->id(),
            'generated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function prepareViewData(Loan $loan, User $user): array
    {
        $amount = (float) ($loan->approved_amount ?? $loan->requested_amount);
        $amountInWords = NumberToWords::toCurrency($amount);

        $approvalDateLong = ($loan->reviewed_at ?? now())->translatedFormat('d \d\e F \d\e Y');

        // Resolve last payment date
        $lastPaymentDate = null;
        if ($loan->financialSummary?->last_fortnight) {
            $lastPaymentDate = Carbon::parse($loan->financialSummary->last_fortnight)->format('d/m/Y');
        } elseif ($loan->loanBenefits->isNotEmpty()) {
            $maxCollectionDate = $loan->loanBenefits->max('collection_date');
            if ($maxCollectionDate) {
                $lastPaymentDate = Carbon::parse($maxCollectionDate)->format('d/m/Y');
            }
        }

        // Summary & amortization data for corrida
        $summary = $loan->financialSummary ? [
            'amount' => (float) $loan->financialSummary->total_principal,
            'monthly_rate_percent' => number_format((float) $loan->monthly_rate, 2).' %',
            'disbursement_date' => $loan->financialSummary->disbursement_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'fortnights' => $loan->financialSummary->fortnights,
            'first_fortnight' => $loan->financialSummary->first_fortnight?->format('Y-m-d'),
            'last_fortnight' => $loan->financialSummary->last_fortnight?->format('Y-m-d'),
            'total_principal' => (float) $loan->financialSummary->total_principal,
            'total_interest' => (float) $loan->financialSummary->total_interest,
            'total_payment' => (float) $loan->financialSummary->total_payment,
        ] : [
            'amount' => $amount,
            'monthly_rate_percent' => number_format((float) $loan->monthly_rate, 2).' %',
            'disbursement_date' => now()->format('Y-m-d'),
            'fortnights' => $loan->nomina_fortnights,
            'first_fortnight' => $loan->first_fortnight_date?->format('Y-m-d'),
            'last_fortnight' => null,
            'total_principal' => $amount,
            'total_interest' => 0.0,
            'total_payment' => $amount,
        ];

        $amortizationTable = $loan->amortizationRows->map(fn ($r) => [
            'period' => $r->period,
            'label' => $r->label,
            'date' => $r->date?->format('Y-m-d') ?? '',
            'opening_balance' => (float) $r->opening_balance,
            'principal' => (float) $r->principal,
            'interest' => (float) $r->interest,
            'payment' => (float) $r->payment,
            'closing_balance' => (float) $r->closing_balance,
            'type' => $r->type,
        ])->toArray();

        return [
            'loan' => $loan,
            'user' => $user,
            'amountInWords' => $amountInWords,
            'approvalDateLong' => $approvalDateLong,
            'lastPaymentDate' => $lastPaymentDate,
            'summary' => $summary,
            'amortizationTable' => $amortizationTable,
            'documentColors' => $this->documentThemePalette->resolve(),
        ];
    }
}
