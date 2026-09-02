@extends('landing.layout')

@section('content')
<section class="py-12 md:py-20 bg-base-100 min-h-screen text-base-content">
    <div class="container mx-auto px-4 max-w-4xl">
        
        <!-- Botón Volver -->
        <div class="mb-8">
            <a href="{{ route('inquiries.index') }}"
               class="inline-flex items-center gap-2 text-xs md:text-sm font-semibold text-base-content/70 hover:text-primary transition-colors">
                <i class="bi bi-arrow-left text-sm"></i>
                <span>Volver a todas las dudas y consultas</span>
            </a>
        </div>

        <!-- TARJETA PRINCIPAL: La Duda (Server-rendered para SEO) -->
        <article class="bg-base-100 rounded-3xl border border-base-300/80 shadow-md p-6 sm:p-8 md:p-10 mb-10 relative overflow-hidden">
            <!-- Barra superior de metadatos -->
            <div class="flex items-center justify-between flex-wrap gap-3 pb-6 border-b border-base-200">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="badge badge-primary font-bold rounded-lg text-xs px-3 py-1">
                        {{ $inquiry->category ?: 'General' }}
                    </span>
                    @if($inquiry->is_public)
                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-success bg-success/10 px-2.5 py-1 rounded-lg">
                            <i class="bi bi-globe"></i>
                            Pública
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-warning bg-warning/10 px-2.5 py-1 rounded-lg">
                            <i class="bi bi-lock"></i>
                            Privada
                        </span>
                    @endif
                    <span class="badge badge-ghost font-medium text-[11px] rounded-lg">
                        Estado: {{ $inquiry->status->getLabel() }}
                    </span>
                </div>

                <div class="flex items-center gap-3 text-xs text-base-content/60">
                    <span class="flex items-center gap-1">
                        <i class="bi bi-calendar3"></i>
                        {{ $inquiry->created_at->format('d/m/Y H:i') }}
                    </span>
                    <span class="flex items-center gap-1">
                        <i class="bi bi-eye"></i>
                        {{ $inquiry->views_count }} vistas
                    </span>
                </div>
            </div>

            <!-- Título H1 para SEO -->
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-base-content tracking-tight mt-6 mb-4">
                {{ $inquiry->title }}
            </h1>

            <!-- Autor -->
            <div class="flex items-center gap-3 mb-6 p-3 rounded-2xl bg-base-200/50 w-fit text-xs">
                <div class="size-7 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold">
                    <i class="bi bi-person text-sm"></i>
                </div>
                <div>
                    <span class="font-bold text-base-content">{{ $inquiry->getAuthorDisplayName() }}</span>
                    <span class="text-base-content/60 ml-1.5">({{ $inquiry->user ? 'Agremiado registrado' : 'Visitante' }})</span>
                </div>
            </div>

            <!-- Cuerpo de la duda -->
            <div class="prose prose-sm sm:prose max-w-none text-base-content/85 leading-relaxed whitespace-pre-wrap">
                {{ $inquiry->body }}
            </div>
        </article>

        <!-- SECCIÓN: Respuestas (1 Duda -> N Respuestas) -->
        <div class="space-y-6">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <i class="bi bi-chat-dots-fill text-primary text-xl"></i>
                    <h2 class="text-xl md:text-2xl font-bold text-base-content tracking-tight">
                        Respuestas ({{ $inquiry->answers->count() }})
                    </h2>
                </div>
            </div>

            <!-- Lista de respuestas -->
            @if($inquiry->answers->count() > 0)
                <div class="space-y-6">
                    @foreach($inquiry->answers as $ans)
                        <div class="rounded-3xl border transition-all p-6 sm:p-8
                            {{ $ans->is_official
                                ? 'bg-primary/5 border-primary/30 shadow-md ring-1 ring-primary/20'
                                : 'bg-base-100 border-base-300/80 shadow-sm' }}">
                            
                            <!-- Encabezado de la respuesta -->
                            <div class="flex items-center justify-between flex-wrap gap-2 pb-4 mb-4 border-b border-base-200">
                                <div class="flex items-center gap-3">
                                    <div class="size-9 rounded-full flex items-center justify-center font-bold
                                        {{ $ans->is_official ? 'bg-primary text-white' : 'bg-base-200 text-base-content' }}">
                                        @if($ans->is_official)
                                            <i class="bi bi-shield-check text-lg"></i>
                                        @else
                                            <i class="bi bi-person text-base"></i>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-bold text-sm md:text-base text-base-content mb-0">
                                                {{ $ans->user ? ($ans->user->full_name ?: $ans->user->name) : 'Sindicato' }}
                                            </h4>
                                            @if($ans->is_official)
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-primary text-white">
                                                    <i class="bi bi-patch-check-fill"></i>
                                                    Respuesta Oficial
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-[11px] text-base-content/60 mb-0">
                                            {{ $ans->user?->category ?? 'Sindicato' }} &bull; {{ $ans->created_at->format('d/m/Y H:i') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Texto de la respuesta -->
                            <div class="prose prose-sm max-w-none text-base-content/90 leading-relaxed whitespace-pre-wrap mb-4">
                                {{ $ans->body }}
                            </div>

                            <!-- ARCHIVOS ADJUNTOS DE LA RESPUESTA (Solo las respuestas permiten adjuntos) -->
                            @if($ans->hasMedia('attachments'))
                                <div class="mt-5 pt-4 border-t border-base-200">
                                    <h5 class="text-xs font-bold uppercase tracking-wider text-base-content/60 flex items-center gap-1.5 mb-3">
                                        <i class="bi bi-paperclip text-primary text-sm"></i>
                                        Documentación y Archivos Adjuntos a esta Respuesta
                                    </h5>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        @foreach($ans->getMedia('attachments') as $media)
                                            <a href="{{ route('inquiries.attachment.download', $media) }}"
                                               target="_blank"
                                               class="flex items-center justify-between gap-3 p-3 rounded-2xl bg-base-100 hover:bg-base-200/80 border border-base-300/80 transition-all group shadow-xs">
                                                <div class="flex items-center gap-2.5 min-w-0">
                                                    <div class="p-2 rounded-xl bg-primary/10 text-primary group-hover:scale-105 transition-transform">
                                                        <i class="bi bi-file-earmark-arrow-down text-lg"></i>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="text-xs font-bold text-base-content truncate mb-0">{{ $media->file_name }}</p>
                                                        <p class="text-[10px] text-base-content/60 mb-0">{{ $media->human_readable_size }}</p>
                                                    </div>
                                                </div>
                                                <div class="p-1.5 rounded-lg text-primary hover:bg-primary/10 transition-colors">
                                                    <i class="bi bi-download text-sm"></i>
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <!-- Estado sin respuestas todavía -->
                <div class="text-center py-12 px-6 bg-base-200/40 rounded-3xl border border-base-300/60">
                    <i class="bi bi-clock-history text-3xl text-base-content/30 mb-2 block"></i>
                    <h4 class="font-bold text-sm text-base-content mb-1">Sin respuestas registradas aún</h4>
                    <p class="text-xs text-base-content/60 max-w-sm mx-auto">
                        Esta consulta está en revisión por parte de la dirigencia sindical y será respondida oficialmente en breve.
                    </p>
                </div>
            @endif
        </div>

        <!-- Card inferior de ayuda adicional -->
        <div class="mt-12 p-6 sm:p-8 rounded-3xl bg-base-200/50 border border-base-300/60 flex flex-col sm:flex-row items-center justify-between gap-6">
            <div>
                <h4 class="font-bold text-base text-base-content mb-1">¿Tienes una duda diferente?</h4>
                <p class="text-xs text-base-content/70 mb-0">
                    Puedes explorar más preguntas en el centro de dudas o ponerte en contacto directo con nuestras oficinas.
                </p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <a href="{{ route('contact') }}" class="btn btn-sm btn-outline rounded-xl">
                    Contacto directo
                </a>
                <a href="{{ route('inquiries.index') }}" class="btn btn-sm btn-primary rounded-xl">
                    Ver más dudas
                </a>
            </div>
        </div>

    </div>
</section>
@endsection
