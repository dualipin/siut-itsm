<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum UserDocumentType: string implements HasIcon, HasLabel
{
    case Afiliacion = 'afiliacion';
    case ComprobanteDomicilio = 'comprobante_domicilio';
    case Ine = 'ine';
    case ComprobantePago = 'comprobante_pago';
    case Curp = 'curp';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Afiliacion => 'Afiliación',
            self::ComprobanteDomicilio => 'Comprobante de Domicilio',
            self::Ine => 'INE / Identificación Oficial',
            self::ComprobantePago => 'Comprobante de Pago',
            self::Curp => 'CURP',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Afiliacion => 'heroicon-m-clipboard-document-check',
            self::ComprobanteDomicilio => 'heroicon-m-home',
            self::Ine => 'heroicon-m-identification',
            self::ComprobantePago => 'heroicon-m-banknotes',
            self::Curp => 'heroicon-m-document-text',
        };
    }
}
