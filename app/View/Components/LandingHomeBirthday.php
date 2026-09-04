<?php

namespace App\View\Components;

use App\Services\BirthdayService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class LandingHomeBirthday extends Component
{
    /**
     * Lista de cumpleañeros enriquecida con frases y tags dinámicos.
     *
     * @var list<array<string, mixed>>
     */
    public array $celebrants = [];

    /**
     * Create a new component instance.
     *
     * @param  list<array<string, mixed>>|null  $celebrants
     */
    public function __construct(
        public ?int $limit = 6,
        ?array $celebrants = null,
        public bool $fallback = false,
    ) {
        $this->celebrants = $celebrants !== null
            ? BirthdayService::makeDynamic($celebrants)
            : BirthdayService::getCelebrants($this->limit, $this->fallback);
    }

    /**
     * Determine if the component should be rendered.
     */
    public function shouldRender(): bool
    {
        return ! empty($this->celebrants);
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('landing.components.home-birthday');
    }
}
