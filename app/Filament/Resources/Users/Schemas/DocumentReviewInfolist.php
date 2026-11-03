<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\DocumentStatus;
use App\Enums\UserDocumentType;
use App\Models\User;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class DocumentReviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.review-documents-panel')
                ->columnSpanFull()
                ->viewData(static function (User $record): array {
                    $fields = [
                        'name' => ['label' => 'Nombre(s)', 'prop' => 'doc_name'],
                        'surnames' => ['label' => 'Apellidos', 'prop' => 'doc_surnames'],
                        'curp' => ['label' => 'CURP', 'prop' => 'doc_curp'],
                        'email' => ['label' => 'Correo', 'prop' => 'doc_email'],
                        'phone' => ['label' => 'Teléfono', 'prop' => 'doc_phone'],
                        'address' => ['label' => 'Dirección', 'prop' => 'doc_address'],
                        'nss' => ['label' => 'NSS', 'prop' => 'doc_nss'],
                        'category' => ['label' => 'Categoría', 'prop' => 'doc_category'],
                    ];

                    $documentData = [];
                    $validCount = 0;

                    foreach (UserDocumentType::cases() as $type) {
                        $media = $record->getDocumentMedia($type);
                        $status = $record->getDocumentStatus($type);
                        $rejectionReason = $record->getDocumentRejectionReason($type);

                        if ($status instanceof DocumentStatus && $status === DocumentStatus::Valid) {
                            $validCount++;
                        }

                        $checklist = [];
                        foreach ($fields as $key => $meta) {
                            $profileVal = (string) ($record->{$key} ?? '');
                            $docVal = (string) ($media?->getCustomProperty($meta['prop']) ?? '');
                            $hasComparison = $docVal !== '';
                            $matches = $hasComparison
                                ? (mb_strtolower(trim($profileVal)) === mb_strtolower(trim($docVal)))
                                : true;

                            $checklist[$key] = [
                                'label' => $meta['label'],
                                'profileValue' => $profileVal,
                                'docValue' => $docVal,
                                'matches' => $matches,
                                'hasComparison' => $hasComparison,
                            ];
                        }

                        $documentData[$type->value] = [
                            'type' => $type,
                            'label' => $type->getLabel(),
                            'icon' => $type->getIcon(),
                            'media' => $media,
                            'fileName' => $media?->file_name,
                            'documentUrl' => $media
                                ? route('portal.users.documents.show', [
                                    'user' => $record,
                                    'type' => $type->value,
                                ])
                                : null,
                            'status' => $status,
                            'statusLabel' => $status === 'sin_subir' ? 'Sin subir' : $status->getLabel(),
                            'statusColor' => $status === 'sin_subir' ? 'gray' : $status->getColor(),
                            'rejectionReason' => $rejectionReason,
                            'checklist' => $checklist,
                        ];
                    }

                    return [
                        'record' => $record,
                        'fields' => $fields,
                        'documentData' => $documentData,
                        'validCount' => $validCount,
                        'totalDocuments' => count(UserDocumentType::cases()),
                    ];
                }),
        ]);
    }
}
