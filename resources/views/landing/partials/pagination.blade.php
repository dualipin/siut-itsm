@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navegación de páginas" class="flex flex-col sm:flex-row items-center justify-between gap-4 w-full pt-8 border-t border-base-200">
        {{-- Contador de resultados (Desktop y Mobile) --}}
        <div class="text-xs sm:text-sm text-base-content/70 text-center sm:text-left">
            Mostrando
            @if ($paginator->firstItem())
                <span class="font-semibold text-base-content tabular-nums">{{ $paginator->firstItem() }}</span>
                a
                <span class="font-semibold text-base-content tabular-nums">{{ $paginator->lastItem() }}</span>
            @else
                <span class="font-semibold text-base-content tabular-nums">{{ $paginator->count() }}</span>
            @endif
            de
            <span class="font-semibold text-base-content tabular-nums">{{ $paginator->total() }}</span>
            publicaciones
        </div>

        {{-- Controles en móvil --}}
        <div class="flex items-center gap-2 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="btn btn-sm btn-ghost btn-disabled opacity-40 cursor-not-allowed" aria-disabled="true">
                    Anterior
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-sm btn-outline border-base-300">
                    Anterior
                </a>
            @endif

            <span class="text-xs font-semibold text-base-content/80 px-2 tabular-nums">
                {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-sm btn-outline border-base-300">
                    Siguiente
                </a>
            @else
                <span class="btn btn-sm btn-ghost btn-disabled opacity-40 cursor-not-allowed" aria-disabled="true">
                    Siguiente
                </span>
            @endif
        </div>

        {{-- Controles en escritorio (DaisyUI join) --}}
        <div class="hidden sm:flex items-center">
            <div class="join border border-base-300/80 rounded-xl bg-base-100 shadow-2xs">
                {{-- Botón Anterior --}}
                @if ($paginator->onFirstPage())
                    <button type="button" class="join-item btn btn-sm btn-ghost opacity-35 cursor-not-allowed" disabled aria-label="Página anterior">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="join-item btn btn-sm btn-ghost hover:bg-base-200" aria-label="Página anterior">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                @endif

                {{-- Elementos de página --}}
                @foreach ($elements as $element)
                    {{-- Separador de puntos suspensivos --}}
                    @if (is_string($element))
                        <span class="join-item btn btn-sm btn-ghost cursor-default opacity-50">{{ $element }}</span>
                    @endif

                    {{-- Enlaces a páginas numéricas --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="join-item btn btn-sm btn-primary font-bold tabular-nums" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="join-item btn btn-sm btn-ghost font-medium tabular-nums hover:bg-base-200" aria-label="Ir a la página {{ $page }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Botón Siguiente --}}
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="join-item btn btn-sm btn-ghost hover:bg-base-200" aria-label="Página siguiente">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @else
                    <button type="button" class="join-item btn btn-sm btn-ghost opacity-35 cursor-not-allowed" disabled aria-label="Página siguiente">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                @endif
            </div>
        </div>
    </nav>
@endif
