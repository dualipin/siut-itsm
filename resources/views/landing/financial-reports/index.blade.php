@extends('landing.layout')

@section('content')
<section class="py-12 md:py-20 bg-base-100 min-h-screen text-base-content">
    <div class="container mx-auto px-4 max-w-6xl">

        <!-- Hero de la sección -->
        <div class="max-w-2xl mx-auto text-center mb-10 animate-fade-in-down">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-primary/10 text-primary mb-3">
                <i class="bi bi-shield-check text-xs"></i>
                Transparencia
            </span>
            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight mb-3 text-base-content">
                Informes Financieros
            </h1>
            <p class="text-base-content/70 text-sm sm:text-base">
                Consulta los ejercicios contables registrados del {{ config('syndicate.name') }}.
            </p>
        </div>

        <!-- Barra de Búsqueda y Filtros de Año -->
        <div class="mb-8 space-y-4">
            <!-- Buscador por año -->
            <form action="{{ route('financial-reports.index') }}" method="GET" class="max-w-md mx-auto">
                <div class="relative">
                    <input type="text"
                           name="search"
                           value="{{ request('search') }}"
                           placeholder="Buscar año (ej. {{ date('Y') }})..."
                           class="input input-bordered w-full rounded-xl pl-10 pr-24 py-2.5 bg-base-200/50 focus:bg-base-100 focus:border-primary text-sm shadow-xs transition-all" />
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-base-content/40">
                        <i class="bi bi-search text-sm"></i>
                    </span>
                    @if(request('search') || request('year'))
                        <a href="{{ route('financial-reports.index') }}"
                           class="absolute right-20 top-1/2 -translate-y-1/2 text-base-content/40 hover:text-base-content text-xs"
                           title="Limpiar">
                            <i class="bi bi-x-circle-fill text-sm"></i>
                        </a>
                    @endif
                    <button type="submit" class="btn btn-sm btn-primary rounded-lg absolute right-1.5 top-1/2 -translate-y-1/2 text-xs font-medium">
                        Buscar
                    </button>
                </div>
            </form>

            <!-- Píldoras de Selección de Años -->
            @if($availableYears->isNotEmpty())
                <div class="flex items-center justify-center gap-2 flex-wrap">
                    <a href="{{ route('financial-reports.index') }}"
                       class="px-3.5 py-1 rounded-full text-xs font-medium transition-all border inline-flex items-center gap-1
                       {{ ! $selectedYear
                           ? 'bg-primary text-primary-content border-primary shadow-xs'
                           : 'bg-base-200/60 text-base-content/70 border-base-300 hover:bg-base-200 hover:text-base-content' }}">
                        Todos
                    </a>

                    @foreach($availableYears as $year)
                        <a href="{{ route('financial-reports.index', ['year' => $year]) }}"
                           class="px-3.5 py-1 rounded-full text-xs font-medium transition-all border inline-flex items-center
                           {{ ($selectedYear === (int) $year)
                               ? 'bg-primary text-primary-content border-primary shadow-xs'
                               : 'bg-base-200/60 text-base-content/70 border-base-300 hover:bg-base-200 hover:text-base-content' }}">
                            {{ $year }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Indicador de filtro activo -->
        @if($selectedYear || request('search'))
            <div class="max-w-md mx-auto mb-6 flex items-center justify-between bg-base-200/60 px-4 py-2 rounded-xl border border-base-300 text-xs text-base-content/80">
                <span>
                    Año: <strong class="text-base-content">{{ $selectedYear ?? request('search') }}</strong>
                    ({{ $reports->total() }})
                </span>
                <a href="{{ route('financial-reports.index') }}" class="text-primary hover:underline font-medium">
                    Limpiar filtro
                </a>
            </div>
        @endif

        <!-- Grid de Informes Financieros -->
        @if($reports->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($reports as $report)
                    <div class="card bg-base-100 border border-base-300/80 rounded-2xl p-5 shadow-xs hover:shadow-md hover:border-primary/40 transition-all flex flex-col justify-between gap-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-base-content/50">
                                    Ejercicio Fiscal
                                </span>
                                <h2 class="text-2xl font-bold text-base-content">
                                    {{ $report->year }}
                                </h2>
                            </div>
                            <span class="badge badge-sm badge-neutral/10 text-base-content/60 border-0 gap-1 font-medium">
                                <i class="bi bi-file-earmark-lock2 text-warning text-xs"></i>
                                Documento protegido
                            </span>
                        </div>

                        <div class="pt-3 border-t border-base-200/80 flex items-center justify-between gap-2 text-xs">
                            <span class="text-base-content/50 text-[11px]">
                                Acceso reservado a agremiados
                            </span>

                            @auth
                                <a href="{{ route('filament.portal.resources.financial-reports.index') }}"
                                   class="btn btn-xs btn-primary rounded-lg font-medium">
                                    Ver en portal
                                </a>
                            @else
                                <a href="{{ route('filament.portal.auth.login') }}"
                                   class="btn btn-xs btn-outline btn-primary rounded-lg font-medium">
                                    Iniciar sesión para ver
                                </a>
                            @endauth
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Paginación -->
            <div class="mt-10 flex justify-center">
                {{ $reports->links() }}
            </div>
        @else
            <!-- Estado Vacío -->
            <div class="card bg-base-100 border border-base-300/80 rounded-2xl p-8 max-w-sm mx-auto text-center shadow-xs">
                <i class="bi bi-folder-x text-3xl text-base-content/30 mb-2"></i>
                <h3 class="text-base font-bold text-base-content mb-1">
                    No se encontraron informes
                </h3>
                <p class="text-xs text-base-content/60 mb-4">
                    @if($selectedYear)
                        No hay informes para el año {{ $selectedYear }}.
                    @else
                        No hay informes registrados.
                    @endif
                </p>
                <a href="{{ route('financial-reports.index') }}" class="btn btn-xs btn-primary rounded-lg mx-auto">
                    Ver todos
                </a>
            </div>
        @endif

    </div>
</section>
@endsection
