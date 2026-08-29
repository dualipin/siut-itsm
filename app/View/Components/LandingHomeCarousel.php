<?php

namespace App\View\Components;

use App\Models\Post;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class LandingHomeCarousel extends Component
{
    /**
     * Summary of slides
     *
     * @var ?Post[]
     */
    public mixed $slides = null;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        $this->slides = Post::whereNull('expires_at')
            ->orWhere('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('landing.components.home-carousel');
    }
}
