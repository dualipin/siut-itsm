@extends('landing.layout')

@section('content')
<section class="py-12 md:py-20 bg-base-100 min-h-screen text-base-content">
    <div class="container mx-auto px-4 max-w-5xl">

        <!-- Hero / Encabezado de la Sección -->
        <div class="flex flex-col md:flex-row items-center justify-between gap-8 mb-12 animate-fade-in-down">
            <div class="max-w-2xl text-center md:text-left space-y-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-primary/10 text-primary mb-1">
                    <i class="bi bi-shield-check text-xs"></i>
                    Transparencia Pública
                </span>
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-black tracking-tight text-base-content">
                    {{ $type->getLabel() }}
                </h1>
                <p class="text-base-content/70 text-sm sm:text-base leading-relaxed">
                    @if($type->value === 'financiero')
                        Consulta los balances contables, presupuestos de operación anuales y dictámenes de egresos del sindicato.
                    @elseif($type->value === 'normativo')
                        Consulta los reglamentos internos, estatutos vigentes y normas que rigen el funcionamiento del sindicato.
                    @elseif($type->value === 'convenio')
                        Consulta los convenios de colaboración, contratos colectivos de trabajo y revisiones salariales con validez jurídica.
                    @elseif($type->value === 'acta')
                        Consulta las actas oficiales, minutas y resoluciones emanadas de nuestras asambleas generales ordinarias y extraordinarias.
                    @else
                        Consulta otros documentos informativos, archivos históricos y de transparencia general de la organización.
                    @endif
                </p>
            </div>
            
            <!-- Icono decorativo según el tipo -->
            <div class="hidden md:block shrink-0 animate-bounce animate-iteration-count-infinite animate-duration-[5s]">
                <div class="p-6 bg-primary/5 rounded-3xl border border-primary/10">
                    @php
                        $iconClass = match ($type->value) {
                            'financiero' => 'bi-cash-coin',
                            'normativo' => 'bi-journal-bookmark',
                            'convenio' => 'bi-file-earmark-handshake',
                            'acta' => 'bi-file-earmark-check',
                            default => 'bi-folder2-open',
                        };
                    @endphp
                    <i class="bi {{ $iconClass }} text-primary/30" style="font-size: 4.5rem; line-height: 1;"></i>
                </div>
            </div>
        </div>

        <!-- Alerta / Nota de Transparencia -->
        <div class="alert bg-base-200 border-base-300 rounded-2xl mb-8 flex items-start gap-3 shadow-xs animate-fade-in-up">
            <i class="bi bi-info-circle text-primary text-lg mt-0.5 shrink-0"></i>
            <div class="text-xs sm:text-sm leading-relaxed text-base-content/80">
                <strong>Nota de Acceso:</strong> En cumplimiento de nuestras políticas de transparencia, listamos la totalidad de los registros organizacionales en esta categoría. No obstante, el acceso y descarga directa de los archivos adjuntos queda estrictamente limitado a aquellos registros que hayan sido formalmente autorizados y declarados como <strong>Publicados</strong>.
            </div>
        </div>

        <!-- Listado Agrupado por Año y Periodo -->
        @if($groupedRecords->count() > 0)
            <div class="space-y-4 animate-fade-in-up animate-delay-200">
                @foreach($groupedRecords as $year => $periods)
                    <!-- Acordeón de Año (Collapse de daisyUI) -->
                    <div class="collapse collapse-arrow bg-base-100 border border-base-300 rounded-2xl shadow-xs">
                        <input type="checkbox" name="year-accordion" id="year-{{ $year }}" {{ $loop->first ? 'checked' : '' }} />
                        
                        <div class="collapse-title flex items-center justify-between pr-12 py-4">
                            <div class="flex items-center gap-3">
                                <span class="bg-primary/15 text-primary text-sm font-bold px-3 py-1 rounded-xl">
                                    {{ $year }}
                                </span>
                                <span class="text-sm font-semibold text-base-content/70">
                                    ({{ $periods->flatten()->count() }} {{ $periods->flatten()->count() === 1 ? 'registro' : 'registros' }})
                                </span>
                            </div>
                        </div>

                        <div class="collapse-content px-4 sm:px-6 pb-6 pt-2 space-y-4 border-t border-base-200/50">
                            @foreach($periods as $period => $recordsList)
                                <!-- Acordeón de Periodo/Mes (Collapse de daisyUI) -->
                                <div class="collapse collapse-plus bg-base-200/40 border border-base-200 rounded-xl">
                                    <input type="checkbox" name="period-accordion-{{ $year }}" id="period-{{ $year }}-{{ \Illuminate\Support\Str::slug($period) }}" checked />
                                    
                                    <div class="collapse-title py-3 px-4 flex items-center gap-2">
                                        <i class="bi bi-calendar3 text-primary/70 text-sm"></i>
                                        <span class="text-sm font-bold text-base-content/80">
                                            {{ $period }}
                                        </span>
                                        <span class="badge badge-sm badge-neutral/10 font-semibold text-base-content/60 border-0">
                                            {{ $recordsList->count() }}
                                        </span>
                                    </div>

                                    <div class="collapse-content px-4 pb-4 pt-1">
                                        <div class="space-y-3 mt-2">
                                            @foreach($recordsList as $record)
                                                <!-- Fila del Registro -->
                                                <div class="bg-base-100 border border-base-300 rounded-xl p-4 shadow-2xs hover:shadow-xs transition-all flex flex-col md:flex-row md:items-center justify-between gap-4">
                                                    
                                                    <!-- Información del Registro -->
                                                    <div class="space-y-1.5 max-w-3xl">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <h3 class="text-sm sm:text-base font-bold text-base-content">
                                                                {{ $record->name }}
                                                            </h3>
                                                            <!-- Badge de Estado de Transparencia -->
                                                            @if($record->isPublished())
                                                                <span class="badge badge-xs badge-success gap-1 font-semibold py-1.5 px-2 rounded-lg">
                                                                    <span class="w-1.5 h-1.5 rounded-full bg-success-content animate-pulse"></span>
                                                                    Publicado
                                                                </span>
                                                            @else
                                                                <span class="badge badge-xs badge-neutral/20 text-base-content/60 border-0 gap-1 font-semibold py-1.5 px-2 rounded-lg">
                                                                    <i class="bi bi-lock-fill text-[10px]"></i>
                                                                    {{ $record->status->getLabel() }}
                                                                </span>
                                                            @endif
                                                        </div>

                                                        @if($record->summary)
                                                            <p class="text-xs sm:text-sm text-base-content/70 leading-relaxed">
                                                                {{ $record->summary }}
                                                            </p>
                                                        @endif

                                                        @if($record->observations)
                                                            <div class="text-xs text-base-content/50 italic flex items-start gap-1">
                                                                <i class="bi bi-chat-left-text shrink-0 mt-0.5 text-[10px]"></i>
                                                                <span>Obs: {{ $record->observations }}</span>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <!-- Botón de Descarga / Estado Cerrado -->
                                                    <div class="flex flex-col items-start md:items-end justify-center shrink-0 border-t md:border-t-0 pt-3 md:pt-0 border-base-200">
                                                        @if($record->isPublished())
                                                            @if($record->documents->count() > 0)
                                                                <div class="space-y-2 w-full md:w-auto">
                                                                    @foreach($record->documents as $document)
                                                                        <div class="flex items-center justify-between gap-3 text-xs">
                                                                            <span class="text-base-content/70 truncate max-w-48 font-medium" title="{{ $document->name }}">
                                                                                <i class="bi bi-file-earmark-pdf text-red-500 mr-1"></i>
                                                                                {{ $document->name }}
                                                                            </span>
                                                                            @if($document->is_public)
                                                                                <a href="{{ route('transparency.documents.download', $document) }}" 
                                                                                   class="btn btn-xs btn-primary rounded-lg gap-1.5 shadow-2xs font-medium"
                                                                                   title="Descargar archivo">
                                                                                    <i class="bi bi-arrow-down-tray"></i>
                                                                                    Descargar
                                                                                </a>
                                                                            @else
                                                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-base-content/40 bg-base-200 py-1 px-2 rounded-lg"
                                                                                      title="Documento de acceso interno o confidencial">
                                                                                    <i class="bi bi-lock-fill"></i>
                                                                                    Restringido
                                                                                </span>
                                                                            @endif
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            @else
                                                                <span class="text-xs text-base-content/40 italic">
                                                                    Sin documentos adjuntos
                                                                </span>
                                                            @endif
                                                        @else
                                                            <div class="flex items-center gap-1.5 text-xs text-base-content/45 bg-base-200/50 py-1.5 px-3 rounded-lg border border-base-300/40">
                                                                <i class="bi bi-lock-fill text-sm"></i>
                                                                <span class="font-medium">No disponible para descarga</span>
                                                            </div>
                                                        @endif
                                                    </div>

                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                    </div>
                @endforeach
            </div>
        @else
            <!-- Estado Vacío -->
            <div class="card bg-base-100 border border-base-300/80 rounded-3xl p-12 max-w-md mx-auto text-center shadow-xs animate-fade-in-up">
                <div class="mx-auto w-16 h-16 bg-base-200 rounded-full flex items-center justify-center mb-4">
                    <i class="bi bi-folder-x text-3xl text-base-content/40"></i>
                </div>
                <h3 class="text-lg font-bold text-base-content mb-1">
                    Sin registros disponibles
                </h3>
                <p class="text-sm text-base-content/60 mb-6">
                    Por el momento no se han ingresado archivos ni registros de transparencia pública para la categoría de <strong>{{ $type->getLabel() }}</strong>.
                </p>
                <a href="{{ route('home') }}" class="btn btn-sm btn-primary rounded-xl mx-auto">
                    <i class="bi bi-house-door mr-1"></i>
                    Volver al Inicio
                </a>
            </div>
        @endif

    </div>
</section>
@endsection
