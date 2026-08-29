<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class LandingFooter extends Component
{
    public array $syndicate;

    public string $currentYear;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        $this->syndicate = config('syndicate');
        $this->currentYear = date('Y');
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('landing.components.footer');
    }
}
