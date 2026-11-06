<?php

namespace App\Filament\Resources\Loans\Schemas;

use App\Models\Loan;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class ReviewLoanDocumentsInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.loans.review-loan-documents-panel')
                ->columnSpanFull()
                ->viewData(static function (Loan $record): array {
                    $record->loadMissing([
                        'user',
                        'evidences.benefit',
                        'evidences.reviewer',
                        'loanBenefits.benefit',
                    ]);

                    $user = $record->user;
                    $evidences = $record->evidences;

                    $evidencesData = [];
                    $validCount = 0;

                    foreach ($evidences as $evidence) {
                        $isApproved = $evidence->manual_review_status === 'approved';

                        if ($isApproved) {
                            $validCount++;
                        }

                        $sourceLabel = $evidence->source_type === 'nomina'
                            ? 'Comprobante de Nómina'
                            : ($evidence->benefit?->name ?? 'Comprobante de Prestación');

                        $icon = $evidence->source_type === 'nomina'
                            ? 'heroicon-m-document-text'
                            : 'heroicon-m-banknotes';

                        $manualStatusLabel = match ($evidence->manual_review_status) {
                            'approved' => 'Aprobado',
                            'rejected' => 'Rechazado',
                            default => 'Pendiente',
                        };

                        $manualStatusColor = match ($evidence->manual_review_status) {
                            'approved' => 'success',
                            'rejected' => 'danger',
                            default => 'warning',
                        };

                        $hasFile = filled($evidence->file_path) && Storage::disk('local')->exists($evidence->file_path);
                        $downloadUrl = $hasFile
                            ? route('loans.evidences.download', [
                                'loan' => $record->id,
                                'evidence' => $evidence->id,
                                'v' => $evidence->updated_at?->timestamp ?? time(),
                            ])
                            : null;

                        $extracted = is_array($evidence->extracted_data) ? $evidence->extracted_data : [];
                        $comparison = is_array($evidence->comparison_data) ? $evidence->comparison_data : [];

                        // Comparisons
                        $docTrabajador = trim((string) ($extracted['nombre_trabajador'] ?? ''));
                        $profileName = trim((string) ($user?->full_name ?? ''));
                        $nameMatch = $comparison['name_match'] ?? (
                            $docTrabajador !== '' && $profileName !== '' && (
                                str_contains(mb_strtolower($docTrabajador), mb_strtolower($profileName)) ||
                                str_contains(mb_strtolower($profileName), mb_strtolower($docTrabajador))
                            )
                        );

                        $docCurp = strtoupper(trim((string) ($extracted['curp'] ?? '')));
                        $profileCurp = strtoupper(trim((string) ($user?->curp ?? '')));
                        $curpMatch = $comparison['curp_match'] ?? (
                            $docCurp !== '' && $profileCurp !== '' && $docCurp === $profileCurp
                        );

                        $docRfc = strtoupper(trim((string) ($extracted['rfc'] ?? '')));
                        $profileRfc = strtoupper(trim((string) ($user?->rfc ?? '')));
                        $rfcMatch = ($docRfc !== '' && $profileRfc !== '') ? ($docRfc === $profileRfc) : null;

                        $evidencesData[$evidence->id] = [
                            'id' => $evidence->id,
                            'source_type' => $evidence->source_type,
                            'label' => $sourceLabel,
                            'icon' => $icon,
                            'fileName' => $evidence->file_name,
                            'fileSizeFormatted' => $evidence->file_size ? round($evidence->file_size / 1024).' KB' : null,
                            'mimeType' => $evidence->mime_type,
                            'hasFile' => $hasFile,
                            'downloadUrl' => $downloadUrl,
                            'manual_status' => $evidence->manual_review_status,
                            'manual_status_label' => $manualStatusLabel,
                            'manual_status_color' => $manualStatusColor,
                            'manual_review_notes' => $evidence->manual_review_notes,
                            'reviewer_name' => $evidence->reviewer?->full_name,
                            'reviewed_at' => $evidence->reviewed_at,
                            'ai_status' => $evidence->ai_status,
                            'ai_status_label' => $evidence->ai_status?->getLabel() ?? 'Sin dictamen IA',
                            'ai_status_color' => $evidence->ai_status?->getColor() ?? 'gray',
                            'ai_confidence' => $evidence->ai_confidence,
                            'ai_classification' => $evidence->ai_classification,
                            'ai_model' => $evidence->ai_model,
                            'extracted_data' => $extracted,
                            'comparison_data' => $comparison,
                            'checklist' => [
                                'trabajador' => [
                                    'label' => 'Nombre del trabajador',
                                    'profileValue' => $profileName,
                                    'docValue' => $docTrabajador,
                                    'hasComparison' => $docTrabajador !== '',
                                    'matches' => (bool) $nameMatch,
                                ],
                                'curp' => [
                                    'label' => 'CURP',
                                    'profileValue' => $profileCurp,
                                    'docValue' => $docCurp,
                                    'hasComparison' => $docCurp !== '',
                                    'matches' => (bool) $curpMatch,
                                ],
                                'rfc' => [
                                    'label' => 'RFC',
                                    'profileValue' => $profileRfc ?: '—',
                                    'docValue' => $docRfc,
                                    'hasComparison' => $docRfc !== '',
                                    'matches' => $rfcMatch ?? true,
                                ],
                                'monto' => [
                                    'label' => $evidence->source_type === 'nomina' ? 'Monto solicitado' : 'Monto de prestación',
                                    'profileValue' => '$'.number_format($evidence->source_type === 'nomina' ? $record->requested_amount : ($record->loanBenefits->firstWhere('benefit_id', $evidence->benefit_id)?->requested_amount ?? 0), 2),
                                    'docValue' => ! empty($extracted['monto_neto']) ? '$'.$extracted['monto_neto'] : '',
                                    'hasComparison' => ! empty($extracted['monto_neto']),
                                    'matches' => true,
                                ],
                                'periodo' => [
                                    'label' => 'Periodo o fecha',
                                    'profileValue' => $record->first_fortnight_date?->format('d/m/Y') ?? 'N/A',
                                    'docValue' => $extracted['periodo_o_fecha'] ?? '',
                                    'hasComparison' => ! empty($extracted['periodo_o_fecha']),
                                    'matches' => true,
                                ],
                            ],
                        ];
                    }

                    return [
                        'record' => $record,
                        'user' => $user,
                        'evidencesData' => $evidencesData,
                        'validCount' => $validCount,
                        'totalEvidences' => count($evidencesData),
                    ];
                }),
        ]);
    }
}
