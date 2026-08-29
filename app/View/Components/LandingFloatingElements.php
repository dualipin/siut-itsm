<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class LandingFloatingElements extends Component
{
    public string $facebookUrl;

    public string $whatsappUrl;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        $this->facebookUrl = config('syndicate.socialMedia.facebook');
        $this->whatsappUrl = config('syndicate.socialMedia.whatsapp');
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('landing.components.floating-elements');
    }
}
