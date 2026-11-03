<?php

namespace App\Support;

final readonly class DocumentAIResult
{
    /**
     * @param  array{nombre: string, curp: string, monto: string}  $extractedFields
     */
    public function __construct(
        public bool $isValidDocument,
        public string $documentType,
        public array $extractedFields,
        public float $confidenceScore,
        public string $observations,
    ) {}

    /**
     * Normalized payload persisted in the media `extracted_data` custom property.
     *
     * @return array{is_valid_document: bool, document_type: string, extracted_fields: array{nombre: string, curp: string, monto: string}, confidence_score: float, observations: string}
     */
    public function toArray(): array
    {
        return [
            'is_valid_document' => $this->isValidDocument,
            'document_type' => $this->documentType,
            'extracted_fields' => $this->extractedFields,
            'confidence_score' => $this->confidenceScore,
            'observations' => $this->observations,
        ];
    }
}
