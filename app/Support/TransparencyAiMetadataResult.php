<?php

namespace App\Support;

use App\Enums\TransparencyAiSourceKind;
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
        public TransparencyAiSourceKind $sourceKind = TransparencyAiSourceKind::None,
        public string $model = 'none',
        public string $promptVersion = '',
        public ?string $errorMessage = null,
        public bool $unsupported = false,
    ) {}

    public static function failure(
        string $errorMessage,
        string $model = 'none',
        string $promptVersion = '',
        TransparencyAiSourceKind $sourceKind = TransparencyAiSourceKind::None,
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
