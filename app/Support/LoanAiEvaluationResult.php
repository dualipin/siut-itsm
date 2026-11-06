<?php

namespace App\Support;

use App\Enums\LoanAiStatus;

final readonly class LoanAiEvaluationResult
{
    /**
     * @param  array<string, mixed>  $extractedFields
     * @param  array<string, mixed>  $comparison
     */
    public function __construct(
        public LoanAiStatus $aiStatus,
        public string $classification,
        public float $confidence,
        public string $model,
        public string $promptVersion,
        public array $extractedFields,
        public array $comparison,
        public string $observations = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ai_status' => $this->aiStatus->value,
            'classification' => $this->classification,
            'confidence' => $this->confidence,
            'model' => $this->model,
            'prompt_version' => $this->promptVersion,
            'extracted_fields' => $this->extractedFields,
            'comparison' => $this->comparison,
            'observations' => $this->observations,
        ];
    }
}
