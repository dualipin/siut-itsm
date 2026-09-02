<div class="navbar bg-primary text-primary-content shadow-sm sticky top-0 z-50">
    <div class="navbar-start">
        <a href="{{ url('/') }}" title="Inicio">
            <img src="{{ asset('assets/img/logo.webp') }}" alt="Logo" class="size-16 rounded-full" />
        </a>
    </div>

    <div class="navbar-center hidden lg:flex">
        <ul class="menu menu-horizontal px-1 gap-1">
            @foreach($navbarLinks as $link)
                @php
                    $isActive = false;
                    if (isset($link['href']) && $link['href'] !== '/' && request()->is(ltrim($link['href'], '/') . '*')) {
                        $isActive = true;
                    } elseif (isset($link['items'])) {
                        foreach ($link['items'] as $subItem) {
                            if (isset($subItem['href']) && request()->is(ltrim($subItem['href'], '/') . '*')) {
                                $isActive = true;
                                break;
                            }
                        }
                    }
                @endphp
                @if(isset($link['items']) && count($link['items']) > 0)
                    <li>
                        <details class="group">
                            <summary class="font-medium hover:bg-white/10 transition-colors rounded-xl px-3.5 py-2 {{ $isActive ? 'bg-white/20 font-bold shadow-xs' : '' }}">
                                {{ $link['label'] }}
                            </summary>
                            <ul class="p-2 bg-base-100 text-base-content shadow-2xl rounded-2xl z-50 w-72 max-h-[calc(100vh-5rem)] overflow-y-auto border border-base-300/80 animate-fade-in-down animate-duration-fast">
                                @include('landing.partials.recursive-menu', ['links' => $link['items']])
                            </ul>
                        </details>
                    </li>
                @else
                    <li>
                        <a href="{{ $link['href'] ?? '#' }}"
                           class="font-medium hover:bg-white/10 transition-colors rounded-xl px-3.5 py-2 {{ (request()->url() === url($link['href'] ?? '')) ? 'bg-white/20 font-bold shadow-xs' : '' }}">
                            {{ $link['label'] }}
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
    </div>

    <div class="navbar-end gap-2">
        <a href="{{ route('filament.portal.auth.register') }}" class="btn btn-secondary hidden sm:flex gap-2 font-semibold">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <line x1="19" x2="19" y1="8" y2="14" />
                <line x1="22" x2="16" y1="11" y2="11" />
            </svg>
            Registrarse
        </a>
        <a href="{{ route('filament.portal.auth.login') }}" class="btn btn-soft gap-2 font-semibold">
            Acceder
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                <polyline points="10 17 15 12 10 7" />
                <line x1="15" x2="3" y1="12" y2="12" />
            </svg>
        </a>
        <label for="landing-drawer" class="btn btn-square lg:hidden">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-6">
                <line x1="4" x2="20" y1="12" y2="12" />
                <line x1="4" x2="20" y1="6" y2="6" />
                <line x1="4" x2="20" y1="18" y2="18" />
            </svg>
        </label>
        <img src="{{ asset('assets/img/logo/logo-snes.png') }}" alt="Snes"
            class="h-10 hidden lg:block bg-primary-content p-1 rounded-field" />
    </div>
</div>