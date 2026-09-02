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
            self::Contratos => 'Contrato',
            self::Formato => 'Formato',
            self::Acervo => 'Acervo',
        };
    }

    public function getPluralLabel(): string
    {
        return match ($this) {
            self::Aviso => 'Avisos',
            self::Noticia => 'Noticias',
            self::Gestion => 'Gestiones',
            self::Contratos => 'Contratos',
            self::Formato => 'Formatos',
            self::Acervo => 'Acervo Documental',
        };
    }

    public function getSlug(): string
    {
        return match ($this) {
            self::Aviso => 'avisos',
            self::Noticia => 'noticias',
            self::Gestion => 'gestiones',
            self::Contratos => 'contratos',
            self::Formato => 'formatos',
            self::Acervo => 'acervo',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Aviso => 'Avisos importantes, comunicados urgentes y convocatorias para la base trabajadora.',
            self::Noticia => 'Noticias, eventos y acontecimientos relevantes de la vida sindical y académica.',
            self::Gestion => 'Informes de avances, negociaciones y logros obtenidos en beneficio de la comunidad sindical.',
            self::Contratos => 'Contratos colectivos de trabajo, convenios y acuerdos salariales con plena certeza jurídica.',
            self::Formato => 'Formatos oficiales, solicitudes de trámites, permisos y formatos de afiliación descargables.',
            self::Acervo => 'Memoria histórica, estatutos sindicales, publicaciones y acervo documental del sindicato.',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Aviso => 'bi-megaphone',
            self::Noticia => 'bi-newspaper',
            self::Gestion => 'bi-briefcase',
            self::Contratos => 'bi-file-earmark-text',
            self::Formato => 'bi-file-earmark-arrow-down',
            self::Acervo => 'bi-archive',
        };
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::Aviso => 'badge-warning',
            self::Noticia => 'badge-info',
            self::Gestion => 'badge-primary',
            self::Contratos => 'badge-success',
            self::Formato => 'badge-secondary',
            self::Acervo => 'badge-accent',
        };
    }

    public static function fromSlug(string $slug): ?self
    {
        return match (strtolower(trim($slug))) {
            'aviso', 'avisos' => self::Aviso,
            'noticia', 'noticias' => self::Noticia,
            'gestion', 'gestiones' => self::Gestion,
            'contrato', 'contratos' => self::Contratos,
            'formato', 'formatos' => self::Formato,
            'acervo', 'acervos' => self::Acervo,
            default => null,
        };
    }
}
