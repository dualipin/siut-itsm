<?php

namespace App\View\Components;

use App\Services\BirthdayService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class LandingHomeBirthday extends Component
{
    /**
     * Lista de cumpleañeros enriquecida con mensajes y etiquetas.
     *
     * @var list<array<string, mixed>>
     */
    public array $celebrants = [];

    /**
     * Crear una nueva instancia del componente.
     *
     * @param  list<array<string, mixed>>|null  $celebrants
     */
    public function __construct(
        public ?int $limit = 6,
        ?array $celebrants = null,
        public bool $fallback = false,
        public string $title = 'Cumpleañeros de Hoy',
        public ?string $subtitle = 'Enviamos un fraternal saludo y mejores deseos a los miembros que celebran hoy su día.',
    ) {
        $this->celebrants = $celebrants !== null
            ? BirthdayService::makeDynamic($celebrants)
            : BirthdayService::getCelebrants($this->limit, $this->fallback);
    }

    /**
     * Determinar si el componente debe renderizarse.
     */
    public function shouldRender(): bool
    {
        return ! empty($this->celebrants);
    }

    /**
     * Obtener la vista del componente.
     */
    public function render(): View|Closure|string
    {
        return view('landing.components.home-birthday', [
            'celebrants' => $this->celebrants,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
        ]);
    }
}
