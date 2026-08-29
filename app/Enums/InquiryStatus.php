<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InquiryStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Answered = 'answered';
    case Closed = 'closed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Answered => 'Respondida',
            self::Closed => 'Cerrada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Answered => 'success',
            self::Closed => 'gray',
        };
    }
}
