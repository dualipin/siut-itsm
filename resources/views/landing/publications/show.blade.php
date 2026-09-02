@extends('landing.layout')

@section('content')
<article class="py-10 md:py-16 bg-base-100 min-h-screen text-base-content">
    <div class="container mx-auto px-4 max-w-5xl">

        <!-- Breadcrumbs -->
        <nav class="breadcrumbs text-xs sm:text-sm mb-8 text-base-content/70 animate-fade-in">
            <ul>
                <li><a href="{{ route('home') }}" class="hover:text-primary"><i class="bi bi-house mr-1"></i> Inicio</a></li>
                <li><a href="{{ route('publications.index') }}" class="hover:text-primary">Publicaciones</a></li>
                <li><a href="{{ route('publications.type', $postType->getSlug()) }}" class="hover:text-primary">{{ $postType->getPluralLabel() }}</a></li>
                <li class="text-base-content/90 font-medium truncate max-w-xs">{{ Str::limit($post->title, 35) }}</li>
            </ul>
        </nav>

        <!-- Cabecera de la Publicación -->
        <header class="mb-10 animate-fade-in-down">
            <!-- Badges e Indicadores -->
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <a href="{{ route('publications.type', $postType->getSlug()) }}"
                   class="badge badge-md font-semibold rounded-lg shadow-xs {{ $postType->getBadgeClass() }}">
                    <i class="bi {{ $postType->getIcon() }} mr-1.5"></i>
                    {{ $postType->getLabel() }}
                </a>

                @if($post->type === \App\Enums\PostType::Aviso && $post->expires_at)
                    <span class="badge badge-md badge-outline bg-base-200/80 text-warning font-semibold">
                        <i class="bi bi-calendar-event mr-1.5"></i> Vigente hasta: {{ $post->expires_at->format('d/m/Y') }}
                    </span>
                @endif
            </div>

            <!-- Título Principal -->
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-black tracking-tight text-base-content leading-tight mb-6">
                {{ $post->title }}
            </h1>

            <!-- Barra de Metadatos y Autor -->
            <div class="flex flex-wrap items-center justify-between gap-4 py-4 border-y border-base-200 text-xs sm:text-sm text-base-content/70">
                <div class="flex items-center gap-3">
                    <div class="avatar placeholder">
                        <div class="bg-primary text-primary-content rounded-full w-10 font-bold text-sm shadow-xs">
                            <span>{{ strtoupper(substr($post->author?->name ?? 'S', 0, 1)) }}</span>
                        </div>
                    </div>
                    <div>
                        <div class="font-bold text-base-content">{{ $post->author?->name ?? 'Comité Ejecutivo OST-SIUT' }}</div>
                        <div class="text-xs text-base-content/60">Organización Sindical</div>
                    </div>
                </div>

                <div class="flex items-center gap-4 text-xs">
                    <span class="inline-flex items-center gap-1.5">
                        <i class="bi bi-calendar3 text-primary"></i>
                        {{ $post->created_at->translatedFormat('d \d\e F, Y') }}
                    </span>
                    <span>•</span>
                    <span class="inline-flex items-center gap-1.5">
                        <i class="bi bi-clock text-primary"></i>
                        {{ $post->reading_time }} min de lectura
                    </span>
                </div>
            </div>
        </header>

        <!-- Imagen Destacada -->
        @if($post->thumbnail_url)
            <div class="mb-10 rounded-3xl overflow-hidden shadow-lg border border-base-300/80 max-h-[500px] bg-base-200 animate-fade-in">
                <img src="{{ $post->thumbnail_url }}"
                     alt="{{ $post->title }}"
                     class="w-full h-full object-cover max-h-[500px]" />
            </div>
        @endif

        <!-- Contenido del Artículo -->
        <div class="mb-12">
            <div class="prose prose-lg max-w-none text-base-content/90 leading-relaxed
                        prose-headings:font-black prose-headings:text-base-content
                        prose-p:mb-5 prose-p:leading-relaxed
                        prose-a:text-primary prose-a:underline hover:prose-a:text-primary-focus
                        prose-img:rounded-2xl prose-img:shadow-md
                        prose-blockquote:border-l-primary prose-blockquote:bg-base-200/40 prose-blockquote:p-4 prose-blockquote:rounded-r-2xl
                        prose-ul:list-disc prose-ol:list-decimal">
                {!! $post->content !!}
            </div>
        </div>

        <!-- Archivos Adjuntos Descargables -->
        @php
            $attachments = $post->media->where('collection_name', 'attachments');
        @endphp

        @if($attachments->isNotEmpty())
            <div class="mb-12 bg-base-200/50 rounded-3xl p-6 sm:p-8 border border-base-300/80 animate-fade-in-up">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center">
                        <i class="bi bi-file-earmark-arrow-down text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-base-content">Archivos Adjuntos y Documentación</h2>
                        <p class="text-xs text-base-content/60">Descarga los documentos oficiales asociados a esta publicación</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($attachments as $attachment)
                        <div class="flex items-center justify-between p-4 bg-base-100 rounded-2xl border border-base-300 hover:border-primary/50 transition-all shadow-xs group">
                            <div class="flex items-center gap-3 min-w-0 pr-3">
                                <div class="w-10 h-10 rounded-xl bg-base-200 flex items-center justify-center text-primary shrink-0 group-hover:scale-110 transition-transform">
                                    @php
                                        $ext = strtolower(pathinfo($attachment->file_name, PATHINFO_EXTENSION));
                                        $icon = match($ext) {
                                            'pdf' => 'bi-file-earmark-pdf text-error',
                                            'doc', 'docx' => 'bi-file-earmark-word text-info',
                                            'xls', 'xlsx' => 'bi-file-earmark-excel text-success',
                                            'zip', 'rar' => 'bi-file-earmark-zip text-warning',
                                            'jpg', 'jpeg', 'png', 'webp' => 'bi-file-earmark-image text-secondary',
                                            default => 'bi-file-earmark-text text-primary',
                                        };
                                    @endphp
                                    <i class="bi {{ $icon }} text-xl"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-base-content truncate group-hover:text-primary transition-colors" title="{{ $attachment->file_name }}">
                                        {{ $attachment->file_name }}
                                    </div>
                                    <div class="text-xs text-base-content/50">
                                        {{ $attachment->human_readable_size }}
                                    </div>
                                </div>
                            </div>
                            <a href="{{ route('publications.attachment.download', $attachment->id) }}"
                               class="btn btn-sm btn-primary rounded-xl shrink-0 gap-1.5 shadow-xs">
                                <i class="bi bi-download text-xs"></i>
                                <span class="hidden sm:inline">Descargar</span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Barra para Compartir y Acciones -->
        <div class="flex flex-wrap items-center justify-between gap-4 py-6 border-t border-b border-base-200 mb-12">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-wider text-base-content/60 mr-2">Compartir:</span>
                
                <!-- WhatsApp -->
                <a href="https://api.whatsapp.com/send?text={{ urlencode($post->title . ' - ' . url()->current()) }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="btn btn-sm btn-circle btn-soft text-success hover:bg-success hover:text-success-content transition-all"
                   title="Compartir por WhatsApp">
                    <i class="bi bi-whatsapp text-sm"></i>
                </a>

                <!-- Facebook -->
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="btn btn-sm btn-circle btn-soft text-info hover:bg-info hover:text-info-content transition-all"
                   title="Compartir en Facebook">
                    <i class="bi bi-facebook text-sm"></i>
                </a>

                <!-- Twitter / X -->
                <a href="https://twitter.com/intent/tweet?text={{ urlencode($post->title) }}&url={{ urlencode(url()->current()) }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="btn btn-sm btn-circle btn-soft text-neutral hover:bg-neutral hover:text-neutral-content transition-all"
                   title="Compartir en X">
                    <i class="bi bi-twitter-x text-sm"></i>
                </a>

                <!-- Copiar Enlace -->
                <button type="button"
                        onclick="navigator.clipboard.writeText(window.location.href); alert('¡Enlace copiado al portapapeles!');"
                        class="btn btn-sm btn-circle btn-soft text-base-content hover:bg-base-300 transition-all"
                        title="Copiar enlace">
                    <i class="bi bi-link-45deg text-base"></i>
                </button>
            </div>

            <div>
                <a href="{{ route('publications.type', $postType->getSlug()) }}" class="btn btn-sm btn-outline rounded-full gap-1.5">
                    <i class="bi bi-arrow-left text-xs"></i>
                    <span>Volver a {{ $postType->getPluralLabel() }}</span>
                </a>
            </div>
        </div>

        <!-- Banner de Ayuda / Dudas -->
        <div class="mb-14 p-6 sm:p-8 bg-gradient-to-r from-primary/10 via-primary/5 to-transparent border border-primary/20 rounded-3xl flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-4 text-center sm:text-left">
                <div class="w-12 h-12 rounded-2xl bg-primary text-primary-content flex items-center justify-center shrink-0 shadow-md">
                    <i class="bi bi-chat-dots-fill text-2xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base sm:text-lg text-base-content">¿Tienes alguna duda o consulta sobre este tema?</h3>
                    <p class="text-xs sm:text-sm text-base-content/70">Comunícate directamente con el sindicato o consulta las preguntas resueltas.</p>
                </div>
            </div>
            <a href="{{ route('inquiries.index') }}" class="btn btn-primary rounded-full px-6 font-semibold shrink-0 shadow-sm">
                Centro de Dudas
            </a>
        </div>

        <!-- Publicaciones Relacionadas -->
        @if($relatedPosts->isNotEmpty())
            <div class="border-t border-base-200 pt-10">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-primary">Continúa leyendo</span>
                        <h2 class="text-2xl font-black text-base-content">Más en {{ $postType->getPluralLabel() }}</h2>
                    </div>
                    <a href="{{ route('publications.type', $postType->getSlug()) }}" class="text-xs font-bold text-primary hover:underline inline-flex items-center gap-1">
                        Ver todas <i class="bi bi-chevron-right text-[10px]"></i>
                    </a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                    @foreach($relatedPosts as $related)
                        <article class="card bg-base-100 border border-base-300/80 rounded-2xl shadow-xs hover:shadow-md transition-all duration-300 flex flex-col justify-between group overflow-hidden">
                            <div class="h-36 bg-base-200 overflow-hidden relative">
                                <img src="{{ $related->thumbnail_url }}"
                                     alt="{{ $related->title }}"
                                     loading="lazy"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                            </div>
                            <div class="p-4 flex-1 flex flex-col justify-between">
                                <div>
                                    <div class="text-[11px] text-base-content/60 mb-1.5">
                                        {{ $related->created_at->translatedFormat('d M, Y') }}
                                    </div>
                                    <h3 class="font-bold text-sm text-base-content line-clamp-2 mb-2 group-hover:text-primary transition-colors">
                                        <a href="{{ route('publications.show', ['type' => $related->type_slug, 'slug' => $related->slug]) }}">
                                            {{ $related->title }}
                                        </a>
                                    </h3>
                                </div>
                                <a href="{{ route('publications.show', ['type' => $related->type_slug, 'slug' => $related->slug]) }}"
                                   class="text-xs font-bold text-primary inline-flex items-center gap-1 hover:gap-1.5 transition-all mt-3">
                                    <span>Leer más</span>
                                    <i class="bi bi-arrow-right text-[10px]"></i>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</article>
@endsection
