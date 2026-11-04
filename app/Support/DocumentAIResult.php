<?php

namespace App\Support;

final readonly class DocumentAIResult
{
    /**
     * @param  array<string, string>  $extractedFields  Named fields extracted from the document.
     * @param  array<string, string>  $aiFlags  Human-readable alerts for the reviewer (no PII in keys).
     */
    public function __construct(
        public bool $isValidDocument,
        public string $documentType,
        public array $extractedFields,
        public float $confidenceScore,
        public string $observations,
        public array $aiFlags = [],
    ) {}

    /**
     * Normalized payload persisted in the media `extracted_data` custom property.
     *
     * @return array{is_valid_document: bool, document_type: string, extracted_fields: array<string, string>, confidence_score: float, observations: string, ai_flags: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'is_valid_document' => $this->isValidDocument,
            'document_type' => $this->documentType,
            'extracted_fields' => $this->extractedFields,
            'confidence_score' => $this->confidenceScore,
            'observations' => $this->observations,
            'ai_flags' => $this->aiFlags,
        ];
    }
}
