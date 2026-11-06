<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DisbursementMethod: string implements HasLabel
{
    case Transferencia = 'transferencia';
    case Cheque = 'cheque';
    case Efectivo = 'efectivo';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Transferencia => 'Transferencia bancaria',
            self::Cheque => 'Cheque',
            self::Efectivo => 'Efectivo',
        };
    }
}
