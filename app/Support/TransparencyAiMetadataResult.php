<?php

namespace App\Support;

use App\Enums\TransparencyRecordType;

final readonly class TransparencyAiMetadataResult
{
    public function __construct(
        public bool $successful,
        public ?TransparencyRecordType $type = null,
        public ?string $name = null,
        public ?string $summary = null,
        public ?string $documentDate = null,
        public ?int $fiscalYear = null,
        public ?string $period = null,
        public ?string $referenceNumber = null,
        public bool $containsPersonalData = false,
        public ?string $internalReasonSuggestion = null,
        public float $confidence = 0.0,
        public string $sourceKind = 'none',
        public string $model = 'none',
        public string $promptVersion = '',
        public ?string $errorMessage = null,
        public bool $unsupported = false,
    ) {}

    public static function failure(
        string $errorMessage,
        string $model = 'none',
        string $promptVersion = '',
        string $sourceKind = 'none',
        bool $unsupported = false,
    ): self {
        return new self(
            successful: false,
            sourceKind: $sourceKind,
            model: $model,
            promptVersion: $promptVersion,
            errorMessage: $errorMessage,
            unsupported: $unsupported,
        );
    }
}
