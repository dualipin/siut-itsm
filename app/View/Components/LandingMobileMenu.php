<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class LandingMobileMenu extends Component
{
    public array $navbarLinks;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        $this->navbarLinks = config('landing.links', []);
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('landing.components.mobile-menu');
    }
}
