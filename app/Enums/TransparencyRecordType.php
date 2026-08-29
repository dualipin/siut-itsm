<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TransparencyRecordType: string implements HasLabel
{
    case Financiero = 'financiero';
    case Normativo = 'normativo';
    case Convenio = 'convenio';
    case Acta = 'acta';
    case Otro = 'otro';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Financiero => 'Informe Financiero',
            self::Normativo => 'Normativo',
            self::Convenio => 'Convenios y Contratos',
            self::Acta => 'Actas y Minutas',
            self::Otro => 'Otro',
        };
    }
}
