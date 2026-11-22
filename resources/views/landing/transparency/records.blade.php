@extends('landing.layout')

@section('content')
<section class="py-12 md:py-20 bg-base-100 min-h-screen text-base-content">
    <div class="container mx-auto px-4 max-w-5xl">

        <!-- Hero / Encabezado de la Sección -->
        <div class="flex flex-col md:flex-row items-center justify-between gap-8 mb-12 animate-fade-in-down">
            <div class="max-w-2xl text-center md:text-left space-y-3">
                <h1 class="text-3xl sm:text-4xl font-display md:text-5xl font-black tracking-tight text-base-content">
                    {{ $type->getLabel() }}
                </h1>
                <p class="text-base-content/70 text-sm sm:text-base leading-relaxed">
                    {{ $type->getDescription() }}
                </p>
            </div>
            
            <!-- Icono decorativo según el tipo -->
            <div class="hidden md:block shrink-0 animate-fade-in-up">
                <div class="p-6 bg-primary/5 rounded-3xl border border-primary/10">
                    <i class="bi {{ $type->getIcon() }} text-primary/30" style="font-size: 4.5rem; line-height: 1;"></i>
                </div>
            </div>
        </div>

        <!-- Alerta / Nota de Transparencia -->
        <div class="alert bg-base-200 border-base-300 rounded-2xl mb-8 flex items-start gap-3 shadow-xs animate-fade-in-up">
            <i class="bi bi-info-circle text-primary text-lg mt-0.5 shrink-0"></i>
            <div class="text-xs sm:text-sm leading-relaxed text-base-content/80">
               En cumplimiento de nuestras políticas de transparencia, listamos la totalidad de los registros organizacionales en esta categoría. No obstante, el acceso y descarga directa de los archivos adjuntos queda estrictamente limitado a aquellos registros declarados como <strong>Público</strong>.
            </div>
        </div>

        <!-- Listado Agrupado por Año y Periodo -->
        @if($groupedRecords->count() > 0)
            <!-- Buscador instantáneo en cliente -->
            <div class="mb-6 animate-fade-in-up">
                <label for="transparency-search" class="sr-only">Buscar registros en {{ $type->getLabel() }}</label>
                <div class="input input-bordered w-full rounded-xl bg-base-200/40 focus-within:bg-base-100 focus-within:border-primary shadow-2xs flex items-center gap-2">
                    <i class="bi bi-search text-base-content/50 shrink-0" aria-hidden="true"></i>
                    <input
                        id="transparency-search"
                        type="search"
                        data-transparency-search
                        placeholder="Buscar por nombre, folio o descripción..."
                        autocomplete="off"
                        class="grow text-sm"
                    />
                    <button
                        type="button"
                        data-search-clear
                        class="btn btn-ghost btn-xs btn-circle hidden"
                        aria-label="Limpiar búsqueda"
                    >
                        <i class="bi bi-x-lg text-xs" aria-hidden="true"></i>
                    </button>
                </div>
                <p data-search-status class="mt-2 text-xs text-base-content/60 hidden" aria-live="polite"></p>
            </div>

            <div class="space-y-4 animate-fade-in-up animate-delay-200" data-transparency-list>
                @foreach($groupedRecords as $year => $periods)
                    <!-- Acordeón de Año (Collapse de daisyUI) -->
                    <div class="collapse collapse-arrow bg-base-100 border border-base-300 rounded-2xl shadow-xs" data-year-group>
                        <input type="checkbox" name="year-accordion" id="year-{{ $year }}" data-accordion-toggle {{ $loop->first ? 'checked' : '' }} />
                        
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
                                <div class="collapse collapse-plus bg-base-200/40 border border-base-200 rounded-xl" data-subgroup>
                                    <input type="checkbox" name="period-accordion-{{ $year }}" id="period-{{ $year }}-{{ \Illuminate\Support\Str::slug($period) }}" data-accordion-toggle checked />
                                    
                                    <div class="collapse-title py-3 px-4 flex items-center gap-2">
                                        <i class="bi bi-calendar3 text-primary/70 text-sm"></i>
                                        <span class="text-sm font-bold text-base-content/80">
                                            {{ $period }}
                                        </span>
                                        <span class="badge badge-sm badge-neutral badge-soft font-semibold text-base-content/60 border-0">
                                            {{ $recordsList->count() }}
                                        </span>
                                    </div>

                                    <div class="collapse-content px-4 pb-4 pt-1">
                                        <div class="space-y-3 mt-2">
                                            @foreach($recordsList as $record)
                                                @php
                                                    $searchIndex = \Illuminate\Support\Str::lower(
                                                        \Illuminate\Support\Str::ascii(
                                                            preg_replace('/\s+/', ' ', trim(implode(' ', array_filter([
                                                                $record->name,
                                                                $record->reference_number,
                                                                $record->summary,
                                                            ]))))
                                                        )
                                                    );
                                                @endphp
                                                <!-- Tarjeta del Registro -->
                                                <div class="card card-border bg-base-100 border-base-300 rounded-xl shadow-2xs hover:shadow-xs transition-all"
                                                     data-record-card
                                                     data-search="{{ $searchIndex }}">
                                                    <div class="card-body p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">

                                                        <!-- Información del Registro -->
                                                        <div class="space-y-2 max-w-3xl">
                                                            <div class="flex flex-wrap items-center gap-2">
                                                                <h3 class="text-sm sm:text-base font-bold text-base-content">
                                                                    {{ $record->name }}
                                                                </h3>

                                                                <!-- Badge de Visibilidad -->
                                                                @if($record->isPublico())
                                                                    <span class="badge badge-xs badge-success gap-1 font-semibold py-2 px-2.5 rounded-lg">
                                                                        <span class="w-1.5 h-1.5 rounded-full bg-success-content"></span>
                                                                        {{ $record->status->getLabel() }}
                                                                    </span>
                                                                @else
                                                                    <span class="badge badge-xs badge-warning badge-soft gap-1 font-semibold py-2 px-2.5 rounded-lg">
                                                                        <i class="bi bi-lock-fill text-[10px]"></i>
                                                                        {{ $record->status->getLabel() }}
                                                                    </span>
                                                                @endif

                                                                @if($record->reference_number)
                                                                    <span class="badge badge-xs badge-neutral badge-soft font-mono font-semibold py-2 px-2.5 rounded-lg">
                                                                        Folio: {{ $record->reference_number }}
                                                                    </span>
                                                                @endif

                                                                @if($type->usesDocumentDate() && $record->document_date)
                                                                    <span class="badge badge-xs badge-ghost gap-1 font-medium py-2 px-2.5 rounded-lg text-base-content/75">
                                                                        <i class="bi bi-calendar-event text-[10px]"></i>
                                                                        <span>Fecha del Documento: {{ $record->document_date->translatedFormat('d M, Y') }}</span>
                                                                    </span>
                                                                @endif
                                                            </div>

                                                            @if($record->summary)
                                                                <p class="text-xs sm:text-sm text-base-content/70 leading-relaxed">
                                                                    {{ $record->summary }}
                                                                </p>
                                                            @endif

                                                            @if($record->isInterno())
                                                                <div class="text-xs text-base-content/70 bg-base-200/60 border border-base-300/60 rounded-lg px-3 py-2 flex items-start gap-2">
                                                                    <i class="bi bi-info-circle shrink-0 mt-0.5 text-warning"></i>
                                                                    <span>
                                                                        <strong class="font-semibold text-base-content/85">Motivo de acceso interno:</strong>
                                                                        {{ $record->observations ?: 'Disponible únicamente para consulta interna.' }}
                                                                    </span>
                                                                </div>
                                                            @endif
                                                        </div>

                                                        <!-- Botón de Descarga / Estado Cerrado -->
                                                        <div class="flex flex-col items-start md:items-end justify-center shrink-0 border-t md:border-t-0 pt-3 md:pt-0 border-base-200">
                                                            @if($record->isPublico())
                                                                @php($documents = $record->getMedia(\App\Models\TransparencyRecord::DOCUMENTS_COLLECTION))
                                                                @if($documents->count() === 1)
                                                                    @php($singleMedia = $documents->first())
                                                                    <a href="{{ route('transparency.documents.download', [$record, $singleMedia]) }}"
                                                                       class="btn btn-sm btn-primary rounded-lg gap-1.5 shadow-2xs font-medium">
                                                                        <i class="bi bi-arrow-down-tray"></i>
                                                                        Descargar documento
                                                                    </a>
                                                                @elseif($documents->count() > 1)
                                                                    <div class="space-y-2 w-full md:w-auto">
                                                                        @foreach($documents as $media)
                                                                            <div class="flex items-center justify-between gap-3 text-xs">
                                                                                <span class="text-base-content/75 truncate max-w-52 font-medium" title="{{ $media->file_name }}">
                                                                                    <i class="bi bi-file-earmark-text text-primary mr-1"></i>
                                                                                    {{ $media->file_name }}
                                                                                </span>
                                                                                <a href="{{ route('transparency.documents.download', [$record, $media]) }}"
                                                                                   class="btn btn-xs btn-primary rounded-lg gap-1.5 shadow-2xs font-medium"
                                                                                   title="Descargar {{ $media->file_name }}">
                                                                                    <i class="bi bi-arrow-down-tray"></i>
                                                                                    Descargar
                                                                                </a>
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                @else
                                                                    <span class="text-xs text-base-content/40 italic">
                                                                        Sin documentos adjuntos
                                                                    </span>
                                                                @endif
                                                            @else
                                                                <span class="badge badge-sm badge-neutral badge-soft gap-1.5 py-3 px-3 rounded-lg border border-base-300/60 text-base-content/70 font-medium">
                                                                    <i class="bi bi-lock-fill text-xs"></i>
                                                                    Solo consulta interna
                                                                </span>
                                                            @endif
                                                        </div>

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

            <!-- Estado Vacío de Búsqueda -->
            <div data-search-empty class="card card-border bg-base-100 border-base-300/80 rounded-2xl p-8 text-center shadow-xs hidden">
                <div class="mx-auto w-12 h-12 bg-base-200 rounded-full flex items-center justify-center mb-3">
                    <i class="bi bi-search text-xl text-base-content/40" aria-hidden="true"></i>
                </div>
                <h3 class="text-base font-bold text-base-content mb-1">
                    Sin coincidencias encontradas
                </h3>
                <p class="text-xs sm:text-sm text-base-content/60 mb-4">
                    No hay registros que coincidan con tu búsqueda en <strong>{{ $type->getLabel() }}</strong>.
                </p>
                <button type="button" data-search-reset class="btn btn-sm btn-outline rounded-xl mx-auto">
                    Limpiar búsqueda
                </button>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    // ponytail: client-side DOM filtering assumes hundreds of records per category; switch to server-side search/pagination if a category exceeds ~500 records.
                    const searchInput = document.querySelector('[data-transparency-search]');
                    const clearButton = document.querySelector('[data-search-clear]');
                    const resetButton = document.querySelector('[data-search-reset]');
                    const emptyState = document.querySelector('[data-search-empty]');
                    const statusText = document.querySelector('[data-search-status]');
                    const yearGroups = Array.from(document.querySelectorAll('[data-year-group]'));

                    if (!searchInput) {
                        return;
                    }

                    const normalize = (value) => value
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .toLowerCase()
                        .trim();

                    const applyFilter = () => {
                        const rawQuery = searchInput.value;
                        const terms = normalize(rawQuery).split(/\s+/).filter(Boolean);
                        const isFiltering = terms.length > 0;

                        clearButton?.classList.toggle('hidden', rawQuery.length === 0);

                        if (!isFiltering) {
                            yearGroups.forEach((yearGroup) => {
                                yearGroup.classList.remove('hidden');
                                const yearToggle = yearGroup.querySelector(':scope > [data-accordion-toggle]');
                                if (yearToggle) {
                                    yearToggle.checked = yearToggle.defaultChecked;
                                }

                                yearGroup.querySelectorAll('[data-subgroup]').forEach((subgroup) => {
                                    subgroup.classList.remove('hidden');
                                    const subToggle = subgroup.querySelector(':scope > [data-accordion-toggle]');
                                    if (subToggle) {
                                        subToggle.checked = subToggle.defaultChecked;
                                    }

                                    subgroup.querySelectorAll('[data-record-card]').forEach((card) => {
                                        card.classList.remove('hidden');
                                    });
                                });
                            });

                            emptyState?.classList.add('hidden');
                            statusText?.classList.add('hidden');
                            return;
                        }

                        let totalMatches = 0;

                        yearGroups.forEach((yearGroup) => {
                            let yearMatches = 0;

                            yearGroup.querySelectorAll('[data-subgroup]').forEach((subgroup) => {
                                let subgroupMatches = 0;

                                subgroup.querySelectorAll('[data-record-card]').forEach((card) => {
                                    const index = card.dataset.search || '';
                                    const matches = terms.every((term) => index.includes(term));
                                    card.classList.toggle('hidden', !matches);

                                    if (matches) {
                                        subgroupMatches += 1;
                                        yearMatches += 1;
                                        totalMatches += 1;
                                    }
                                });

                                subgroup.classList.toggle('hidden', subgroupMatches === 0);
                                const subToggle = subgroup.querySelector(':scope > [data-accordion-toggle]');
                                if (subToggle) {
                                    subToggle.checked = subgroupMatches > 0;
                                }
                            });

                            yearGroup.classList.toggle('hidden', yearMatches === 0);
                            const yearToggle = yearGroup.querySelector(':scope > [data-accordion-toggle]');
                            if (yearToggle) {
                                yearToggle.checked = yearMatches > 0;
                            }
                        });

                        emptyState?.classList.toggle('hidden', totalMatches > 0);
                        if (statusText) {
                            statusText.textContent = totalMatches === 1
                                ? '1 registro encontrado'
                                : `${totalMatches} registros encontrados`;
                            statusText.classList.remove('hidden');
                        }
                    };

                    const clearSearch = () => {
                        searchInput.value = '';
                        applyFilter();
                        searchInput.focus();
                    };

                    searchInput.addEventListener('input', applyFilter);
                    clearButton?.addEventListener('click', clearSearch);
                    resetButton?.addEventListener('click', clearSearch);
                });
            </script>
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
