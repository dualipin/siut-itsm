<div class="space-y-6">
    <!-- Header Summary Card -->
    <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 dark:border-gray-800 pb-4">
            <div>
                <span class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Solicitud de Préstamo</span>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                    {{ $loan->folio }}
                </h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-3 py-1 text-xs font-bold rounded-full bg-{{ $loan->status->getColor() }}-100 text-{{ $loan->status->getColor() }}-800 border border-{{ $loan->status->getColor() }}-300">
                    {{ $loan->status->getLabel() }}
                </span>
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {{ $loan->payment_type->getLabel() }}
                </span>
            </div>
        </div>

        @if($loan->status->value === 'rechazada' && $loan->rejection_reason)
            <div class="mt-4 p-4 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900 rounded-xl text-sm text-red-800 dark:text-red-200">
                <strong>Motivo del Rechazo:</strong> {{ $loan->rejection_reason }}
            </div>
        @endif

        @if($loan->status->value === 'lista_espera' && $loan->waitlist_reason)
            <div class="mt-4 p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900 rounded-xl text-sm text-amber-800 dark:text-amber-200">
                <strong>Motivo de Lista de Espera:</strong> {{ $loan->waitlist_reason }}
            </div>
        @endif

        @if(!$loan->documents_generated && in_array($loan->status->value, ['aprobada', 'desembolsada']))
            <div class="mt-4 p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900 rounded-xl text-sm text-amber-800 dark:text-amber-200 flex items-center justify-between">
                <div>
                    <strong>Documentos pendientes:</strong> La solicitud fue aprobada pero hubo un inconveniente al generar los archivos PDF automáticos. Puedes regenerarlos usando la acción del menú superior.
                </div>
            </div>
        @endif

        <!-- Key Financial Stats -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6">
            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                <span class="text-xs text-gray-500 block">Monto Solicitado</span>
                <span class="text-lg font-bold text-gray-900 dark:text-white">$ {{ number_format($loan->requested_amount, 2) }}</span>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                <span class="text-xs text-gray-500 block">Monto Aprobado</span>
                <span class="text-lg font-bold text-green-600 dark:text-green-400">
                    {{ $loan->approved_amount ? '$ '.number_format($loan->approved_amount, 2) : 'Por dictaminar' }}
                </span>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                <span class="text-xs text-gray-500 block">Tasa Mensual</span>
                <span class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($loan->monthly_rate, 2) }}%</span>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                <span class="text-xs text-gray-500 block">Desembolso</span>
                <span class="text-lg font-bold text-gray-900 dark:text-white">
                    {{ $loan->disbursed_at ? $loan->disbursed_at->format('d/m/Y') : 'Pendiente' }}
                </span>
            </div>
        </div>
    </div>

    <!-- 2 Column Details: Solicitante & Detalles de Pago -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Solicitante -->
        <div class="p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm space-y-3">
            <h3 class="font-bold text-sm text-gray-900 dark:text-white uppercase tracking-wider border-b border-gray-100 dark:border-gray-800 pb-2">
                Datos del Solicitante
            </h3>
            <div class="text-sm space-y-2">
                <div class="flex justify-between"><span class="text-gray-500">Nombre:</span> <strong class="text-gray-900 dark:text-white">{{ $user->full_name }}</strong></div>
                <div class="flex justify-between"><span class="text-gray-500">CURP:</span> <strong class="text-gray-900 dark:text-white">{{ $user->curp ?? 'N/A' }}</strong></div>
                <div class="flex justify-between"><span class="text-gray-500">Categoría:</span> <strong class="text-gray-900 dark:text-white">{{ $user->category ?? 'N/A' }}</strong></div>
                <div class="flex justify-between"><span class="text-gray-500">Teléfono:</span> <strong class="text-gray-900 dark:text-white">{{ $user->phone ?? 'N/A' }}</strong></div>
                <div class="flex justify-between"><span class="text-gray-500">Dirección:</span> <strong class="text-gray-900 dark:text-white">{{ $user->address ?? 'N/A' }}</strong></div>
                <div class="flex justify-between"><span class="text-gray-500">Cuenta / Banco:</span> <strong class="text-gray-900 dark:text-white">{{ $loan->bank_name ?? 'N/A' }} ({{ $loan->bank_account ?? 'N/A' }})</strong></div>
                <div class="flex justify-between"><span class="text-gray-500">CLABE:</span> <strong class="text-gray-900 dark:text-white font-mono">{{ $loan->interbank_clabe ?? 'N/A' }}</strong></div>
            </div>
        </div>

        <!-- Condiciones del Préstamo -->
        <div class="p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm space-y-3">
            <h3 class="font-bold text-sm text-gray-900 dark:text-white uppercase tracking-wider border-b border-gray-100 dark:border-gray-800 pb-2">
                Condiciones de Amortización
            </h3>
            <div class="text-sm space-y-2">
                <div class="flex justify-between"><span class="text-gray-500">Forma de Pago:</span> <strong class="text-gray-900 dark:text-white">{{ $loan->payment_type->getLabel() }}</strong></div>
                @if($loan->payment_type->hasNomina())
                    <div class="flex justify-between"><span class="text-gray-500">Monto por Nómina:</span> <strong class="text-gray-900 dark:text-white">$ {{ number_format($loan->nomina_amount, 2) }}</strong></div>
                    <div class="flex justify-between"><span class="text-gray-500">Plazo en Quincenas:</span> <strong class="text-gray-900 dark:text-white">{{ $loan->nomina_fortnights }} quincenas</strong></div>
                    <div class="flex justify-between"><span class="text-gray-500">Primera Quincena:</span> <strong class="text-gray-900 dark:text-white">{{ $loan->first_fortnight_date?->format('d/m/Y') ?? 'N/A' }}</strong></div>
                    @if($loan->salary_commitment_amount)
                        <div class="flex justify-between"><span class="text-gray-500">Compromiso Salarial:</span> <strong class="text-gray-900 dark:text-white">$ {{ number_format($loan->salary_commitment_amount, 2) }}</strong></div>
                    @endif
                @endif

                @if($loan->loanBenefits->isNotEmpty())
                    <div class="pt-2 border-t border-gray-100 dark:border-gray-800">
                        <span class="text-xs font-semibold text-gray-500 block mb-1">Prestaciones Asignadas:</span>
                        @foreach($loan->loanBenefits as $lb)
                            <div class="flex justify-between text-xs py-1">
                                <span>{{ $lb->benefit->name ?? 'Prestación' }} ({{ $lb->collection_date?->format('d/m/Y') }}):</span>
                                <strong>$ {{ number_format($lb->approved_amount ?? $lb->requested_amount, 2) }}</strong>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex justify-between pt-2 border-t border-gray-100 dark:border-gray-800">
                    <span class="text-gray-500">Firma del Solicitante:</span>
                    <strong class="{{ $loan->isSignatureValid() ? 'text-green-600' : 'text-amber-600' }}">
                        {{ $loan->isSignatureValid() ? '✓ Firma válida y verificada' : 'Pendiente / Invalidada' }}
                    </strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Expediente de Evidencias Documentales -->
    <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
            <div>
                <h3 class="font-bold text-base text-gray-900 dark:text-white">Expediente Documental y Verificación IA</h3>
                <p class="text-xs text-gray-500">Comprobantes presentados por cada fuente de pago y dictamen automático.</p>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $loan->hasCompleteEvidence() ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                {{ $loan->hasCompleteEvidence() ? 'Expediente Completo' : 'Expediente Incompleto' }}
            </span>
        </div>

        @if($evidences->isEmpty())
            <div class="text-center py-6 text-sm text-gray-500">
                No se han registrado evidencias documentales para esta solicitud.
            </div>
        @else
            <div class="space-y-4">
                @foreach($evidences as $ev)
                    <div class="p-4 rounded-xl border {{ $ev->isValidOrApproved() ? 'border-green-200 bg-green-50/20 dark:border-green-900/40 dark:bg-green-950/10' : 'border-amber-200 bg-amber-50/20 dark:border-amber-900/40 dark:bg-amber-950/10' }} space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <span class="text-xs font-mono uppercase px-2 py-0.5 rounded bg-gray-200 dark:bg-gray-800 font-semibold">{{ $ev->source_type }}</span>
                                <strong class="ml-2 text-sm text-gray-900 dark:text-white">
                                    {{ $ev->source_type === 'nomina' ? 'Comprobante de Nómina' : ($ev->benefit->name ?? 'Prestación') }}
                                </strong>
                                <span class="text-xs text-gray-500 ml-2">({{ round($ev->file_size / 1024) }} KB)</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-{{ $ev->ai_status->getColor() }}-100 text-{{ $ev->ai_status->getColor() }}-800">
                                    IA: {{ $ev->ai_status->getLabel() }}
                                </span>
                                @if($ev->manual_review_status === 'approved')
                                    <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-800">
                                        ✓ Aprobado manual
                                    </span>
                                @elseif($ev->manual_review_status === 'rejected')
                                    <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-800">
                                        ✕ Rechazado manual
                                    </span>
                                @endif
                                <a href="{{ route('loans.evidences.download', ['loan' => $loan->id, 'evidence' => $ev->id]) }}" target="_blank" class="text-xs font-semibold text-primary-600 hover:underline">
                                    Descargar Archivo ↗
                                </a>
                            </div>
                        </div>

                        <!-- Extracted Data & Comparison Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs bg-white dark:bg-gray-800/80 p-3 rounded-lg border border-gray-100 dark:border-gray-800">
                            <div><span class="text-gray-500">Clasificación IA:</span> <strong>{{ $ev->ai_classification ?? 'N/A' }}</strong></div>
                            <div><span class="text-gray-500">Confianza:</span> <strong>{{ $ev->ai_confidence ? number_format($ev->ai_confidence * 100, 1).'%' : 'N/A' }}</strong></div>
                            <div><span class="text-gray-500">Nombre detectado:</span> <strong>{{ $ev->extracted_data['nombre_trabajador'] ?? 'N/A' }}</strong></div>
                        </div>

                        @if($ev->manual_review_notes)
                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                <strong>Nota del revisor:</strong> {{ $ev->manual_review_notes }} (por {{ $ev->reviewer->full_name ?? 'Revisor' }})
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Documentos Oficiales Generados (PDFs) -->
    <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm space-y-4">
        <div class="border-b border-gray-100 dark:border-gray-800 pb-3">
            <h3 class="font-bold text-base text-gray-900 dark:text-white">Documentos Oficiales Generados (PDFs Privados)</h3>
            <p class="text-xs text-gray-500">Expediente contractual generado para firma y resguardo oficial.</p>
        </div>

        @php
            $docTypes = [
                'solicitud' => 'Formato de Solicitud',
                'pagare' => 'Pagaré Contractual',
                'anuencia' => 'Anuencia de Descuento',
                'corrida_financiera' => 'Corrida Financiera Oficial',
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            @foreach($docTypes as $key => $title)
                @php $doc = $generatedDocs->get($key)?->first(); @endphp
                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 flex flex-col justify-between">
                    <div>
                        <span class="text-xs font-semibold text-gray-500 block uppercase">{{ $title }}</span>
                        <div class="mt-2 text-xs">
                            @if($doc)
                                <span class="font-bold text-green-600 block">v{{ $doc->version }} generada</span>
                                <span class="text-gray-400 text-[10px]">{{ $doc->generated_at?->format('d/m/Y H:i') }}</span>
                            @else
                                <span class="text-gray-400 italic">No generado</span>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">
                        @if($doc)
                            <a href="{{ route('loans.documents.download', ['loan' => $loan->id, 'document' => $doc->id]) }}" target="_blank" class="w-full text-center block text-xs font-bold text-primary-600 hover:text-primary-700 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg py-1.5 shadow-sm">
                                Descargar PDF ↗
                            </a>
                        @else
                            <button disabled class="w-full text-center block text-xs text-gray-400 bg-gray-100 dark:bg-gray-800 rounded-lg py-1.5 cursor-not-allowed">
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
        <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                <div>
                    <h3 class="font-bold text-base text-gray-900 dark:text-white">Tabla de Amortización Quincenal</h3>
                    <p class="text-xs text-gray-500">Corrida persistida calculada con el método oficial.</p>
                </div>
                @if($financialSummary)
                    <div class="text-right text-xs">
                        <span class="text-gray-500">Pago Total:</span>
                        <strong class="text-sm font-bold text-gray-900 dark:text-white">$ {{ number_format($financialSummary->total_payment, 2) }}</strong>
                    </div>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                            <th class="p-2 font-semibold">Período</th>
                            <th class="p-2 font-semibold">Concepto</th>
                            <th class="p-2 font-semibold">Fecha</th>
                            <th class="p-2 font-semibold text-right">Saldo Inicial</th>
                            <th class="p-2 font-semibold text-right">Capital</th>
                            <th class="p-2 font-semibold text-right">Interés</th>
                            <th class="p-2 font-semibold text-right">Pago</th>
                            <th class="p-2 font-semibold text-right">Saldo Final</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($amortizationRows as $r)
                            <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50/50 dark:hover:bg-gray-800/20">
                                <td class="p-2 font-mono">{{ $r->period }}</td>
                                <td class="p-2 font-medium">{{ $r->label }}</td>
                                <td class="p-2">{{ $r->date?->format('d/m/Y') }}</td>
                                <td class="p-2 text-right">$ {{ number_format($r->opening_balance, 2) }}</td>
                                <td class="p-2 text-right">$ {{ number_format($r->principal, 2) }}</td>
                                <td class="p-2 text-right">$ {{ number_format($r->interest, 2) }}</td>
                                <td class="p-2 text-right font-bold text-gray-900 dark:text-white">$ {{ number_format($r->payment, 2) }}</td>
                                <td class="p-2 text-right font-mono">$ {{ number_format($r->closing_balance, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Historial de Auditoría de Estados -->
    @if($statusHistories->isNotEmpty())
        <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm space-y-3">
            <h3 class="font-bold text-sm text-gray-900 dark:text-white uppercase tracking-wider border-b border-gray-100 dark:border-gray-800 pb-2">
                Historial de Transiciones y Auditoría
            </h3>
            <div class="space-y-2">
                @foreach($statusHistories as $h)
                    <div class="flex items-start justify-between text-xs py-1 border-b border-gray-50 dark:border-gray-800/40">
                        <div>
                            <span class="font-semibold text-gray-900 dark:text-white">
                                {{ $h->from_status ? $h->from_status->getLabel().' → ' : '' }}{{ $h->to_status->getLabel() }}
                            </span>
                            @if($h->comment)
                                <p class="text-gray-500 mt-0.5">{{ $h->comment }}</p>
                            @endif
                        </div>
                        <div class="text-right text-gray-400">
                            <div>{{ $h->user->full_name ?? 'Sistema' }}</div>
                            <div class="text-[10px]">{{ $h->created_at?->format('d/m/Y H:i') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
