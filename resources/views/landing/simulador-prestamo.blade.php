@extends('landing.layout')

@section('content')
    @php
        $islandProps = [
            'benefits' => $benefits->toArray(),
            'operationalState' => $operationalState,
            'targetFiscalYear' => $targetFiscalYear,
            'suspensionMessage' => $suspensionMessage,
            'hasAvailableDates' => $hasAvailableDates,
            'windowClosedMessage' => $windowClosedMessage,
        ];
    @endphp

    <div
        data-vue="loans/simulator-form"
        data-client="load"
    >
        <script type="application/json" class="vue-island-props">{!! json_encode($islandProps, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>

        @if ($isOutOfService || ! $hasAvailableDates)
            <div class="vue-island-fallback min-h-screen bg-base-200">
                <header class="bg-base-300 border-b border-base-300">
                    <div class="max-w-3xl w-full mx-auto px-4 sm:px-6 py-6 sm:py-10">
                        <div class="flex items-center gap-2 mb-2 sm:mb-4">
                            <span class="text-sm uppercase tracking-[.18em] text-base-content/60 font-semibold">
                                Sindicato Único de Trabajadores
                            </span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-base-content">
                            Simulador de Préstamo
                        </h1>
                    </div>
                </header>

                <main class="max-w-3xl w-full mx-auto px-4 sm:px-6 py-8">
                    @if ($isOutOfService)
                        <div role="alert" class="alert alert-error rounded-box shadow-sm">
                            <div>
                                <h2 class="font-semibold text-base">Servicio temporalmente fuera de servicio</h2>
                                <p class="text-sm mt-1">{{ $suspensionMessage }}</p>
                            </div>
                        </div>
                    @else
                        <div role="alert" class="alert alert-warning rounded-box shadow-sm">
                            <div>
                                <h2 class="font-semibold text-base">Sin fechas disponibles en el ejercicio actual</h2>
                                <p class="text-sm mt-1">{{ $windowClosedMessage }}</p>
                            </div>
                        </div>
                    @endif
                </main>
            </div>
        @endif
    </div>
@endsection
