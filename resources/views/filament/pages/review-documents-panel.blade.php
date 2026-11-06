@php
    use App\Enums\UserDocumentType;
@endphp

<div
    x-data="{
        activeDoc: @entangle('activeDocumentType'),
        labels: {
            @foreach($documentData as $k => $d)
                '{{ $k }}': '{{ addslashes($d['label']) }}',
            @endforeach
        }
    }"
    class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12"
>
    {{-- ── COLUMNA IZQUIERDA: Selector de documentos y Datos del socio (4 cols = ~33%) ── --}}
    <div class="space-y-5 lg:col-span-4 xl:col-span-4">

        {{-- TARJETA 1: Lista Vertical de Documentos --}}
        <div class="overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-xs dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center justify-between gap-2.5 border-b border-gray-200/80 bg-gray-50/75 px-4 py-3 dark:border-white/10 dark:bg-white/[0.02]">
                <div class="min-w-0 flex-1">
                    <h3 class="truncate text-sm font-semibold text-gray-950 dark:text-white">Expediente de Afiliación</h3>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">Documentos a revisar</p>
                </div>
                <div class="shrink-0">
                    <x-filament::badge :color="$validCount === $totalDocuments ? 'success' : 'warning'" size="sm" class="whitespace-nowrap">
                        <span class="tabular-nums font-semibold">{{ $validCount }}/{{ $totalDocuments }}</span> validados
                    </x-filament::badge>
                </div>
            </div>

            <nav class="divide-y divide-gray-100 dark:divide-white/5" aria-label="Documentos">
                @foreach ($documentData as $key => $doc)
                    <button
                        type="button"
                        @click="activeDoc = '{{ $key }}'"
                        class="group relative flex w-full items-start gap-3 p-3.5 px-4 text-left transition-colors duration-150"
                        :class="activeDoc === '{{ $key }}'
                            ? 'bg-primary-50/70 text-primary-950 dark:bg-primary-950/35 dark:text-primary-100'
                            : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5'"
                        :aria-current="activeDoc === '{{ $key }}' ? 'true' : 'false'"
                    >
                        <div
                            class="mt-0.5 shrink-0 rounded-lg p-2 transition-colors"
                            :class="activeDoc === '{{ $key }}'
                                ? 'bg-primary-100 text-primary-700 ring-1 ring-primary-500/20 dark:bg-primary-900/60 dark:text-primary-300'
                                : 'bg-gray-100 text-gray-500 group-hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:group-hover:text-gray-200'"
                        >
                            <x-filament::icon :icon="$doc['icon']" class="h-4 w-4" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <div
                                    class="text-sm font-semibold leading-snug break-words"
                                    :class="activeDoc === '{{ $key }}'
                                        ? 'text-gray-950 dark:text-white'
                                        : 'text-gray-800 group-hover:text-gray-950 dark:text-gray-200 dark:group-hover:text-white'"
                                >
                                    {{ $doc['label'] }}
                                </div>
                                <div class="shrink-0">
                                    <x-filament::badge :color="$doc['statusColor']" size="sm" class="whitespace-nowrap">
                                        {{ $doc['statusLabel'] }}
                                    </x-filament::badge>
                                </div>
                            </div>

                            <div class="mt-1 truncate font-mono text-[11px] text-gray-500 dark:text-gray-400">
                                {{ $doc['fileName'] ?? 'Sin archivo cargado' }}
                            </div>
                        </div>
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- TARJETA 2: Datos del Socio a Cotejar --}}
        <div class="overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-xs dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center justify-between gap-2 border-b border-gray-200/80 bg-gray-50/75 px-4 py-3 dark:border-white/10 dark:bg-white/[0.02]">
                <div class="min-w-0 flex-1">
                    <h3 class="truncate text-sm font-semibold text-gray-950 dark:text-white">Datos del Perfil</h3>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                        Cotejando con: <span class="font-medium text-primary-600 dark:text-primary-400" x-text="labels[activeDoc] || 'Documento activo'"></span>
                    </p>
                </div>
                <div class="shrink-0">
                    <x-filament::button
                        wire:click="mountAction('setDocumentValues', { type: activeDoc })"
                        color="gray"
                        size="xs"
                        icon="heroicon-m-pencil-square"
                        outlined
                    >
                        Ingresar datos
                    </x-filament::button>
                </div>
            </div>

            <div class="max-h-[calc(100vh-26rem)] min-h-[300px] divide-y divide-gray-100 overflow-y-auto p-4 space-y-3.5 dark:divide-white/5">
                @foreach ($fields as $fieldKey => $fieldMeta)
                    <div class="pt-3.5 first:pt-0">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
                            {{ $fieldMeta['label'] }}
                        </div>
                        <div class="mt-0.5 text-sm font-semibold break-words {{ in_array($fieldKey, ['curp', 'nss']) ? 'font-mono tracking-tight text-gray-900 dark:text-gray-100' : 'text-gray-900 dark:text-gray-100' }}">
                            {{ $record->{$fieldKey} ?: '—' }}
                        </div>

                        {{-- Comparación contra el documento activo --}}
                        @foreach ($documentData as $docKey => $doc)
                            <div x-show="activeDoc === '{{ $docKey }}'" class="mt-1">
                                @php
                                    $item = $doc['checklist'][$fieldKey];
                                @endphp

                                @if ($doc['media'] === null)
                                    <span class="inline-flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500 italic">
                                        <x-filament::icon icon="heroicon-m-document-magnifying-glass" class="h-3.5 w-3.5 shrink-0" />
                                        Documento sin subir
                                    </span>
                                @elseif ($item['hasComparison'])
                                    @if ($item['matches'])
                                        <div class="inline-flex items-center gap-1.5 text-xs font-medium text-success-700 dark:text-success-400">
                                            <x-filament::icon icon="heroicon-m-check-circle" class="h-3.5 w-3.5 shrink-0 text-success-600 dark:text-success-400" />
                                            <span>Coincide con el documento</span>
                                        </div>
                                    @else
                                        <div class="rounded-lg bg-danger-50 p-2.5 text-xs text-danger-800 ring-1 ring-inset ring-danger-200/70 dark:bg-danger-950/40 dark:text-danger-200 dark:ring-danger-800/50 flex items-start gap-2">
                                            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4 shrink-0 text-danger-600 dark:text-danger-400 mt-0.5" />
                                            <div class="min-w-0">
                                                <div class="font-medium">Difiere del documento cargado</div>
                                                <div class="mt-0.5 text-danger-700 dark:text-danger-300">
                                                    En documento: <strong class="font-semibold text-danger-950 dark:text-danger-100">{{ $item['docValue'] }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                                        <x-filament::icon icon="heroicon-m-minus-circle" class="h-3.5 w-3.5 shrink-0 opacity-70" />
                                        Sin datos de cotejo capturados
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

    </div>

    {{-- ── COLUMNA DERECHA: Toolbar y Visor del Documento (8 cols = ~67%) ── --}}
    <div class="flex flex-col space-y-4 lg:col-span-8 xl:col-span-8">

        @foreach ($documentData as $key => $doc)
            <div x-show="activeDoc === '{{ $key }}'" class="flex flex-col space-y-4">

                {{-- Toolbar Superior del Documento --}}
                <div class="rounded-xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-white/10 dark:bg-gray-900 space-y-3.5">
                    {{-- Fila 1: Metadatos e Identidad del Documento --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="shrink-0 rounded-lg bg-primary-50 p-2.5 text-primary-600 dark:bg-primary-950/60 dark:text-primary-400">
                                <x-filament::icon :icon="$doc['icon']" class="h-5 w-5" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-base font-semibold text-gray-950 dark:text-white" style="text-wrap: balance;">
                                        {{ $doc['label'] }}
                                    </h2>
                                    <x-filament::badge :color="$doc['statusColor']" size="sm" class="whitespace-nowrap">
                                        {{ $doc['statusLabel'] }}
                                    </x-filament::badge>
                                </div>
                                <div class="mt-0.5 truncate font-mono text-[11px] text-gray-500 dark:text-gray-400">
                                    {{ $doc['fileName'] ?? 'Sin archivo cargado' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    @if (!empty($doc['rejectionReason']))
                        <div class="rounded-lg bg-danger-50 p-2.5 text-xs text-danger-800 ring-1 ring-inset ring-danger-200/70 dark:bg-danger-950/40 dark:text-danger-200 dark:ring-danger-800/50 flex items-start gap-2">
                            <x-filament::icon icon="heroicon-m-exclamation-circle" class="h-4 w-4 shrink-0 text-danger-600 dark:text-danger-400 mt-0.5" />
                            <div class="min-w-0">
                                <span class="font-semibold">Observación de rechazo:</span> {{ $doc['rejectionReason'] }}
                            </div>
                        </div>
                    @endif

                    {{-- Fila 2: Acciones en barra dedicada (Decisión y Utilidades) --}}
                    <div class="flex flex-wrap items-center justify-between gap-2.5 border-t border-gray-100 pt-3 dark:border-white/5">
                        @if ($doc['media'] !== null)
                            {{-- Grupo de Evaluación Decisoria --}}
                            <div class="flex flex-wrap items-center gap-2">
                                <x-filament::button
                                    wire:click="mountAction('approveDocument', { type: '{{ $key }}' })"
                                    color="success"
                                    size="sm"
                                    icon="heroicon-m-check-circle"
                                >
                                    Autorizar
                                </x-filament::button>

                                <x-filament::button
                                    wire:click="mountAction('rejectDocument', { type: '{{ $key }}' })"
                                    color="danger"
                                    size="sm"
                                    icon="heroicon-m-x-circle"
                                    outlined
                                >
                                    Rechazar
                                </x-filament::button>
                            </div>

                            {{-- Grupo de Herramientas del Documento --}}
                            <div class="flex flex-wrap items-center gap-2">
                                <x-filament::button
                                    wire:click="mountAction('analyzeWithAi', { type: '{{ $key }}' })"
                                    color="info"
                                    size="sm"
                                    icon="heroicon-m-cpu-chip"
                                    outlined
                                >
                                    Analizar con IA
                                </x-filament::button>

                                <x-filament::button
                                    wire:click="mountAction('replaceDocument', { type: '{{ $key }}' })"
                                    color="gray"
                                    size="sm"
                                    icon="heroicon-m-arrow-path"
                                    outlined
                                >
                                    Reemplazar
                                </x-filament::button>

                                <x-filament::button
                                    :href="$doc['documentUrl']"
                                    tag="a"
                                    target="_blank"
                                    color="gray"
                                    size="sm"
                                    icon="heroicon-m-arrow-top-right-on-square"
                                    outlined
                                >
                                    Abrir
                                </x-filament::button>
                            </div>
                        @else
                            <div>
                                <x-filament::button
                                    wire:click="mountAction('replaceDocument', { type: '{{ $key }}' })"
                                    color="primary"
                                    size="sm"
                                    icon="heroicon-m-arrow-up-tray"
                                >
                                    Subir archivo
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- ── Bloque de Análisis IA ────────────────────────────────────────────── --}}
                @if ($doc['media'] !== null && ($doc['extractedData'] !== null || $doc['aiError'] !== null || $doc['aiFlagged']))
                    <div class="overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-xs dark:border-white/10 dark:bg-gray-900">
                        <div class="flex items-center justify-between border-b border-gray-200/80 bg-gray-50/75 px-4 py-3 dark:border-white/10 dark:bg-white/[0.02]">
                            <div class="flex items-center gap-2">
                                <x-filament::icon icon="heroicon-m-cpu-chip" class="h-4 w-4 text-primary-600 dark:text-primary-400 shrink-0" />
                                <h4 class="text-sm font-semibold text-gray-950 dark:text-white" style="text-wrap: balance;">Análisis de Inteligencia Artificial</h4>
                            </div>
                            @if ($doc['aiProcessedAt'])
                                <span class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">{{ \Carbon\Carbon::parse($doc['aiProcessedAt'])->diffForHumans() }}</span>
                            @endif
                        </div>

                        <div class="space-y-4 p-4">
                            {{-- AI error / unavailable --}}
                            @if ($doc['aiError'])
                                <div class="flex items-start gap-2.5 rounded-lg bg-warning-50 p-3 text-xs text-warning-800 ring-1 ring-inset ring-warning-200/70 dark:bg-warning-950/30 dark:text-warning-300 dark:ring-warning-800/50">
                                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4 shrink-0 text-warning-600 dark:text-warning-400 mt-0.5" />
                                    <span style="text-wrap: pretty;">{{ $doc['aiError'] }}</span>
                                </div>
                            @endif

                            {{-- Flagged --}}
                            @if ($doc['aiFlagged'])
                                <div class="flex items-start gap-2.5 rounded-lg bg-danger-50 p-3 text-xs text-danger-800 ring-1 ring-inset ring-danger-200/70 dark:bg-danger-950/30 dark:text-danger-300 dark:ring-danger-800/50">
                                    <x-filament::icon icon="heroicon-m-flag" class="h-4 w-4 shrink-0 text-danger-600 dark:text-danger-400 mt-0.5" />
                                    <span style="text-wrap: pretty;">La IA marcó este documento como posiblemente incorrecto o inconsistente. Revísalo con atención contra los datos del agremiado.</span>
                                </div>
                            @endif

                            {{-- AI flags (reviewer alerts) --}}
                            @if (!empty($doc['aiFlags']))
                                <div class="space-y-2">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Alertas para el revisor</p>
                                    @foreach ($doc['aiFlags'] as $flagMsg)
                                        <div class="flex items-start gap-2 rounded-lg bg-warning-50 p-2.5 text-xs text-warning-800 ring-1 ring-inset ring-warning-200/70 dark:bg-warning-950/30 dark:text-warning-300 dark:ring-warning-800/50">
                                            <x-filament::icon icon="heroicon-m-exclamation-circle" class="h-4 w-4 shrink-0 text-warning-600 dark:text-warning-400 mt-0.5" />
                                            <span style="text-wrap: pretty;">{{ $flagMsg }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Extracted fields --}}
                            @if (!empty($doc['extractedData']['extracted_fields']))
                                @php $ef = $doc['extractedData']['extracted_fields']; @endphp
                                <div>
                                    <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Datos extraídos del documento</p>
                                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                                        @foreach ($ef as $efKey => $efVal)
                                            @if (filled($efVal))
                                                <div>
                                                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ ucfirst(str_replace('_', ' ', $efKey)) }}</dt>
                                                    <dd class="mt-0.5 break-all text-sm font-semibold text-gray-900 dark:text-gray-100 tabular-nums">{{ $efVal }}</dd>
                                                </div>
                                            @endif
                                        @endforeach
                                    </dl>
                                </div>
                            @endif

                            {{-- Confidence score --}}
                            @if (isset($doc['extractedData']['confidence_score']))
                                <div class="flex flex-wrap items-center gap-2 border-t border-gray-100 pt-2.5 text-xs text-gray-500 dark:border-white/5 dark:text-gray-400">
                                    <span>Certeza de extracción:</span>
                                    <span class="inline-flex items-center rounded-full bg-primary-50 px-2.5 py-0.5 font-semibold tabular-nums text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-950/60 dark:text-primary-300">
                                        {{ round($doc['extractedData']['confidence_score'] * 100) }}%
                                    </span>
                                    @if (!empty($doc['extractedData']['observations']))
                                        <span class="text-gray-400 dark:text-gray-500">·</span>
                                        <span style="text-wrap: pretty;">{{ $doc['extractedData']['observations'] }}</span>
                                    @endif
                                </div>
                            @endif

                            {{-- Side-by-side photo comparison (INE only) --}}
                            @if ($key === 'ine' && $doc['documentUrl'])
                                <div class="border-t border-gray-100 pt-3 dark:border-white/5">
                                    <p class="mb-2.5 text-xs font-medium text-gray-500 dark:text-gray-400">Comparación de fotografías</p>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="text-center">
                                            <p class="mb-1.5 text-xs font-medium text-gray-600 dark:text-gray-400">Foto de perfil</p>
                                            @if ($doc['profilePhotoUrl'])
                                                <img
                                                    src="{{ $doc['profilePhotoUrl'] }}"
                                                    alt="Foto de perfil del agremiado"
                                                    class="mx-auto aspect-square w-full max-w-[140px] rounded-lg border border-gray-200 object-cover shadow-xs dark:border-white/10"
                                                >
                                            @else
                                                <div class="mx-auto flex aspect-square w-full max-w-[140px] items-center justify-center rounded-lg border border-gray-200 bg-gray-100 text-xs text-gray-400 dark:border-white/10 dark:bg-gray-800 dark:text-gray-500">
                                                    Sin foto registrada
                                                </div>
                                            @endif
                                        </div>
                                        <div class="text-center">
                                            <p class="mb-1.5 text-xs font-medium text-gray-600 dark:text-gray-400">INE (documento)</p>
                                            <div class="mx-auto flex aspect-square w-full max-w-[140px] items-center justify-center rounded-lg border border-gray-200 bg-gray-50 p-2 text-center dark:border-white/10 dark:bg-gray-800/60">
                                                <a
                                                    href="{{ $doc['documentUrl'] }}"
                                                    target="_blank"
                                                    class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                                                >
                                                    <span>Ver INE completa</span>
                                                    <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="h-3.5 w-3.5 shrink-0" />
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400" style="text-wrap: pretty;">
                                        La comparación facial es de revisión manual. Verifica que ambas imágenes correspondan a la misma persona física.
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Visor de Documento o Estado Vacío --}}
                <div class="relative flex-1 overflow-hidden rounded-xl border border-gray-200/80 bg-gray-50 shadow-xs dark:border-white/10 dark:bg-gray-950">
                    @if ($doc['documentUrl'])
                        <iframe
                            wire:key="document-viewer-{{ $key }}-{{ $doc['media']->getKey() }}"
                            src="{{ $doc['documentUrl'] }}"
                            class="w-full rounded-xl border-0 bg-white dark:bg-gray-900"
                            style="height: calc(100vh - 17.5rem); min-height: 560px;"
                            title="{{ $doc['label'] }}"
                        ></iframe>
                    @else
                        <div
                            class="flex flex-col items-center justify-center p-12 text-center"
                            style="height: calc(100vh - 17.5rem); min-height: 560px;"
                        >
                            <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                                <x-filament::icon icon="heroicon-o-document-arrow-up" class="h-7 w-7" />
                            </div>
                            <h3 class="mb-1 text-base font-semibold text-gray-900 dark:text-gray-100" style="text-wrap: balance;">
                                Documento aún no subido
                            </h3>
                            <p class="mx-auto mb-5 max-w-sm text-sm text-gray-500 dark:text-gray-400" style="text-wrap: pretty;">
                                El agremiado aún no ha cargado su {{ $doc['label'] }}. Puedes subir un archivo en su nombre para iniciar la revisión.
                            </p>
                            <x-filament::button
                                wire:click="mountAction('replaceDocument', { type: '{{ $key }}' })"
                                color="gray"
                                size="sm"
                                icon="heroicon-m-arrow-up-tray"
                                outlined
                            >
                                Subir archivo en nombre del socio
                            </x-filament::button>
                        </div>
                    @endif
                </div>

            </div>
        @endforeach

    </div>
</div>