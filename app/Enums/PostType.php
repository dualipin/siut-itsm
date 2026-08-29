<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PostType: string implements HasLabel
{
    case Aviso = 'aviso';
    case Noticia = 'noticia';
    case Gestion = 'gestion';
    case Contratos = 'contratos';
    case Formato = 'formato';
    case Acervo = 'acervo';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Aviso => 'Aviso',
            self::Noticia => 'Noticia',
            self::Gestion => 'Gestión',
            self::Contratos => 'Contratos',
            self::Formato => 'Formato',
            self::Acervo => 'Acervo',
        };
    }
}
