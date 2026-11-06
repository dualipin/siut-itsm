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
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-12 text-center">
            <div class="mx-auto w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-400 dark:text-gray-500 mb-4">
                <x-filament::icon icon="heroicon-o-document-magnifying-glass" class="w-6 h-6" />
            </div>
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                Sin evidencias registradas
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto">
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
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {{-- ── COLUMNA IZQUIERDA: Selector de evidencias y Datos de cotejo (4 cols = ~33%) ── --}}
            <div class="lg:col-span-4 xl:col-span-4 space-y-5">

                {{-- TARJETA 1: Lista Vertical de Evidencias --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50/75 dark:bg-gray-800/60 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Expediente del Préstamo</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Selecciona un comprobante a revisar</p>
                        </div>
                        <x-filament::badge :color="$validCount === $totalEvidences ? 'success' : 'warning'" size="sm">
                            {{ $validCount }}/{{ $totalEvidences }} Validadas
                        </x-filament::badge>
                    </div>

                    <nav class="divide-y divide-gray-100 dark:divide-gray-800" aria-label="Evidencias documentales">
                        @foreach ($evidencesData as $id => $ev)
                            <button
                                type="button"
                                @click="activeEvidence = {{ $id }}"
                                class="w-full text-left p-3.5 px-4 transition-all duration-150 flex items-center justify-between border-l-4 group"
                                :class="activeEvidence == {{ $id }}
                                    ? 'bg-primary-50/80 dark:bg-primary-950/40 border-primary-600 dark:border-primary-500'
                                    : 'border-transparent hover:bg-gray-50 dark:hover:bg-white/5'"
                            >
                                <div class="flex items-center gap-3 flex-1 min-w-0 pr-2">
                                    <div
                                        class="p-2 rounded-lg shrink-0 transition-colors"
                                        :class="activeEvidence == {{ $id }}
                                            ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/60 dark:text-primary-300'
                                            : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 group-hover:text-gray-700 dark:group-hover:text-gray-200'"
                                    >
                                        <x-filament::icon :icon="$ev['icon']" class="w-4 h-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <div
                                            class="text-sm truncate"
                                            :class="activeEvidence == {{ $id }}
                                                ? 'font-semibold text-primary-950 dark:text-white'
                                                : 'font-medium text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white'"
                                        >
                                            {{ $ev['label'] }}
                                        </div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500 truncate mt-0.5">
                                            {{ $ev['fileName'] ?? 'Archivo sin nombre' }}
                                            @if($ev['fileSizeFormatted'])
                                                · {{ $ev['fileSizeFormatted'] }}
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="shrink-0 flex items-center gap-1.5">
                                    <x-filament::badge :color="$ev['manual_status_color']" size="sm">
                                        {{ $ev['manual_status_label'] }}
                                    </x-filament::badge>
                                </div>
                            </button>
                        @endforeach
                    </nav>
                </div>

                {{-- TARJETA 2: Datos del Solicitante y Préstamo a Cotejar --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50/75 dark:bg-gray-800/60 border-b border-gray-200 dark:border-gray-800">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white truncate">Datos a Cotejar</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                            Cotejando con: <span class="font-medium text-primary-600 dark:text-primary-400" x-text="labels[activeEvidence] || 'Evidencia activa'"></span>
                        </p>
                    </div>

                    <div class="p-4 max-h-[calc(100vh-26rem)] min-h-[300px] overflow-y-auto space-y-3.5 divide-y divide-gray-100 dark:divide-gray-800/80">
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
                                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">
                                                {{ $item['label'] }}
                                            </div>
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100 break-words">
                                                {{ $item['profileValue'] ?: '—' }}
                                            </div>

                                            <div class="mt-1">
                                                @if (! $ev['hasFile'])
                                                    <span class="inline-flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500 italic">
                                                        Archivo no disponible
                                                    </span>
                                                @elseif ($item['hasComparison'])
                                                    @if ($item['matches'])
                                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-success-600 dark:text-success-400">
                                                            <x-filament::icon icon="heroicon-m-check-circle" class="w-3.5 h-3.5 shrink-0" />
                                                            Coincide con el comprobante: <strong>{{ $item['docValue'] }}</strong>
                                                        </span>
                                                    @else
                                                        <div class="p-2 rounded-lg bg-danger-50 dark:bg-danger-950/40 border border-danger-200 dark:border-danger-800/60 text-xs text-danger-700 dark:text-danger-300 flex items-start gap-1.5">
                                                            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="w-3.5 h-3.5 shrink-0 mt-0.5" />
                                                            <div>
                                                                En documento: <strong>{{ $item['docValue'] }}</strong> · difiere del perfil
                                                            </div>
                                                        </div>
                                                    @endif
                                                @else
                                                    <span class="inline-flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                                                        <x-filament::icon icon="heroicon-m-minus-circle" class="w-3.5 h-3.5 shrink-0 opacity-60" />
                                                        Sin dato detectado en el comprobante
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
                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">
                                Dispersión Bancaria
                            </div>
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $record->bank_name ?? 'Sin banco registrado' }}
                            </div>
                            <div class="text-xs font-mono text-gray-500 dark:text-gray-400 mt-0.5">
                                Cuenta: {{ $record->bank_account ?? 'N/A' }} · CLABE: {{ $record->interbank_clabe ?? 'N/A' }}
                            </div>
                        </div>

                        <div class="pt-3.5">
                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">
                                Condiciones del Préstamo
                            </div>
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $record->payment_type->getLabel() }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Solicitado: ${{ number_format($record->requested_amount, 2) }}
                                @if($record->payment_type->hasNomina())
                                    · {{ $record->nomina_fortnights }} quincenas de ${{ number_format($record->nomina_amount, 2) }}
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- ── COLUMNA DERECHA: Toolbar y Visor del Documento (8 cols = ~67%) ── --}}
            <div class="lg:col-span-8 xl:col-span-8 flex flex-col space-y-4">

                @foreach ($evidencesData as $id => $ev)
                    <div x-show="activeEvidence == {{ $id }}" class="space-y-4 flex flex-col">

                        {{-- Toolbar Superior de la Evidencia --}}
                        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-3.5 px-4 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="p-2.5 rounded-lg bg-primary-50 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400 shrink-0">
                                    <x-filament::icon :icon="$ev['icon']" class="w-5 h-5" />
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h2 class="text-base font-semibold text-gray-900 dark:text-white truncate">
                                            {{ $ev['label'] }}
                                        </h2>
                                        <x-filament::badge :color="$ev['manual_status_color']" size="sm">
                                            Manual: {{ $ev['manual_status_label'] }}
                                        </x-filament::badge>
                                        <x-filament::badge :color="$ev['ai_status_color']" size="sm">
                                            IA: {{ $ev['ai_status_label'] }}
                                        </x-filament::badge>
                                    </div>
                                    @if (! empty($ev['manual_review_notes']))
                                        <p class="text-xs text-danger-600 dark:text-danger-400 mt-1 flex items-center gap-1.5">
                                            <x-filament::icon icon="heroicon-m-exclamation-circle" class="w-4 h-4 shrink-0" />
                                            <span><strong>Observación:</strong> {{ $ev['manual_review_notes'] }} @if($ev['reviewer_name']) ({{ $ev['reviewer_name'] }}) @endif</span>
                                        </p>
                                    @endif
                                </div>
                            </div>

                            {{-- Acciones de la Evidencia en barra horizontal --}}
                            <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
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

                        {{-- ── Bloque de Análisis IA ──────────────────────────────────── --}}
                        @if ($ev['ai_status'] || ! empty($ev['extracted_data']) || $ev['ai_classification'])
                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-4 py-3 bg-info-50/60 dark:bg-info-950/30 border-b border-info-200/70 dark:border-info-800/40 flex items-center gap-2">
                                    <x-filament::icon icon="heroicon-m-cpu-chip" class="w-4 h-4 text-info-600 dark:text-info-400 shrink-0" />
                                    <h4 class="text-sm font-semibold text-info-800 dark:text-info-200">
                                        Análisis de Inteligencia Artificial
                                    </h4>
                                    @if ($ev['ai_confidence'])
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-info-100 text-info-800 dark:bg-info-900/60 dark:text-info-300 ml-auto">
                                            Confianza: {{ round($ev['ai_confidence'] * 100) }}%
                                        </span>
                                    @endif
                                </div>

                                <div class="p-4 space-y-3.5 text-xs text-gray-700 dark:text-gray-300">
                                    @if ($ev['ai_status']?->value === 'invalido' || $ev['ai_status']?->value === 'dudoso')
                                        <div class="p-2.5 rounded-lg bg-warning-50 dark:bg-warning-950/30 border border-warning-200 dark:border-warning-800/50 text-warning-800 dark:text-warning-300 flex items-start gap-2">
                                            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="w-4 h-4 shrink-0 mt-0.5" />
                                            <span>La IA detectó inconsistencias o baja certeza en este comprobante. Verifica minuciosamente los datos contra el perfil.</span>
                                        </div>
                                    @endif

                                    @if (! empty($ev['extracted_data']))
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Datos extraídos del documento</p>
                                            <dl class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-2.5">
                                                @foreach ($ev['extracted_data'] as $k => $v)
                                                    @if (filled($v))
                                                        <div>
                                                            <dt class="text-xs text-gray-500 dark:text-gray-400">{{ ucfirst(str_replace('_', ' ', $k)) }}</dt>
                                                            <dd class="text-sm font-medium text-gray-900 dark:text-gray-100 break-words">{{ $v }}</dd>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </dl>
                                        </div>
                                    @endif

                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400 pt-1 border-t border-gray-100 dark:border-gray-800">
                                        <span>Clasificación: <strong class="text-gray-700 dark:text-gray-300">{{ $ev['ai_classification'] ?? 'N/A' }}</strong></span>
                                        @if($ev['ai_model'])
                                            <span>Modelo: <strong class="text-gray-700 dark:text-gray-300">{{ $ev['ai_model'] }}</strong></span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Visor de Documento o Estado Vacío --}}
                        <div class="relative w-full rounded-xl overflow-hidden border border-gray-200 dark:border-gray-800 shadow-sm bg-gray-100 dark:bg-gray-950 flex-1">
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
                                    class="flex flex-col items-center justify-center p-12 text-center bg-gray-50/60 dark:bg-gray-900/40 rounded-xl"
                                    style="height: calc(100vh - 17.5rem); min-height: 560px;"
                                >
                                    <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-4 text-gray-400 dark:text-gray-500">
                                        <x-filament::icon icon="heroicon-o-document-arrow-up" class="w-8 h-8" />
                                    </div>
                                    <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 mb-1">
                                        Archivo no encontrado en almacenamiento
                                    </h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mb-5">
                                        El archivo físico para {{ $ev['label'] }} no se encuentra disponible. Puedes subir o reemplazar el archivo aquí mismo.
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
