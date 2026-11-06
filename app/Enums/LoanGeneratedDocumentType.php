<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LoanGeneratedDocumentType: string implements HasLabel
{
    case Solicitud = 'solicitud';
    case Pagare = 'pagare';
    case Anuencia = 'anuencia';
    case CorridaFinanciera = 'corrida_financiera';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Solicitud => 'Formato de Solicitud',
            self::Pagare => 'Pagaré',
            self::Anuencia => 'Anuencia de Descuento',
            self::CorridaFinanciera => 'Corrida Financiera',
        };
    }
}
