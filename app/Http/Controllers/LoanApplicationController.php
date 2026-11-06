<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLoanRequest;
use App\Http\Requests\UploadLoanEvidenceRequest;
use App\Models\Benefit;
use App\Models\Loan;
use App\Models\LoanEvidence;
use App\Models\LoanGeneratedDocument;
use App\Models\User;
use App\Services\LoanDocumentAIService;
use App\Services\LoanService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LoanApplicationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected readonly LoanService $loanService,
        protected readonly LoanDocumentAIService $aiService,
    ) {}

    /**
     * Store or update draft loan.
     */
    public function store(StoreLoanRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $loanId = $request->input('loan_id');
        if ($loanId) {
            $loan = Loan::where('id', $loanId)->where('user_id', $user->id)->firstOrFail();
            $this->authorize('update', $loan);
            $loan = $this->loanService->updateDraft($loan, $validated);
        } else {
            $this->authorize('create', Loan::class);
            $loan = $this->loanService->createDraft($user, $validated);
        }

        if (! empty($validated['signature_data'])) {
            $this->loanService->signLoan($loan, (string) $validated['signature_data']);
        }

        return response()->json([
            'success' => true,
            'loan' => $loan->fresh(['loanBenefits.benefit', 'evidences']),
            'message' => 'Borrador guardado exitosamente.',
        ]);
    }

    /**
     * Synchronous blocking document upload and AI evaluation.
     */
    public function uploadEvidence(UploadLoanEvidenceRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $file = $request->file('file');
        $sourceType = (string) $request->input('source_type');
        $benefitId = $request->input('benefit_id') ? (int) $request->input('benefit_id') : null;
        $loanId = $request->input('loan_id') ? (int) $request->input('loan_id') : null;

        // 1. Local validation (extension, mime, max 5MB, max 5 pages)
        $this->aiService->validateLocalFile($file);

        // 2. Private storage and SHA-256 hash calculation
        $storageData = $this->aiService->storePrivatelyAndHash($file);

        // 3. AI classification, minimal extraction & local comparison
        $benefit = $benefitId ? Benefit::find($benefitId) : null;
        $aiResult = $this->aiService->evaluate($storageData['path'], $sourceType, $user, $benefit);

        // 4. If attached to a loan, persist or update LoanEvidence
        $evidence = null;
        if ($loanId) {
            $loan = Loan::where('id', $loanId)->where('user_id', $user->id)->firstOrFail();
            $this->authorize('update', $loan);

            $evidence = LoanEvidence::updateOrCreate(
                [
                    'loan_id' => $loan->id,
                    'source_type' => $sourceType,
                    'benefit_id' => $benefitId,
                ],
                [
                    'file_path' => $storageData['path'],
                    'file_hash' => $storageData['hash'],
                    'file_name' => $storageData['name'],
                    'mime_type' => $storageData['mime'],
                    'file_size' => $storageData['size'],
                    'ai_status' => $aiResult->aiStatus,
                    'ai_classification' => $aiResult->classification,
                    'ai_confidence' => $aiResult->confidence,
                    'ai_model' => $aiResult->model,
                    'ai_prompt_version' => $aiResult->promptVersion,
                    'extracted_data' => $aiResult->extractedFields,
                    'comparison_data' => $aiResult->comparison,
                    'manual_review_status' => 'pending',
                ]
            );
        }

        return response()->json([
            'success' => true,
            'storage' => $storageData,
            'ai_result' => $aiResult->toArray(),
            'evidence' => $evidence,
        ]);
    }

    /**
     * Capture applicant's drawn signature.
     */
    public function sign(Request $request, Loan $loan): JsonResponse
    {
        $this->authorize('update', $loan);

        $request->validate([
            'signature_data' => ['required', 'string'],
        ]);

        $loan = $this->loanService->signLoan($loan, (string) $request->input('signature_data'));

        return response()->json([
            'success' => true,
            'message' => 'Firma registrada correctamente.',
            'loan' => $loan,
        ]);
    }

    /**
     * Submit loan for review.
     */
    public function submit(Request $request, Loan $loan): JsonResponse
    {
        $this->authorize('submit', $loan);

        $loan = $this->loanService->submitLoan($loan, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Solicitud enviada exitosamente para revisión.',
            'redirect_url' => route('filament.portal.resources.loans.index'),
            'loan' => $loan->fresh(['loanBenefits', 'evidences']),
        ]);
    }

    /**
     * Download authorized private generated PDF.
     */
    public function downloadDocument(Loan $loan, LoanGeneratedDocument $document): BinaryFileResponse
    {
        $this->authorize('downloadDocument', $loan);

        abort_if($document->loan_id !== $loan->id, 404);
        abort_if(! Storage::disk('local')->exists($document->file_path), 404, 'Archivo no encontrado.');

        $fullPath = Storage::disk('local')->path($document->file_path);
        $filename = "{$loan->folio}-{$document->document_type->value}-v{$document->version}.pdf";

        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Cache-Control' => 'private, no-cache, must-revalidate',
        ]);
    }

    /**
     * Download authorized evidence.
     */
    public function downloadEvidence(Loan $loan, LoanEvidence $evidence): BinaryFileResponse
    {
        $this->authorize('view', $loan);

        abort_if($evidence->loan_id !== $loan->id, 404);
        abort_if(! Storage::disk('local')->exists($evidence->file_path), 404, 'Archivo no encontrado.');

        $fullPath = Storage::disk('local')->path($evidence->file_path);

        return response()->file($fullPath, [
            'Content-Type' => $evidence->mime_type,
            'Content-Disposition' => "inline; filename=\"{$evidence->file_name}\"",
            'Cache-Control' => 'private, no-cache, must-revalidate',
        ]);
    }
}
