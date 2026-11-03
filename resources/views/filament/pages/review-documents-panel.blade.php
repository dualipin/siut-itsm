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
    class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start"
>
    {{-- ── COLUMNA IZQUIERDA: Selector de documentos y Datos del socio (4 cols = ~33%) ── --}}
    <div class="lg:col-span-4 xl:col-span-4 space-y-5">

        {{-- TARJETA 1: Lista Vertical de Documentos --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-gray-50/75 dark:bg-gray-800/60 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Expediente de Afiliación</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Selecciona un documento para revisar</p>
                </div>
                <x-filament::badge :color="$validCount === $totalDocuments ? 'success' : 'warning'" size="sm">
                    {{ $validCount }}/{{ $totalDocuments }} Validados
                </x-filament::badge>
            </div>

            <nav class="divide-y divide-gray-100 dark:divide-gray-800" aria-label="Documentos">
                @foreach ($documentData as $key => $doc)
                    <button
                        type="button"
                        @click="activeDoc = '{{ $key }}'"
                        class="w-full text-left p-3.5 px-4 transition-all duration-150 flex items-center justify-between border-l-4 group"
                        :class="activeDoc === '{{ $key }}'
                            ? 'bg-primary-50/80 dark:bg-primary-950/40 border-primary-600 dark:border-primary-500'
                            : 'border-transparent hover:bg-gray-50 dark:hover:bg-white/5'"
                    >
                        <div class="flex items-center gap-3 flex-1 min-w-0 pr-2">
                            <div
                                class="p-2 rounded-lg shrink-0 transition-colors"
                                :class="activeDoc === '{{ $key }}'
                                    ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/60 dark:text-primary-300'
                                    : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 group-hover:text-gray-700 dark:group-hover:text-gray-200'"
                            >
                                <x-filament::icon :icon="$doc['icon']" class="w-4 h-4" />
                            </div>
                            <div class="min-w-0">
                                <div
                                    class="text-sm truncate"
                                    :class="activeDoc === '{{ $key }}'
                                        ? 'font-semibold text-primary-950 dark:text-white'
                                        : 'font-medium text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white'"
                                >
                                    {{ $doc['label'] }}
                                </div>
                                <div class="text-xs text-gray-400 dark:text-gray-500 truncate mt-0.5">
                                    {{ $doc['fileName'] ?? 'Sin archivo cargado' }}
                                </div>
                            </div>
                        </div>

                        <div class="shrink-0">
                            <x-filament::badge :color="$doc['statusColor']" size="sm">
                                {{ $doc['statusLabel'] }}
                            </x-filament::badge>
                        </div>
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- TARJETA 2: Datos del Socio a Cotejar --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-gray-50/75 dark:bg-gray-800/60 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white truncate">Datos del Perfil</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                        Cotejando con: <span class="font-medium text-primary-600 dark:text-primary-400" x-text="labels[activeDoc]"></span>
                    </p>
                </div>
                <div class="shrink-0">
                    <x-filament::button
                        wire:click="mountAction('setDocumentValues', { type: activeDoc })"
                        color="info"
                        size="xs"
                        icon="heroicon-m-pencil-square"
                        outlined
                    >
                        Ingresar datos
                    </x-filament::button>
                </div>
            </div>

            <div class="p-4 max-h-[calc(100vh-26rem)] min-h-[300px] overflow-y-auto space-y-3.5 divide-y divide-gray-100 dark:divide-gray-800/80">
                @foreach ($fields as $fieldKey => $fieldMeta)
                    <div class="pt-3.5 first:pt-0">
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">
                            {{ $fieldMeta['label'] }}
                        </div>
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100 break-words">
                            {{ $record->{$fieldKey} ?: '—' }}
                        </div>

                        {{-- Comparación contra el documento activo --}}
                        @foreach ($documentData as $docKey => $doc)
                            <div x-show="activeDoc === '{{ $docKey }}'" class="mt-1">
                                @php
                                    $item = $doc['checklist'][$fieldKey];
                                @endphp

                                @if ($doc['media'] === null)
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500 italic">
                                        Documento sin subir
                                    </span>
                                @elseif ($item['hasComparison'])
                                    @if ($item['matches'])
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-success-600 dark:text-success-400">
                                            <x-filament::icon icon="heroicon-m-check-circle" class="w-3.5 h-3.5 shrink-0" />
                                            Coincide con el documento
                                        </span>
                                    @else
                                        <div class="p-2 rounded-lg bg-danger-50 dark:bg-danger-950/40 border border-danger-200 dark:border-danger-800/60 text-xs text-danger-700 dark:text-danger-300 flex items-start gap-1.5">
                                            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="w-3.5 h-3.5 shrink-0 mt-0.5" />
                                            <div>
                                                En documento: <strong>{{ $item['docValue'] }}</strong> · revísalo
                                            </div>
                                        </div>
                                    @endif
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                                        <x-filament::icon icon="heroicon-m-minus-circle" class="w-3.5 h-3.5 shrink-0 opacity-60" />
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
    <div class="lg:col-span-8 xl:col-span-8 flex flex-col space-y-4">

        @foreach ($documentData as $key => $doc)
            <div x-show="activeDoc === '{{ $key }}'" class="space-y-4 flex flex-col">

                {{-- Toolbar Superior del Documento --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-3.5 px-4 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="p-2.5 rounded-lg bg-primary-50 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400 shrink-0">
                            <x-filament::icon :icon="$doc['icon']" class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-base font-semibold text-gray-900 dark:text-white truncate">
                                    {{ $doc['label'] }}
                                </h2>
                                <x-filament::badge :color="$doc['statusColor']" size="sm">
                                    {{ $doc['statusLabel'] }}
                                </x-filament::badge>
                            </div>
                            @if (!empty($doc['rejectionReason']))
                                <p class="text-xs text-danger-600 dark:text-danger-400 mt-0.5 flex items-center gap-1">
                                    <x-filament::icon icon="heroicon-m-exclamation-circle" class="w-3.5 h-3.5 shrink-0" />
                                    <span><strong>Observación:</strong> {{ $doc['rejectionReason'] }}</span>
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- Acciones del Documento en una sola fila horizontal --}}
                    <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
                        @if ($doc['media'] !== null)
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
                        @else
                            <x-filament::button
                                wire:click="mountAction('replaceDocument', { type: '{{ $key }}' })"
                                color="primary"
                                size="sm"
                                icon="heroicon-m-arrow-up-tray"
                            >
                                Subir archivo
                            </x-filament::button>
                        @endif
                    </div>
                </div>

                {{-- Visor de Documento o Estado Vacío --}}
                <div class="relative w-full rounded-xl overflow-hidden border border-gray-200 dark:border-gray-800 shadow-sm bg-gray-100 dark:bg-gray-950 flex-1">
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
                            class="flex flex-col items-center justify-center p-12 text-center bg-gray-50/60 dark:bg-gray-900/40 rounded-xl"
                            style="height: calc(100vh - 17.5rem); min-height: 560px;"
                        >
                            <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-4 text-gray-400 dark:text-gray-500">
                                <x-filament::icon icon="heroicon-o-document-arrow-up" class="w-8 h-8" />
                            </div>
                            <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 mb-1">
                                Documento aún no subido
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mb-5">
                                El agremiado aún no ha cargado su {{ $doc['label'] }}. Puedes subir un archivo PDF en su nombre para iniciar la revisión.
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
