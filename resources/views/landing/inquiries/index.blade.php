@extends('landing.layout')

@section('content')
<section class="py-12 md:py-20 bg-base-100 min-h-screen text-base-content">
    <div class="container mx-auto px-4 max-w-7xl">
        
        <!-- Hero de la sección -->
        <div class="max-w-3xl mx-auto text-center mb-12 animate-fade-in-down">
            <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-primary/10 text-primary mb-3">
                <i class="bi bi-question-circle text-sm"></i>
                Atención y Orientación Sindical
            </span>
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-black tracking-tight mb-4 text-base-content">
                Centro de Dudas y Consultas
            </h1>
            <p class="text-base-content/70 text-sm sm:text-base md:text-lg leading-relaxed mb-6">
                Encuentra respuestas a las inquietudes más comunes sobre tus derechos, trámites, escalafón y prestaciones, o envía tu propia consulta al sindicato.
            </p>

            <!-- Isla de Vue: Botón y Modal de Nueva Duda -->
            <div data-vue="inquiries/new-inquiry-modal"
                 data-props="{{ json_encode([
                     'authUser' => auth()->check() ? [
                         'id' => auth()->id(),
                         'name' => auth()->user()->full_name ?: auth()->user()->name,
                         'email' => auth()->user()->email,
                         'role' => auth()->user()->role?->getLabel() ?? '',
                         'isAdmin' => auth()->user()->isLeaderOrAdmin(),
                     ] : null
                 ]) }}">
            </div>
        </div>

        <!-- Filtros: Buscador + Categorías (SEO Friendly Form) -->
        <div class="mb-10 space-y-5">
            <!-- Buscador -->
            <form action="{{ route('inquiries.index') }}" method="GET" class="max-w-2xl mx-auto">
                @if(request('category') && request('category') !== 'Todas')
                    <input type="hidden" name="category" value="{{ request('category') }}">
                @endif
                <div class="relative">
                    <input type="text"
                           name="search"
                           value="{{ request('search') }}"
                           placeholder="Buscar en dudas resueltas (ej. préstamo, escalafón, vacaciones)..."
                           class="input input-bordered w-full rounded-2xl pl-12 pr-28 py-3.5 bg-base-200/50 focus:bg-base-100 focus:border-primary text-sm shadow-xs transition-all" />
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-base-content/40">
                        <i class="bi bi-search text-base"></i>
                    </span>
                    <button type="submit" class="btn btn-sm btn-primary rounded-xl absolute right-2.5 top-1/2 -translate-y-1/2 text-xs font-semibold">
                        Buscar
                    </button>
                </div>
            </form>

            <!-- Píldoras de Categoría -->
            <div class="flex items-center justify-center gap-2 flex-wrap">
                @foreach($categories as $cat)
                    <a href="{{ route('inquiries.index', ['category' => $cat, 'search' => request('search')]) }}"
                       class="px-4 py-1.5 rounded-full text-xs font-semibold transition-all border
                       {{ $currentCategory === $cat
                           ? 'bg-primary text-primary-content border-primary shadow-sm'
                           : 'bg-base-200/60 text-base-content/70 border-base-300/80 hover:bg-base-200 hover:text-base-content' }}">
                        {{ $cat }}
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Grid de Dudas (Server-rendered para SEO) -->
        @if($inquiries->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($inquiries as $item)
                    <article class="card bg-base-100 border border-base-300/80 rounded-3xl shadow-sm hover:shadow-md transition-all duration-300 flex flex-col justify-between group">
                        <div class="p-6">
                            <!-- Categoría y Respuestas -->
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <span class="badge badge-sm font-semibold rounded-lg bg-base-200 text-base-content/80 border-0">
                                    {{ $item->category ?: 'General' }}
                                </span>
                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary">
                                    <i class="bi bi-chat-text"></i>
                                    {{ $item->answers_count }} {{ $item->answers_count === 1 ? 'respuesta' : 'respuestas' }}
                                </span>
                            </div>

                            <!-- Título -->
                            <a href="{{ route('inquiries.show', $item->slug) }}" class="group-hover:text-primary transition-colors">
                                <h3 class="font-bold text-base md:text-lg text-base-content leading-snug line-clamp-2 mb-2">
                                    {{ $item->title }}
                                </h3>
                            </a>

                            <!-- Extracto -->
                            <p class="text-xs md:text-sm text-base-content/70 line-clamp-3 leading-relaxed mb-4">
                                {{ Str::limit($item->body, 140) }}
                            </p>

                            <!-- Indicador de respuesta oficial -->
                            @if($item->answers->contains('is_official', true))
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-success/10 text-success text-[11px] font-bold">
                                    <i class="bi bi-patch-check-fill"></i>
                                    <span>Respuesta oficial disponible</span>
                                </div>
                            @endif
                        </div>

                        <!-- Footer de la tarjeta -->
                        <div class="px-6 py-3.5 bg-base-200/40 border-t border-base-200 rounded-b-3xl flex items-center justify-between text-xs text-base-content/60">
                            <div class="flex items-center gap-3">
                                <span class="flex items-center gap-1">
                                    <i class="bi bi-eye"></i>
                                    {{ $item->views_count }}
                                </span>
                                <span>{{ $item->getAuthorDisplayName() }}</span>
                            </div>
                            <a href="{{ route('inquiries.show', $item->slug) }}" class="font-bold text-primary inline-flex items-center gap-0.5 hover:underline">
                                <span>Ver detalle</span>
                                <i class="bi bi-chevron-right text-xs"></i>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            <!-- Paginación -->
            <div class="mt-10 flex justify-center">
                {{ $inquiries->links() }}
            </div>
        @else
            <div class="text-center py-16 bg-base-200/30 rounded-3xl border border-base-300/60 max-w-lg mx-auto p-8">
                <i class="bi bi-question-circle text-4xl text-base-content/30 mb-3 block"></i>
                <h3 class="font-bold text-base text-base-content mb-1">No se encontraron dudas</h3>
                <p class="text-xs text-base-content/60 mb-5">
                    No encontramos preguntas que coincidan con los criterios seleccionados. Puedes formular la primera duda sobre este tema.
                </p>
                <a href="{{ route('inquiries.index') }}" class="btn btn-sm btn-primary rounded-full px-5">
                    Ver todas las dudas
                </a>
            </div>
        @endif

    </div>
</section>
@endsection
