@extends('landing.layout')

@section('content')
<section class="py-10 md:py-16 bg-base-100 min-h-screen text-base-content">
    <div class="container mx-auto px-4 max-w-7xl">

        <!-- Hero de la sección -->
        <div class="max-w-3xl mx-auto text-center mb-10 animate-fade-in-down">
            <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-primary/10 text-primary mb-3">
                @if($currentType)
                    <i class="bi {{ $currentType->getIcon() }} text-sm"></i>
                    {{ $currentType->getPluralLabel() }}
                @else
                    <i class="bi bi-collection text-sm"></i>
                    Portal Informativo Sindical
                @endif
            </span>
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-black tracking-tight mb-4 text-base-content">
                {{ $title }}
            </h1>
            <p class="text-base-content/70 text-sm sm:text-base md:text-lg leading-relaxed">
                {{ $subtitle }}
            </p>
        </div>

        <!-- Barra de Búsqueda y Filtros de Tipos -->
        <div class="mb-10 space-y-5">
            <!-- Buscador -->
            <form action="{{ $currentType ? route('publications.type', $currentType->getSlug()) : route('publications.index') }}" method="GET" class="max-w-2xl mx-auto">
                <div class="relative">
                    <input type="text"
                           name="search"
                           value="{{ request('search') }}"
                           placeholder="Buscar por título, palabra clave o contenido..."
                           class="input input-bordered w-full rounded-2xl pl-12 pr-28 py-3.5 bg-base-200/50 focus:bg-base-100 focus:border-primary text-sm shadow-xs transition-all" />
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-base-content/40">
                        <i class="bi bi-search text-base"></i>
                    </span>
                    @if(request('search'))
                        <a href="{{ $currentType ? route('publications.type', $currentType->getSlug()) : route('publications.index') }}"
                           class="absolute right-24 top-1/2 -translate-y-1/2 text-base-content/40 hover:text-base-content text-xs">
                            <i class="bi bi-x-circle-fill text-sm"></i>
                        </a>
                    @endif
                    <button type="submit" class="btn btn-sm btn-primary rounded-xl absolute right-2.5 top-1/2 -translate-y-1/2 text-xs font-semibold">
                        Buscar
                    </button>
                </div>
            </form>

            <!-- Píldoras de Categoría / PostType -->
            <div class="flex items-center justify-center gap-2 flex-wrap">
                <a href="{{ route('publications.index', ['search' => request('search')]) }}"
                   class="px-4 py-2 rounded-full text-xs font-semibold transition-all border inline-flex items-center gap-1.5
                   {{ ! $currentType
                       ? 'bg-primary text-primary-content border-primary shadow-sm'
                       : 'bg-base-200/70 text-base-content/80 border-base-300 hover:bg-base-200 hover:text-base-content' }}">
                    <i class="bi bi-grid text-xs"></i>
                    <span>Todas</span>
                </a>

                @foreach(\App\Enums\PostType::cases() as $type)
                    <a href="{{ route('publications.type', ['type' => $type->getSlug(), 'search' => request('search')]) }}"
                       class="px-4 py-2 rounded-full text-xs font-semibold transition-all border inline-flex items-center gap-1.5
                       {{ ($currentType === $type)
                           ? 'bg-primary text-primary-content border-primary shadow-sm'
                           : 'bg-base-200/70 text-base-content/80 border-base-300 hover:bg-base-200 hover:text-base-content' }}">
                        <i class="bi {{ $type->getIcon() }} text-xs"></i>
                        <span>{{ $type->getPluralLabel() }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Indicador de resultados activos si hay búsqueda -->
        @if(request('search'))
            <div class="max-w-7xl mx-auto mb-6 flex items-center justify-between bg-base-200/50 px-4 py-2.5 rounded-2xl border border-base-300/60 text-xs text-base-content/80">
                <span>
                    Mostrando resultados para: <strong class="text-base-content font-bold">"{{ request('search') }}"</strong>
                    ({{ $posts->total() }} encontrados)
                </span>
                <a href="{{ $currentType ? route('publications.type', $currentType->getSlug()) : route('publications.index') }}" class="text-primary hover:underline font-semibold flex items-center gap-1">
                    <i class="bi bi-arrow-counterclockwise"></i> Limpiar filtro
                </a>
            </div>
        @endif

        <!-- Listado de Publicaciones (Grid) -->
        @if($posts->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($posts as $index => $post)
                    <article class="card bg-base-100 border border-base-300/80 rounded-3xl shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between group overflow-hidden animate-fade-in-up animate-delay-{{ min(500, ($index % 6) * 100) }}">
                        
                        <!-- Encabezado / Imagen de la tarjeta -->
                        <div class="relative overflow-hidden bg-base-200 h-48 sm:h-52">
                            <img src="{{ $post->thumbnail_url }}"
                                 alt="{{ $post->title }}"
                                 loading="lazy"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                            
                            <!-- Badges flotantes sobre la imagen -->
                            <div class="absolute top-3 left-3 flex flex-wrap items-center gap-1.5">
                                <span class="badge badge-sm font-semibold rounded-lg shadow-xs {{ $post->type?->getBadgeClass() ?? 'badge-neutral' }}">
                                    <i class="bi {{ $post->type?->getIcon() ?? 'bi-file-text' }} mr-1"></i>
                                    {{ $post->type?->getLabel() ?? 'Publicación' }}
                                </span>

                                @if($post->type === \App\Enums\PostType::Aviso && $post->expires_at)
                                    <span class="badge badge-sm badge-outline bg-base-100/90 text-warning font-medium backdrop-blur-xs">
                                        <i class="bi bi-clock mr-1"></i> Vence: {{ $post->expires_at->format('d/m/Y') }}
                                    </span>
                                @endif
                            </div>

                            @if($post->media->where('collection_name', 'attachments')->count() > 0)
                                <div class="absolute bottom-3 right-3 badge badge-sm bg-neutral/80 text-neutral-content backdrop-blur-xs font-semibold">
                                    <i class="bi bi-paperclip mr-1"></i>
                                    {{ $post->media->where('collection_name', 'attachments')->count() }} {{ $post->media->where('collection_name', 'attachments')->count() === 1 ? 'adjunto' : 'adjuntos' }}
                                </div>
                            @endif
                        </div>

                        <!-- Cuerpo de la tarjeta -->
                        <div class="p-6 flex-1 flex flex-col justify-between">
                            <div>
                                <!-- Metadatos superiores: Fecha y tiempo de lectura -->
                                <div class="flex items-center gap-3 text-xs text-base-content/60 mb-2.5">
                                    <span class="inline-flex items-center gap-1">
                                        <i class="bi bi-calendar3"></i>
                                        {{ $post->created_at->translatedFormat('d M, Y') }}
                                    </span>
                                    <span>•</span>
                                    <span class="inline-flex items-center gap-1">
                                        <i class="bi bi-book"></i>
                                        {{ $post->reading_time }} min de lectura
                                    </span>
                                </div>

                                <!-- Título -->
                                <h3 class="font-bold text-lg text-base-content leading-snug line-clamp-2 mb-2 group-hover:text-primary transition-colors">
                                    <a href="{{ route('publications.show', ['type' => $post->type_slug, 'slug' => $post->slug]) }}">
                                        {{ $post->title }}
                                    </a>
                                </h3>

                                <!-- Extracto -->
                                <p class="text-xs md:text-sm text-base-content/70 line-clamp-3 leading-relaxed mb-4">
                                    {{ Str::limit(strip_tags($post->content), 130) }}
                                </p>
                            </div>

                            <!-- Footer de tarjeta con autor y enlace -->
                            <div class="pt-4 border-t border-base-200 flex items-center justify-between text-xs mt-auto">
                                <div class="flex items-center gap-2 text-base-content/70">
                                    <div class="avatar placeholder">
                                        <div class="bg-primary/10 text-primary rounded-full w-6 text-[10px] font-bold">
                                            <span>{{ strtoupper(substr($post->author?->name ?? 'S', 0, 1)) }}</span>
                                        </div>
                                    </div>
                                    <span class="truncate max-w-[130px] font-medium">
                                        {{ $post->author?->name ?? 'OST-SIUT' }}
                                    </span>
                                </div>

                                <a href="{{ route('publications.show', ['type' => $post->type_slug, 'slug' => $post->slug]) }}"
                                   class="font-bold text-primary inline-flex items-center gap-1 hover:gap-1.5 transition-all group-hover:underline">
                                    <span>{{ ($post->type === \App\Enums\PostType::Formato || $post->type === \App\Enums\PostType::Contratos) ? 'Descargar' : 'Leer más' }}</span>
                                    <i class="bi bi-arrow-right text-xs"></i>
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <!-- Paginación -->
            <div class="mt-12 flex justify-center">
                {{ $posts->links() }}
            </div>
        @else
            <!-- Estado Vacío Estilizado -->
            <div class="text-center py-16 bg-base-200/30 rounded-3xl border border-base-300/60 max-w-lg mx-auto p-8 animate-fade-in">
                <div class="w-16 h-16 rounded-full bg-primary/10 text-primary mx-auto flex items-center justify-center mb-4">
                    <i class="bi {{ $currentType ? $currentType->getIcon() : 'bi-folder2-open' }} text-3xl"></i>
                </div>
                <h3 class="font-bold text-lg text-base-content mb-1">
                    No se encontraron {{ $currentType ? strtolower($currentType->getPluralLabel()) : 'publicaciones' }}
                </h3>
                <p class="text-xs sm:text-sm text-base-content/60 mb-6 leading-relaxed">
                    @if(request('search'))
                        No hay resultados que coincidan con "{{ request('search') }}". Intenta con términos más generales o verifica la ortografía.
                    @else
                        Actualmente no hay publicaciones disponibles en esta sección. Pronto publicaremos novedades aquí.
                    @endif
                </p>
                <div class="flex items-center justify-center gap-3">
                    @if(request('search'))
                        <a href="{{ $currentType ? route('publications.type', $currentType->getSlug()) : route('publications.index') }}" class="btn btn-sm btn-outline rounded-full px-4">
                            Limpiar búsqueda
                        </a>
                    @endif
                    <a href="{{ route('publications.index') }}" class="btn btn-sm btn-primary rounded-full px-5">
                        Ver todas las publicaciones
                    </a>
                </div>
            </div>
        @endif

    </div>
</section>
@endsection
