<div
    x-data="{
        activeEvidence: @entangle('activeEvidenceId'),
        labels: {
            @foreach($evidencesData as $id => $d)
                '{{ $id }}': '{{ addslashes($d['label']) }}',
            @endforeach
        }
    }"
    class="space-y-6"
>
    @if ($totalEvidences === 0)
        <div class="rounded-xl border border-gray-200/80 bg-white p-12 text-center shadow-xs dark:border-white/10 dark:bg-gray-900">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-document-magnifying-glass" class="h-6 w-6" />
            </div>
            <h3 class="text-base font-semibold text-gray-950 dark:text-white" style="text-wrap: balance;">
                Sin evidencias registradas
            </h3>
            <p class="mx-auto mt-1.5 max-w-md text-sm text-gray-500 dark:text-gray-400" style="text-wrap: pretty;">
                Esta solicitud de préstamo no cuenta con evidencias documentales o comprobantes cargados.
            </p>
            <div class="mt-6">
                <x-filament::button
                    :href="\App\Filament\Resources\Loans\LoanResource::getUrl('view', ['record' => $record])"
                    tag="a"
                    color="gray"
                    icon="heroicon-m-arrow-left"
                >
                    Volver a la solicitud
                </x-filament::button>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
            {{-- ── COLUMNA IZQUIERDA: Selector de evidencias y Datos de cotejo (4 cols = ~33%) ── --}}
            <div class="space-y-5 lg:col-span-4 xl:col-span-4">

                {{-- TARJETA 1: Lista Vertical de Evidencias --}}
                <div class="overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-xs dark:border-white/10 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-2.5 border-b border-gray-200/80 bg-gray-50/75 px-4 py-3 dark:border-white/10 dark:bg-white/[0.02]">
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate text-sm font-semibold text-gray-950 dark:text-white">Expediente del Préstamo</h3>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">Comprobantes a revisar</p>
                        </div>
                        <div class="shrink-0">
                            <x-filament::badge :color="$validCount === $totalEvidences ? 'success' : 'warning'" size="sm" class="whitespace-nowrap">
                                <span class="tabular-nums font-semibold">{{ $validCount }}/{{ $totalEvidences }}</span> validadas
                            </x-filament::badge>
                        </div>
                    </div>

                    <nav class="divide-y divide-gray-100 dark:divide-white/5" aria-label="Evidencias documentales">
                        @foreach ($evidencesData as $id => $ev)
                            <button
                                type="button"
                                @click="activeEvidence = {{ $id }}"
                                class="group relative flex w-full items-start gap-3 p-3.5 px-4 text-left transition-colors duration-150"
                                :class="activeEvidence == {{ $id }}
                                    ? 'bg-primary-50/70 text-primary-950 dark:bg-primary-950/35 dark:text-primary-100'
                                    : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5'"
                                :aria-current="activeEvidence == {{ $id }} ? 'true' : 'false'"
                            >
                                <div
                                    class="mt-0.5 shrink-0 rounded-lg p-2 transition-colors"
                                    :class="activeEvidence == {{ $id }}
                                        ? 'bg-primary-100 text-primary-700 ring-1 ring-primary-500/20 dark:bg-primary-900/60 dark:text-primary-300'
                                        : 'bg-gray-100 text-gray-500 group-hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:group-hover:text-gray-200'"
                                >
                                    <x-filament::icon :icon="$ev['icon']" class="h-4 w-4" />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <div
                                            class="text-sm font-semibold leading-snug break-words"
                                            :class="activeEvidence == {{ $id }}
                                                ? 'text-gray-950 dark:text-white'
                                                : 'text-gray-800 group-hover:text-gray-950 dark:text-gray-200 dark:group-hover:text-white'"
                                        >
                                            {{ $ev['label'] }}
                                        </div>
                                        <div class="shrink-0">
                                            <x-filament::badge :color="$ev['manual_status_color']" size="sm" class="whitespace-nowrap">
                                                {{ $ev['manual_status_label'] }}
                                            </x-filament::badge>
                                        </div>
                                    </div>

                                    <div class="mt-1 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="truncate font-mono text-xs">{{ $ev['fileName'] ?? 'Archivo sin nombre' }}</span>
                                        @if($ev['fileSizeFormatted'])
                                            <span class="shrink-0 text-gray-400 dark:text-gray-500">·</span>
                                            <span class="shrink-0 tabular-nums font-mono text-xs">{{ $ev['fileSizeFormatted'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            </button>
                        @endforeach
                    </nav>
                </div>

                {{-- TARJETA 2: Datos del Solicitante y Préstamo a Cotejar --}}
                <div class="overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-xs dark:border-white/10 dark:bg-gray-900">
                    <div class="border-b border-gray-200/80 bg-gray-50/75 px-4 py-3 dark:border-white/10 dark:bg-white/[0.02]">
                        <h3 class="truncate text-sm font-semibold text-gray-950 dark:text-white" style="text-wrap: balance;">Datos a Cotejar</h3>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                            Cotejando con: <span class="font-medium text-primary-600 dark:text-primary-400" x-text="labels[activeEvidence] || 'Evidencia activa'"></span>
                        </p>
                    </div>

                    <div class="max-h-[calc(100vh-26rem)] min-h-[300px] divide-y divide-gray-100 overflow-y-auto p-4 space-y-3.5 dark:divide-white/5">
                        {{-- Checklist Dinámico según la evidencia activa --}}
                        @php
                            $checklistKeys = [
                                'trabajador' => 'Trabajador',
                                'curp' => 'CURP',
                                'rfc' => 'RFC',
                                'monto' => 'Monto Referencia',
                                'periodo' => 'Periodo / Fecha',
                            ];
                        @endphp

                        @foreach ($checklistKeys as $chkKey => $chkDefaultLabel)
                            <div class="pt-3.5 first:pt-0">
                                @foreach ($evidencesData as $id => $ev)
                                    @php
                                        $item = $ev['checklist'][$chkKey] ?? null;
                                    @endphp
                                    @if ($item)
                                        <div x-show="activeEvidence == {{ $id }}">
                                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                                {{ $item['label'] }}
                                            </div>
                                            <div class="mt-0.5 text-sm font-semibold break-words {{ in_array($chkKey, ['curp', 'rfc']) ? 'font-mono tracking-tight text-gray-900 dark:text-gray-100' : ($chkKey === 'monto' ? 'tabular-nums text-gray-950 dark:text-white' : 'text-gray-900 dark:text-gray-100') }}">
                                                {{ $item['profileValue'] ?: '—' }}
                                            </div>

                                            <div class="mt-1">
                                                @if (! $ev['hasFile'])
                                                    <span class="inline-flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500 italic">
                                                        <x-filament::icon icon="heroicon-m-document-magnifying-glass" class="h-3.5 w-3.5 shrink-0" />
                                                        Archivo no disponible
                                                    </span>
                                                @elseif ($item['hasComparison'])
                                                    @if ($item['matches'])
                                                        <div class="inline-flex items-center gap-1.5 text-xs font-medium text-success-700 dark:text-success-400">
                                                            <x-filament::icon icon="heroicon-m-check-circle" class="h-3.5 w-3.5 shrink-0 text-success-600 dark:text-success-400" />
                                                            <span>Coincide con comprobante</span>
                                                        </div>
                                                    @else
                                                        <div class="rounded-lg bg-danger-50 p-2.5 text-xs text-danger-800 ring-1 ring-inset ring-danger-200/70 dark:bg-danger-950/40 dark:text-danger-200 dark:ring-danger-800/50 flex items-start gap-2">
                                                            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4 shrink-0 text-danger-600 dark:text-danger-400 mt-0.5" />
                                                            <div class="min-w-0">
                                                                <div class="font-medium">Difiere del comprobante</div>
                                                                <div class="mt-0.5 text-danger-700 dark:text-danger-300">
                                                                    En documento: <strong class="font-semibold text-danger-950 dark:text-danger-100">{{ $item['docValue'] }}</strong>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                @else
                                                    <span class="inline-flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                                                        <x-filament::icon icon="heroicon-m-minus-circle" class="h-3.5 w-3.5 shrink-0 opacity-70" />
                                                        Sin dato detectado en el archivo
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endforeach

                        {{-- Datos Bancarios y de Solicitud Adicionales --}}
                        <div class="pt-3.5">
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                Dispersión bancaria
                            </div>
                            <div class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ $record->bank_name ?? 'Sin banco registrado' }}
                            </div>
                            <div class="mt-1 space-y-0.5 font-mono text-xs text-gray-600 dark:text-gray-400">
                                <div>Cuenta: <span class="font-medium text-gray-900 dark:text-gray-200">{{ $record->bank_account ?? 'N/A' }}</span></div>
                                <div>CLABE: <span class="font-medium text-gray-900 dark:text-gray-200">{{ $record->interbank_clabe ?? 'N/A' }}</span></div>
                            </div>
                        </div>

                        <div class="pt-3.5">
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                Condiciones del préstamo
                            </div>
                            <div class="mt-0.5 text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $record->payment_type->getLabel() }}
                            </div>
                            <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                Solicitado: <strong class="tabular-nums font-semibold text-gray-950 dark:text-white">${{ number_format($record->requested_amount, 2) }}</strong>
                                @if($record->payment_type->hasNomina())
                                    <span class="text-gray-400 dark:text-gray-500">·</span>
                                    <span class="tabular-nums font-medium">{{ $record->nomina_fortnights }}</span> quincenas de <strong class="tabular-nums font-semibold text-gray-950 dark:text-white">${{ number_format($record->nomina_amount, 2) }}</strong>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- ── COLUMNA DERECHA: Toolbar y Visor del Documento (8 cols = ~67%) ── --}}
            <div class="flex flex-col space-y-4 lg:col-span-8 xl:col-span-8">

                @foreach ($evidencesData as $id => $ev)
                    <div x-show="activeEvidence == {{ $id }}" class="flex flex-col space-y-4">

                        {{-- Toolbar Superior de la Evidencia --}}
                        <div class="rounded-xl border border-gray-200/80 bg-white p-4 shadow-xs dark:border-white/10 dark:bg-gray-900 space-y-3.5">
                            {{-- Fila 1: Metadatos e Identidad del Documento --}}
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="shrink-0 rounded-lg bg-primary-50 p-2.5 text-primary-600 dark:bg-primary-950/60 dark:text-primary-400">
                                        <x-filament::icon :icon="$ev['icon']" class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h2 class="text-base font-semibold text-gray-950 dark:text-white" style="text-wrap: balance;">
                                                {{ $ev['label'] }}
                                            </h2>
                                            <x-filament::badge :color="$ev['manual_status_color']" size="sm" class="whitespace-nowrap">
                                                {{ $ev['manual_status_label'] }}
                                            </x-filament::badge>
                                            <x-filament::badge :color="$ev['ai_status_color']" size="sm" class="whitespace-nowrap">
                                                IA: {{ $ev['ai_status_label'] }}
                                            </x-filament::badge>
                                        </div>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                            <span class="font-mono text-xs">{{ $ev['fileName'] ?? 'Archivo sin nombre' }}</span>
                                            @if($ev['fileSizeFormatted'])
                                                <span class="text-gray-400 dark:text-gray-500">·</span>
                                                <span class="tabular-nums font-mono text-xs">{{ $ev['fileSizeFormatted'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if (! empty($ev['manual_review_notes']))
                                <div class="rounded-lg bg-danger-50 p-2.5 text-xs text-danger-800 ring-1 ring-inset ring-danger-200/70 dark:bg-danger-950/40 dark:text-danger-200 dark:ring-danger-800/50 flex items-start gap-2">
                                    <x-filament::icon icon="heroicon-m-exclamation-circle" class="h-4 w-4 shrink-0 text-danger-600 dark:text-danger-400 mt-0.5" />
                                    <div class="min-w-0">
                                        <span class="font-semibold">Observación de rechazo:</span> {{ $ev['manual_review_notes'] }}
                                        @if($ev['reviewer_name'])
                                            <span class="text-danger-600 dark:text-danger-400 block text-xs mt-0.5">Revisado por {{ $ev['reviewer_name'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- Fila 2: Acciones en barra dedicada (Decisión y Utilidades) --}}
                            <div class="flex flex-wrap items-center justify-between gap-2.5 border-t border-gray-100 pt-3 dark:border-white/5">
                                {{-- Grupo de Evaluación Decisoria --}}
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-filament::button
                                        wire:click="mountAction('approveEvidence', { id: {{ $id }} })"
                                        color="success"
                                        size="sm"
                                        icon="heroicon-m-check-circle"
                                    >
                                        Aprobar
                                    </x-filament::button>

                                    <x-filament::button
                                        wire:click="mountAction('rejectEvidence', { id: {{ $id }} })"
                                        color="danger"
                                        size="sm"
                                        icon="heroicon-m-x-circle"
                                        outlined
                                    >
                                        Rechazar
                                    </x-filament::button>

                                    @if ($ev['manual_status'] !== 'pending')
                                        <x-filament::button
                                            wire:click="mountAction('resetEvidence', { id: {{ $id }} })"
                                            color="gray"
                                            size="sm"
                                            icon="heroicon-m-arrow-path"
                                            outlined
                                        >
                                            Restablecer
                                        </x-filament::button>
                                    @endif
                                </div>

                                {{-- Grupo de Herramientas del Comprobante --}}
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-filament::button
                                        wire:click="mountAction('analyzeWithAi', { id: {{ $id }} })"
                                        color="info"
                                        size="sm"
                                        icon="heroicon-m-cpu-chip"
                                        outlined
                                    >
                                        Analizar con IA
                                    </x-filament::button>

                                    <x-filament::button
                                        wire:click="mountAction('replaceEvidence', { id: {{ $id }} })"
                                        color="gray"
                                        size="sm"
                                        icon="heroicon-m-arrow-up-tray"
                                        outlined
                                    >
                                        Reemplazar
                                    </x-filament::button>

                                    @if ($ev['downloadUrl'])
                                        <x-filament::button
                                            :href="$ev['downloadUrl']"
                                            tag="a"
                                            target="_blank"
                                            color="gray"
                                            size="sm"
                                            icon="heroicon-m-arrow-top-right-on-square"
                                            outlined
                                        >
                                            Abrir
                                        </x-filament::button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- ── Bloque de Análisis IA ──────────────────────────────────── --}}
                        @if ($ev['ai_status'] || ! empty($ev['extracted_data']) || $ev['ai_classification'])
                            <div class="overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-xs dark:border-white/10 dark:bg-gray-900">
                                <div class="flex items-center justify-between border-b border-gray-200/80 bg-gray-50/75 px-4 py-3 dark:border-white/10 dark:bg-white/[0.02]">
                                    <div class="flex items-center gap-2">
                                        <x-filament::icon icon="heroicon-m-cpu-chip" class="h-4 w-4 text-primary-600 dark:text-primary-400 shrink-0" />
                                        <h4 class="text-sm font-semibold text-gray-950 dark:text-white" style="text-wrap: balance;">
                                            Análisis de Inteligencia Artificial
                                        </h4>
                                    </div>
                                    @if ($ev['ai_confidence'])
                                        <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-semibold tabular-nums text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-950/60 dark:text-primary-300">
                                            Confianza: {{ round($ev['ai_confidence'] * 100) }}%
                                        </span>
                                    @endif
                                </div>

                                <div class="space-y-3.5 p-4 text-xs text-gray-700 dark:text-gray-300">
                                    @if ($ev['ai_status']?->value === 'invalido' || $ev['ai_status']?->value === 'dudoso')
                                        <div class="flex items-start gap-2.5 rounded-lg bg-warning-50 p-3 text-xs text-warning-800 ring-1 ring-inset ring-warning-200/70 dark:bg-warning-950/30 dark:text-warning-300 dark:ring-warning-800/50">
                                            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4 shrink-0 text-warning-600 dark:text-warning-400 mt-0.5" />
                                            <span style="text-wrap: pretty;">La IA detectó inconsistencias o baja certeza en este comprobante. Verifica minuciosamente los datos contra el perfil.</span>
                                        </div>
                                    @endif

                                    @if (! empty($ev['extracted_data']))
                                        <div>
                                            <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Datos extraídos del documento</p>
                                            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 md:grid-cols-3">
                                                @foreach ($ev['extracted_data'] as $k => $v)
                                                    @if (filled($v))
                                                        <div>
                                                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ ucfirst(str_replace('_', ' ', $k)) }}</dt>
                                                            <dd class="mt-0.5 break-words text-sm font-semibold text-gray-900 dark:text-gray-100 tabular-nums">{{ $v }}</dd>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </dl>
                                        </div>
                                    @endif

                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-gray-100 pt-2.5 text-xs text-gray-500 dark:border-white/5 dark:text-gray-400">
                                        <span>Clasificación: <strong class="font-medium text-gray-800 dark:text-gray-200">{{ $ev['ai_classification'] ?? 'N/A' }}</strong></span>
                                        @if($ev['ai_model'])
                                            <span>Modelo: <strong class="font-mono text-xs font-medium text-gray-800 dark:text-gray-200">{{ $ev['ai_model'] }}</strong></span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Visor de Documento o Estado Vacío --}}
                        <div class="relative flex-1 overflow-hidden rounded-xl border border-gray-200/80 bg-gray-50 shadow-xs dark:border-white/10 dark:bg-gray-950">
                            @if ($ev['downloadUrl'])
                                <iframe
                                    wire:key="loan-evidence-viewer-{{ $id }}"
                                    src="{{ $ev['downloadUrl'] }}"
                                    class="w-full rounded-xl border-0 bg-white dark:bg-gray-900"
                                    style="height: calc(100vh - 17.5rem); min-height: 560px;"
                                    title="{{ $ev['label'] }}"
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
                                        Archivo no encontrado en almacenamiento
                                    </h3>
                                    <p class="mx-auto mb-5 max-w-sm text-sm text-gray-500 dark:text-gray-400" style="text-wrap: pretty;">
                                        El archivo físico para {{ $ev['label'] }} no se encuentra disponible. Puedes subir o reemplazar el comprobante aquí mismo.
                                    </p>
                                    <x-filament::button
                                        wire:click="mountAction('replaceEvidence', { id: {{ $id }} })"
                                        color="gray"
                                        size="sm"
                                        icon="heroicon-m-arrow-up-tray"
                                        outlined
                                    >
                                        Subir archivo de reemplazo
                                    </x-filament::button>
                                </div>
                            @endif
                        </div>

                    </div>
                @endforeach

            </div>
        </div>
    @endif
</div>