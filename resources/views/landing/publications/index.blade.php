@extends('landing.layout')

@section('content')
<section class="py-12 md:py-20 bg-base-100 min-h-screen text-base-content">
    <div class="container mx-auto px-4 max-w-7xl">

        <!-- Hero de la sección (sin kicker decorativo, jerarquía tipográfica limpia) -->
        <header class="max-w-3xl mx-auto text-center mb-10 md:mb-12">
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold font-display tracking-tight text-base-content text-balance mb-4">
                {{ $title }}
            </h1>
            <p class="text-base sm:text-lg text-base-content/75 leading-relaxed text-pretty max-w-2xl mx-auto">
                {{ $subtitle }}
            </p>
        </header>

        <!-- Barra de Búsqueda y Filtros de Tipos -->
        <div class="mb-10 md:mb-12 space-y-4">
            <!-- Buscador unificado y accesible -->
            <form action="{{ $currentType ? route('publications.type', $currentType->getSlug()) : route('publications.index') }}" method="GET" class="max-w-xl mx-auto">
                <div class="relative flex items-center">
                    <span class="absolute left-4 text-base-content/50 pointer-events-none" aria-hidden="true">
                        <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>
                    </span>
                    <input type="text"
                           name="search"
                           value="{{ request('search') }}"
                           placeholder="Buscar por título o contenido..."
                           aria-label="Buscar publicaciones"
                           class="input input-bordered w-full rounded-xl pl-11 pr-24 py-3 bg-base-100 focus:bg-base-100 focus:border-primary text-sm shadow-2xs transition-all" />
                    @if(request('search'))
                        <a href="{{ $currentType ? route('publications.type', $currentType->getSlug()) : route('publications.index') }}"
                           class="absolute right-20 text-base-content/40 hover:text-base-content p-1 transition-colors"
                           title="Limpiar búsqueda"
                           aria-label="Limpiar búsqueda">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </a>
                    @endif
                    <button type="submit" class="btn btn-sm btn-primary rounded-lg absolute right-2 text-xs font-semibold px-3.5">
                        Buscar
                    </button>
                </div>
            </form>

            <!-- Filtros de Categoría / PostType -->
            <nav aria-label="Categorías de publicaciones" class="flex items-center justify-center gap-1.5 sm:gap-2 flex-wrap max-w-4xl mx-auto">
                <a href="{{ route('publications.index', ['search' => request('search')]) }}"
                   @if(! $currentType) aria-current="page" @endif
                   class="px-3.5 py-1.5 rounded-full text-xs sm:text-sm font-medium transition-all border inline-flex items-center gap-1.5
                   {{ ! $currentType
                       ? 'bg-primary text-primary-content border-primary shadow-2xs font-semibold'
                       : 'bg-base-100 text-base-content/75 border-base-300 hover:bg-base-200/80 hover:text-base-content' }}">
                    <i class="bi bi-grid text-xs" aria-hidden="true"></i>
                    <span>Todas</span>
                </a>

                @foreach(\App\Enums\PostType::cases() as $type)
                    <a href="{{ route('publications.type', ['type' => $type->getSlug(), 'search' => request('search')]) }}"
                       @if($currentType === $type) aria-current="page" @endif
                       class="px-3.5 py-1.5 rounded-full text-xs sm:text-sm font-medium transition-all border inline-flex items-center gap-1.5
                       {{ ($currentType === $type)
                           ? 'bg-primary text-primary-content border-primary shadow-2xs font-semibold'
                           : 'bg-base-100 text-base-content/75 border-base-300 hover:bg-base-200/80 hover:text-base-content' }}">
                        <i class="bi {{ $type->getIcon() }} text-xs" aria-hidden="true"></i>
                        <span>{{ $type->getPluralLabel() }}</span>
                    </a>
                @endforeach
            </nav>

            <!-- Indicador activo de búsqueda con opción de restablecer -->
            @if(request('search'))
                <div class="max-w-xl mx-auto flex items-center justify-between bg-base-200/60 px-4 py-2 rounded-xl border border-base-300/70 text-xs text-base-content/80">
                    <span>
                        Resultados para: <strong class="text-base-content font-semibold">"{{ request('search') }}"</strong>
                        <span class="text-base-content/60 tabular-nums">({{ $posts->total() }})</span>
                    </span>
                    <a href="{{ $currentType ? route('publications.type', $currentType->getSlug()) : route('publications.index') }}"
                       class="text-primary hover:underline font-semibold inline-flex items-center gap-1">
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span>Quitar filtro</span>
                    </a>
                </div>
            @endif
        </div>

        <!-- Listado de Publicaciones (Grid) -->
        @if($posts->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                @foreach($posts as $post)
                    <article class="group flex flex-col bg-base-100 rounded-2xl border border-base-200 hover:border-base-300/80 shadow-2xs hover:shadow-md transition-all duration-200 overflow-hidden">
                        
                        <!-- Imagen con ratio 16/10 uniforme -->
                        <a href="{{ route('publications.show', ['type' => $post->type_slug, 'slug' => $post->slug]) }}"
                           class="block relative aspect-[16/10] w-full overflow-hidden bg-base-200 focus-visible:outline-hidden"
                           tabindex="-1"
                           aria-hidden="true">
                            <img src="{{ $post->thumbnail_url }}"
                                 alt="{{ $post->title }}"
                                 loading="lazy"
                                 class="w-full h-full object-cover group-hover:scale-102 transition-transform duration-300" />
                            
                            <!-- Badge de tipo sobre imagen -->
                            <div class="absolute top-3 left-3 flex flex-wrap items-center gap-1.5">
                                <span class="badge badge-sm font-medium rounded-md shadow-2xs {{ $post->type?->getBadgeClass() ?? 'badge-neutral' }} gap-1">
                                    <i class="bi {{ $post->type?->getIcon() ?? 'bi-file-text' }} text-[11px]" aria-hidden="true"></i>
                                    {{ $post->type?->getLabel() ?? 'Publicación' }}
                                </span>

                                @if($post->type === \App\Enums\PostType::Aviso && $post->expires_at)
                                    <span class="badge badge-sm badge-warning font-medium rounded-md shadow-2xs gap-1">
                                        <i class="bi bi-clock text-[11px]" aria-hidden="true"></i>
                                        Vence {{ $post->expires_at->format('d/m/Y') }}
                                    </span>
                                @endif
                            </div>
                        </a>

                        <!-- Cuerpo de la tarjeta -->
                        <div class="p-5 sm:p-6 flex-1 flex flex-col justify-between">
                            <div>
                                <!-- Metadatos superiores: fecha, tiempo de lectura y adjuntos -->
                                <div class="flex items-center gap-2 text-xs text-base-content/65 font-medium tabular-nums mb-2.5">
                                    <time datetime="{{ $post->created_at->toDateString() }}">
                                        {{ $post->created_at->translatedFormat('d M, Y') }}
                                    </time>
                                    <span class="text-base-content/30" aria-hidden="true">•</span>
                                    <span>{{ $post->reading_time }} min de lectura</span>
                                    @php
                                        $attachmentsCount = $post->media->where('collection_name', 'attachments')->count();
                                    @endphp
                                    @if($attachmentsCount > 0)
                                        <span class="text-base-content/30" aria-hidden="true">•</span>
                                        <span class="inline-flex items-center gap-1 text-primary font-medium" title="{{ $attachmentsCount }} {{ $attachmentsCount === 1 ? 'adjunto disponible' : 'adjuntos disponibles' }}">
                                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.37l-9.849 9.85a1.5 1.5 0 0 1-2.122-2.123l8.788-8.788" />
                                            </svg>
                                            <span>{{ $attachmentsCount }}</span>
                                        </span>
                                    @endif
                                </div>

                                <!-- Título con rol tipográfico jerarquizado -->
                                <h2 class="font-display font-bold text-lg sm:text-xl text-base-content leading-snug tracking-tight text-balance mb-2.5 group-hover:text-primary transition-colors">
                                    <a href="{{ route('publications.show', ['type' => $post->type_slug, 'slug' => $post->slug]) }}"
                                       class="focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 rounded-xs focus-visible:outline-hidden">
                                        {{ $post->title }}
                                    </a>
                                </h2>

                                <!-- Extracto legible con ritmo y wrapping cuidado -->
                                <p class="text-sm text-base-content/75 leading-relaxed text-pretty line-clamp-3 mb-4">
                                    {{ Str::limit(strip_tags($post->content), 130) }}
                                </p>
                            </div>

                            <!-- Footer de tarjeta con autor y enlace de acción -->
                            <div class="pt-4 border-t border-base-200/80 flex items-center justify-between text-xs mt-auto">
                                <div class="flex items-center gap-2 min-w-0 text-base-content/70">
                                    <div class="avatar placeholder shrink-0" aria-hidden="true">
                                        <div class="bg-primary/10 text-primary rounded-full w-6 h-6 text-[11px] font-semibold flex items-center justify-center">
                                            <span>{{ strtoupper(substr($post->author?->name ?? 'S', 0, 1)) }}</span>
                                        </div>
                                    </div>
                                    <span class="truncate font-medium text-base-content/80 text-xs">
                                        {{ $post->author?->name ?? 'OST-SIUT' }}
                                    </span>
                                </div>

                                <a href="{{ route('publications.show', ['type' => $post->type_slug, 'slug' => $post->slug]) }}"
                                   class="inline-flex items-center gap-1 font-semibold text-xs text-primary hover:text-primary/80 transition-colors shrink-0 group/link"
                                   aria-label="Leer más: {{ $post->title }}">
                                    <span>{{ in_array($post->type, [\App\Enums\PostType::Formato, \App\Enums\PostType::Contratos], true) ? 'Descargar' : 'Leer más' }}</span>
                                    <svg class="size-3.5 transition-transform duration-200 group-hover/link:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <!-- Paginación estilizada y accesible -->
            <div class="mt-12">
                {{ $posts->links('landing.partials.pagination') }}
            </div>
        @else
            <!-- Estado Vacío depurado -->
            <div class="text-center py-16 px-6 bg-base-100 rounded-2xl border border-base-200 max-w-md mx-auto">
                <div class="size-14 rounded-full bg-primary/10 text-primary mx-auto flex items-center justify-center mb-4">
                    <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                </div>
                <h2 class="font-display font-bold text-lg text-base-content mb-1.5 text-balance">
                    No se encontraron {{ $currentType ? strtolower($currentType->getPluralLabel()) : 'publicaciones' }}
                </h2>
                <p class="text-xs sm:text-sm text-base-content/70 mb-6 leading-relaxed text-pretty">
                    @if(request('search'))
                        No hay publicaciones que coincidan con "{{ request('search') }}". Intenta con términos más generales o verifica la ortografía.
                    @else
                        Actualmente no hay publicaciones disponibles en esta sección.
                    @endif
                </p>
                <div class="flex flex-wrap items-center justify-center gap-2">
                    @if(request('search'))
                        <a href="{{ $currentType ? route('publications.type', $currentType->getSlug()) : route('publications.index') }}" class="btn btn-sm btn-outline rounded-lg">
                            Limpiar búsqueda
                        </a>
                    @endif
                    @if($currentType || request('search'))
                        <a href="{{ route('publications.index') }}" class="btn btn-sm btn-primary rounded-lg">
                            Ver todas las publicaciones
                        </a>
                    @endif
                </div>
            </div>
        @endif

    </div>
</section>
@endsection
