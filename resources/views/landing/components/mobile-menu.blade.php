<ul class="menu bg-base-100 text-base-content min-h-full w-80 p-4">
    <li class="mb-4 flex flex-row items-start justify-between">
        <a href="{{ url('/') }}" class="text-2xl font-bold p-0 hover:bg-transparent">
            Menú
        </a>
        <label for="landing-drawer" class="btn btn-square btn-ghost">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-6"><line x1="18" x2="6" y1="6" y2="18"/><line x1="6" x2="18" y1="6" y2="18"/></svg>
        </label>
    </li>
    
    @foreach($navbarLinks as $link)
        @if(isset($link['items']) && count($link['items']) > 0)
            <li>
                <details>
                    <summary class="font-semibold">{{ $link['label'] }}</summary>
                    <ul>
                        @include('landing.partials.recursive-menu', ['links' => $link['items']])
                    </ul>
                </details>
            </li>
        @else
            <li>
                <a href="{{ $link['href'] ?? '#' }}" class="font-semibold">
                    {{ $link['label'] }}
                </a>
            </li>
        @endif
    @endforeach

    <li>
        <a href="{{ url('/auth/register') }}" class="btn btn-secondary gap-2 font-semibold">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
            Registrarse
        </a>
    </li>
</ul>