<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LoanPaymentType: string implements HasColor, HasLabel
{
    case Nomina = 'nomina';
    case Benefit = 'benefit';
    case Mixed = 'mixed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Nomina => 'Nómina',
            self::Benefit => 'Prestación(es)',
            self::Mixed => 'Mixta (Nómina + Prestación)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Nomina => 'primary',
            self::Benefit => 'info',
            self::Mixed => 'warning',
        };
    }

    public function hasNomina(): bool
    {
        return in_array($this, [self::Nomina, self::Mixed], true);
    }

    public function hasBenefits(): bool
    {
        return in_array($this, [self::Benefit, self::Mixed], true);
    }
}
