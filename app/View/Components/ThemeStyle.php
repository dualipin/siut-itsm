<?php

namespace App\View\Components;

use App\Models\Theme;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ThemeStyle extends Component
{
    public ?Theme $theme;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        $this->theme = Theme::where('id', 1)->first() ?? Theme::first();
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.theme-style');
    }
}
