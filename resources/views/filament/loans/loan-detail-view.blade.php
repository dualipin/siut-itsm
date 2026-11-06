@php
    $canReview = $canReview ?? (auth()->user()?->isLeaderOrAdmin() ?? false);
@endphp

<div class="space-y-6">
    <!-- Header & Resumen Financiero -->
    <div class="rounded-xl border border-gray-200/80 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-col gap-4 pb-5 border-b border-gray-100 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white text-wrap-balance">
                    {{ $loan->folio }}
                </h2>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-filament::badge :color="$loan->status->getColor()" size="sm">
                    {{ $loan->status->getLabel() }}
                </x-filament::badge>
                <x-filament::badge color="gray" size="sm">
                    {{ $loan->payment_type->getLabel() }}
                </x-filament::badge>
            </div>
        </div>

        @if($loan->status->value === 'rechazada' && $loan->rejection_reason)
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50/70 p-4 text-sm text-red-900 text-wrap-pretty dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                <strong class="font-semibold">Motivo del rechazo:</strong> {{ $loan->rejection_reason }}
            </div>
        @endif

        @if($loan->status->value === 'lista_espera' && $loan->waitlist_reason)
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50/70 p-4 text-sm text-amber-900 text-wrap-pretty dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
                <strong class="font-semibold">Motivo de lista de espera:</strong> {{ $loan->waitlist_reason }}
            </div>
        @endif

        @if(!$loan->documents_generated && in_array($loan->status->value, ['aprobada', 'desembolsada']))
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50/70 p-4 text-sm text-amber-900 text-wrap-pretty dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
                <strong class="font-semibold">Documentos pendientes:</strong> La solicitud fue aprobada pero hubo un inconveniente al generar los archivos PDF automáticos. Puedes regenerarlos usando la acción del menú superior.
            </div>
        @endif

        <!-- Métricas Financieras Principales (sin tarjetas anidadas) -->
        <div class="grid grid-cols-2 gap-4 pt-5 sm:grid-cols-4 sm:gap-6">
            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Monto solicitado</dt>
                <dd class="mt-1 text-xl font-semibold tracking-tight text-gray-950 tabular-nums dark:text-white">
                    ${{ number_format($loan->requested_amount, 2) }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Monto aprobado</dt>
                <dd class="mt-1 text-xl font-semibold tracking-tight tabular-nums {{ $loan->approved_amount ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500 text-base font-normal' }}">
                    {{ $loan->approved_amount ? '$'.number_format($loan->approved_amount, 2) : 'Por dictaminar' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Tasa mensual</dt>
                <dd class="mt-1 text-xl font-semibold tracking-tight text-gray-950 tabular-nums dark:text-white">
                    {{ $loan->monthly_rate !== null ? number_format($loan->monthly_rate, 2).'%' : 'Por dictaminar' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Desembolso</dt>
                <dd class="mt-1 text-xl font-semibold tracking-tight text-gray-950 tabular-nums dark:text-white">
                    {{ $loan->disbursed_at ? $loan->disbursed_at->format('d/m/Y') : 'Pendiente' }}
                </dd>
            </div>
        </div>
    </div>

    <!-- 2 Column Details: Solicitante & Condiciones -->
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <!-- Solicitante -->
        <div class="rounded-xl border border-gray-200/80 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white text-wrap-balance border-b border-gray-100 dark:border-gray-800 pb-3">
                Datos del solicitante
            </h3>
            <dl class="divide-y divide-gray-100 dark:divide-gray-800/60 text-sm">
                <div class="py-2.5 flex justify-between items-center gap-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Nombre</dt>
                    <dd class="text-sm font-medium text-gray-900 dark:text-gray-100 text-right">{{ $user->full_name }}</dd>
                </div>
                <div class="py-2.5 flex justify-between items-center gap-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">CURP</dt>
                    <dd class="text-xs font-mono tabular-nums text-gray-900 dark:text-gray-100">{{ $user->curp ?? 'N/A' }}</dd>
                </div>
                <div class="py-2.5 flex justify-between items-center gap-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Categoría</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100 text-right">{{ $user->category ?? 'N/A' }}</dd>
                </div>
                <div class="py-2.5 flex justify-between items-center gap-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Teléfono</dt>
                    <dd class="text-xs font-mono tabular-nums text-gray-900 dark:text-gray-100">{{ $user->phone ?? 'N/A' }}</dd>
                </div>
                <div class="py-2.5 flex justify-between items-center gap-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Dirección</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100 text-right text-wrap-pretty">{{ $user->address ?? 'N/A' }}</dd>
                </div>
                <div class="py-2.5 flex justify-between items-center gap-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Cuenta bancaria</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100 text-right">
                        {{ $loan->bank_name ?? 'N/A' }} <span class="text-xs font-mono tabular-nums text-gray-500 dark:text-gray-400">({{ $loan->bank_account ?? 'N/A' }})</span>
                    </dd>
                </div>
                <div class="py-2.5 flex justify-between items-center gap-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">CLABE interbancaria</dt>
                    <dd class="text-xs font-mono tabular-nums text-gray-900 dark:text-gray-100">{{ $loan->interbank_clabe ?? 'N/A' }}</dd>
                </div>
            </dl>
        </div>

        <!-- Condiciones de Amortización -->
        <div class="rounded-xl border border-gray-200/80 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white text-wrap-balance border-b border-gray-100 dark:border-gray-800 pb-3">
                Condiciones de amortización
            </h3>
            <dl class="divide-y divide-gray-100 dark:divide-gray-800/60 text-sm">
                <div class="py-2.5 flex justify-between items-center gap-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Forma de pago</dt>
                    <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $loan->payment_type->getLabel() }}</dd>
                </div>
                @if($loan->payment_type->hasNomina())
                    <div class="py-2.5 flex justify-between items-center gap-4">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Monto por nómina</dt>
                        <dd class="text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100">${{ number_format($loan->nomina_amount, 2) }}</dd>
                    </div>
                    <div class="py-2.5 flex justify-between items-center gap-4">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Plazo en quincenas</dt>
                        <dd class="text-sm tabular-nums text-gray-900 dark:text-gray-100">{{ $loan->nomina_fortnights }} quincenas</dd>
                    </div>
                    <div class="py-2.5 flex justify-between items-center gap-4">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Primera quincena</dt>
                        <dd class="text-xs font-mono tabular-nums text-gray-900 dark:text-gray-100">{{ $loan->first_fortnight_date?->format('d/m/Y') ?? 'N/A' }}</dd>
                    </div>
                    @if($loan->salary_commitment_amount)
                        <div class="py-2.5 flex justify-between items-center gap-4">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Compromiso salarial</dt>
                            <dd class="text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100">${{ number_format($loan->salary_commitment_amount, 2) }}</dd>
                        </div>
                    @endif
                @endif

                @if($loan->loanBenefits->isNotEmpty())
                    <div class="py-2.5 space-y-1.5">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Prestaciones asignadas</dt>
                        <dd class="space-y-1">
                            @foreach($loan->loanBenefits as $lb)
                                <div class="flex justify-between items-center text-xs text-gray-700 dark:text-gray-300">
                                    <span>{{ $lb->benefit->name ?? 'Prestación' }} <span class="text-gray-400 tabular-nums">({{ $lb->collection_date?->format('d/m/Y') }})</span></span>
                                    <span class="font-semibold tabular-nums text-gray-900 dark:text-white">${{ number_format($lb->approved_amount ?? $lb->requested_amount, 2) }}</span>
                                </div>
                            @endforeach
                        </dd>
                    </div>
                @endif

                <div class="py-2.5 flex justify-between items-center gap-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Firma del solicitante</dt>
                    <dd>
                        <x-filament::badge :color="$loan->isSignatureValid() ? 'success' : 'warning'" size="sm">
                            {{ $loan->isSignatureValid() ? 'Firma verificada' : 'Pendiente / Invalidada' }}
                        </x-filament::badge>
                    </dd>
                </div>

                @if($loan->signature_data)
                    <div class="py-2.5 flex items-center justify-between gap-4">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Trazo registrado</dt>
                        <dd class="rounded-lg border border-gray-200 bg-white p-1.5 dark:border-gray-700">
                            <img src="{{ $loan->signature_data }}" class="max-h-14 max-w-32 object-contain" alt="Firma registrada">
                        </dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

    <!-- Expediente de Evidencias Documentales -->
    <div class="rounded-xl border border-gray-200/80 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-4">
            <div>
                <h3 class="text-base font-semibold text-gray-950 dark:text-white text-wrap-balance">
                    Expediente documental
                </h3>
            </div>
            <div class="flex items-center gap-2">
                @if($canReview && $evidences->isNotEmpty())
                    <x-filament::button
                        :href="\App\Filament\Resources\Loans\LoanResource::getUrl('review-documents', ['record' => $loan])"
                        tag="a"
                        size="xs"
                        color="info"
                        icon="heroicon-m-document-check"
                    >
                        Validar Evidencias
                    </x-filament::button>
                @endif
                <x-filament::badge :color="$loan->hasCompleteEvidence() ? 'success' : 'warning'" size="sm">
                    {{ $loan->hasCompleteEvidence() ? 'Expediente completo' : 'Expediente incompleto' }}
                </x-filament::badge>
            </div>
        </div>

        @if($evidences->isEmpty())
            <div class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                No se han registrado evidencias documentales para esta solicitud.
            </div>
        @else
            <div class="divide-y divide-gray-100 dark:divide-gray-800/80">
                @foreach($evidences as $ev)
                    <div class="py-4 space-y-2.5 first:pt-4 last:pb-0">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-xs font-semibold uppercase px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    {{ $ev->source_type }}
                                </span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $ev->source_type === 'nomina' ? 'Comprobante de Nómina' : ($ev->benefit->name ?? 'Prestación') }}
                                </span>
                                <span class="text-xs text-gray-500 tabular-nums">({{ round($ev->file_size / 1024) }} KB)</span>
                            </div>

                            <div class="flex items-center gap-2">
                                @if($canReview)
                                    <x-filament::badge :color="$ev->ai_status->getColor()" size="sm">
                                        IA: {{ $ev->ai_status->getLabel() }}
                                    </x-filament::badge>

                                    @if($ev->manual_review_status === 'approved')
                                        <x-filament::badge color="success" size="sm">
                                            Manual: Aprobado
                                        </x-filament::badge>
                                    @elseif($ev->manual_review_status === 'rejected')
                                        <x-filament::badge color="danger" size="sm">
                                            Manual: Rechazado
                                        </x-filament::badge>
                                    @endif
                                @else
                                    @if($ev->isValidOrApproved())
                                        <x-filament::badge color="success" size="sm">
                                            Aprobado
                                        </x-filament::badge>
                                    @elseif($ev->manual_review_status === 'rejected')
                                        <x-filament::badge color="danger" size="sm">
                                            Rechazado
                                        </x-filament::badge>
                                    @else
                                        <x-filament::badge color="warning" size="sm">
                                            En revisión
                                        </x-filament::badge>
                                    @endif
                                @endif

                                @if($canReview)
                                    <a href="{{ \App\Filament\Resources\Loans\LoanResource::getUrl('review-documents', ['record' => $loan, 'evidence' => $ev->id]) }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 underline-offset-2 hover:underline ml-1">
                                        Validar
                                    </a>
                                @endif

                                <a href="{{ route('loans.evidences.download', ['loan' => $loan->id, 'evidence' => $ev->id]) }}" target="_blank" class="text-xs font-semibold text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 underline-offset-2 hover:underline ml-1">
                                    Descargar
                                </a>
                            </div>
                        </div>

                        @if($canReview)
                            <!-- Metadatos extraídos por IA (diseño limpio y plano) -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs text-gray-600 dark:text-gray-400 pt-1">
                                <div><span class="text-gray-500 dark:text-gray-400">Clasificación IA:</span> <span class="font-medium text-gray-900 dark:text-gray-200">{{ $ev->ai_classification ?? 'N/A' }}</span></div>
                                <div><span class="text-gray-500 dark:text-gray-400">Confianza:</span> <span class="font-medium tabular-nums text-gray-900 dark:text-gray-200">{{ $ev->ai_confidence ? number_format($ev->ai_confidence * 100, 1).'%' : 'N/A' }}</span></div>
                                <div><span class="text-gray-500 dark:text-gray-400">Nombre detectado:</span> <span class="font-medium text-gray-900 dark:text-gray-200">{{ $ev->extracted_data['nombre_trabajador'] ?? 'N/A' }}</span></div>
                            </div>
                        @endif

                        @if($ev->manual_review_notes)
                            <div class="text-xs text-gray-600 dark:text-gray-400 text-wrap-pretty pt-1">
                                <span class="font-semibold text-gray-700 dark:text-gray-300">Nota del revisor:</span> {{ $ev->manual_review_notes }}
                                <span class="text-gray-400">({{ $ev->reviewer->full_name ?? 'Revisor' }})</span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Documentos Oficiales Generados -->
    <div class="rounded-xl border border-gray-200/80 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <div class="border-b border-gray-100 dark:border-gray-800 pb-4">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white text-wrap-balance">
                Documentos contractuales generados
            </h3>
        </div>

        @php
            $docTypes = [
                'solicitud' => 'Formato de solicitud',
                'pagare' => 'Pagaré contractual',
                'anuencia' => 'Anuencia de descuento',
                'corrida_financiera' => 'Corrida financiera',
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 pt-5">
            @foreach($docTypes as $key => $title)
                @php $doc = $generatedDocs->get($key)?->first(); @endphp
                <div class="rounded-lg border border-gray-200/70 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-800/40 flex flex-col justify-between">
                    <div>
                        <span class="text-xs font-medium text-gray-700 dark:text-gray-300 block">{{ $title }}</span>
                        <div class="mt-2 text-xs">
                            @if($doc)
                                <span class="font-semibold text-emerald-600 dark:text-emerald-400 block tabular-nums">v{{ $doc->version }} generada</span>
                                <span class="text-gray-500 dark:text-gray-400 text-xs tabular-nums">{{ $doc->generated_at?->format('d/m/Y H:i') }}</span>
                            @else
                                <span class="text-gray-400 dark:text-gray-500 italic">No generado</span>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-gray-200/60 dark:border-gray-700/60">
                        @if($doc)
                            <a href="{{ route('loans.documents.download', ['loan' => $loan->id, 'document' => $doc->id]) }}" target="_blank" class="w-full text-center block text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md py-1.5 transition-colors">
                                Descargar PDF
                            </a>
                        @else
                            <button disabled type="button" class="w-full text-center block text-xs text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-800 rounded-md py-1.5 cursor-not-allowed">
                                Pendiente
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Tabla de Amortización Persistida -->
    @if($amortizationRows->isNotEmpty())
        <div class="rounded-xl border border-gray-200/80 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-gray-100 dark:border-gray-800 pb-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white text-wrap-balance">
                        Tabla de amortización quincenal
                    </h3>
                </div>
                @if($financialSummary)
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Pago total estimado:
                        <span class="text-sm font-semibold tabular-nums text-gray-950 dark:text-white ml-1">
                            ${{ number_format($financialSummary->total_payment, 2) }}
                        </span>
                    </div>
                @endif
            </div>

            <div class="overflow-x-auto pt-4">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400">
                            <th class="py-2.5 px-3 font-medium">Período</th>
                            <th class="py-2.5 px-3 font-medium">Concepto</th>
                            <th class="py-2.5 px-3 font-medium">Fecha</th>
                            <th class="py-2.5 px-3 font-medium text-right">Saldo Inicial</th>
                            <th class="py-2.5 px-3 font-medium text-right">Capital</th>
                            <th class="py-2.5 px-3 font-medium text-right">Interés</th>
                            <th class="py-2.5 px-3 font-medium text-right">Pago</th>
                            <th class="py-2.5 px-3 font-medium text-right">Saldo Final</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800/60">
                        @foreach($amortizationRows as $r)
                            <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-800/30 transition-colors">
                                <td class="py-2.5 px-3 font-mono tabular-nums text-gray-500 dark:text-gray-400">{{ $r->period }}</td>
                                <td class="py-2.5 px-3 font-medium text-gray-900 dark:text-gray-100">{{ $r->label }}</td>
                                <td class="py-2.5 px-3 tabular-nums text-gray-600 dark:text-gray-300">{{ $r->date?->format('d/m/Y') }}</td>
                                <td class="py-2.5 px-3 text-right tabular-nums text-gray-600 dark:text-gray-300">${{ number_format($r->opening_balance, 2) }}</td>
                                <td class="py-2.5 px-3 text-right tabular-nums text-gray-600 dark:text-gray-300">${{ number_format($r->principal, 2) }}</td>
                                <td class="py-2.5 px-3 text-right tabular-nums text-gray-600 dark:text-gray-300">${{ number_format($r->interest, 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-semibold tabular-nums text-gray-950 dark:text-white">${{ number_format($r->payment, 2) }}</td>
                                <td class="py-2.5 px-3 text-right tabular-nums text-gray-600 dark:text-gray-400">${{ number_format($r->closing_balance, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Historial de Transiciones y Auditoría -->
    @if($statusHistories->isNotEmpty())
        <div class="rounded-xl border border-gray-200/80 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white text-wrap-balance border-b border-gray-100 dark:border-gray-800 pb-3">
                Historial de transiciones y auditoría
            </h3>
            <div class="divide-y divide-gray-100 dark:divide-gray-800/60">
                @foreach($statusHistories as $h)
                    <div class="py-2.5 flex items-start justify-between gap-4 text-xs first:pt-3 last:pb-0">
                        <div>
                            <span class="font-medium text-gray-900 dark:text-gray-100">
                                {{ $h->from_status ? $h->from_status->getLabel().' → ' : '' }}{{ $h->to_status->getLabel() }}
                            </span>
                            @if($h->comment)
                                <p class="text-gray-500 dark:text-gray-400 mt-0.5 text-wrap-pretty">{{ $h->comment }}</p>
                            @endif
                        </div>
                        <div class="text-right text-gray-500 dark:text-gray-400 shrink-0">
                            <div>{{ $h->user->full_name ?? 'Sistema' }}</div>
                            <div class="text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ $h->created_at?->format('d/m/Y H:i') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

                            <span class="font-medium text-gray-900 dark:text-gray-100">
                                {{ $h->from_status ? $h->from_status->getLabel().' → ' : '' }}{{ $h->to_status->getLabel() }}
                            </span>
                            @if($h->comment)
                                <p class="text-gray-500 dark:text-gray-400 mt-0.5 text-wrap-pretty">{{ $h->comment }}</p>
                            @endif
                        </div>
                        <div class="text-right text-gray-500 dark:text-gray-400 shrink-0">
                            <div>{{ $h->user->full_name ?? 'Sistema' }}</div>
                            <div class="text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ $h->created_at?->format('d/m/Y H:i') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
                                {{ $h->from_status ? $h->from_status->getLabel().' → ' : '' }}{{ $h->to_status->getLabel() }}
                            </span>
                            @if($h->comment)
                                <p class="text-gray-500 dark:text-gray-400 mt-0.5 text-wrap-pretty">{{ $h->comment }}</p>
                            @endif
                        </div>
                        <div class="text-right text-gray-500 dark:text-gray-400 shrink-0">
                            <div>{{ $h->user->full_name ?? 'Sistema' }}</div>
                            <div class="text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ $h->created_at?->format('d/m/Y H:i') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
