<?php

namespace App\Services;

use Carbon\CarbonImmutable;

final readonly class EfirmaValidationResult
{
    public function __construct(
        public string $certificateBase64,
        public string $certificateSerial,
        public string $rfc,
        public string $curp,
        public string $signerName,
        public CarbonImmutable $validFrom,
        public CarbonImmutable $validTo,
        public string $originalChain,
        public string $seal,
        public CarbonImmutable $signedAt,
    ) {}
}
