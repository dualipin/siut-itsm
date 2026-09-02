@extends('landing.layout')

@section('content')
<section class="py-12 md:py-20 bg-base-100 min-h-screen text-base-content">
    <div class="container mx-auto px-4 max-w-6xl">

        <!-- Hero de la sección con animación de entrada -->
        <div class="flex flex-col md:flex-row items-center justify-between gap-8 mb-16 animate-fade-in-down">
            <div class="max-w-2xl text-center md:text-left space-y-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-primary/10 text-primary mb-1">
                    <i class="bi bi-folder2-open text-xs"></i>
                    Información Pública
                </span>
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-black tracking-tight text-base-content">
                    Transparencia
                </h1>
                <p class="text-base-content/70 text-sm sm:text-base leading-relaxed">
                    Consulta documentos, informes y datos públicos del sindicato agrupados para facilitar el acceso a la información y el cumplimiento de las normativas.
                </p>
            </div>
            <!-- Icono decorativo con animación tailwind-animations -->
            <div class="hidden md:block shrink-0 animate-bounce animate-iteration-count-infinite animate-duration-[4s]">
                <div class="p-6 bg-primary/5 rounded-3xl border border-primary/10">
                    <i class="bi bi-folder2-open text-primary/30" style="font-size: 4.5rem; line-height: 1;"></i>
                </div>
            </div>
        </div>

        <!-- Sección de Logos / Enlaces oficiales de Transparencia -->
        <div class="animate-fade-in-up animate-delay-200">
            <div class="text-center mb-8">
                <h2 class="text-[11px] font-bold text-base-content/50 uppercase tracking-widest">
                    Enlaces y Portales Oficiales de Transparencia
                </h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-5xl mx-auto items-center">
                <!-- Tarjeta 1: Buen Gobierno Tabasco -->
                <div class="card bg-base-100 border border-base-300/80 rounded-3xl overflow-hidden shadow-xs hover:shadow-md hover:border-primary/40 transition-all duration-300 hover:scale-[1.02] flex flex-col items-center justify-center p-6 h-full min-h-48 group">
                    <a href="https://tabasco.gob.mx/buen-gobierno" target="_blank" rel="noopener noreferrer"
                        class="w-full h-full flex flex-col items-center justify-center gap-4">
                        <img src="{{ asset('assets/img/logo/LOGOESC-BUEN-GOBIERNO.png') }}" alt="Logo del Buen Gobierno Tabasco"
                            class="max-h-24 w-auto object-contain transition-transform duration-300 group-hover:scale-105" width="420" height="210">
                        <span class="text-xs font-semibold text-primary hover:underline text-center">Gobierno de Tabasco</span>
                    </a>
                </div>

                <!-- Tarjeta 2: Secretaría de la Función Pública / Anticorrupción -->
                <div class="card bg-base-100 border border-base-300/80 rounded-3xl overflow-hidden shadow-xs hover:shadow-md hover:border-primary/40 transition-all duration-300 hover:scale-[1.02] flex flex-col items-center justify-center p-6 h-full min-h-48 group">
                    <a href="https://www.gob.mx/buengobierno" target="_blank" rel="noreferrer noopener"
                        class="w-full h-full flex flex-col items-center justify-center gap-4">
                        <img src="{{ asset('assets/img/logo/secretaria-anticorrupcion-y-buen-gobierno.webp') }}"
                            alt="Logo de la Secretaría de Transparencia" class="max-h-24 w-auto object-contain transition-transform duration-300 group-hover:scale-105" width="420"
                            height="210">
                        <span class="text-xs font-semibold text-primary hover:underline text-center">Secretaría de la Función Pública</span>
                    </a>
                </div>

                <!-- Tarjeta 3: OSFE -->
                <div class="card bg-base-100 border border-base-300/80 rounded-3xl overflow-hidden shadow-xs hover:shadow-md hover:border-primary/40 transition-all duration-300 hover:scale-[1.02] flex flex-col items-center justify-center p-6 h-full min-h-48 group">
                    <a href="https://osfetabasco.gob.mx/" target="_blank" rel="noreferrer noopener"
                        class="w-full h-full flex flex-col items-center justify-center gap-4">
                        <img src="{{ asset('assets/img/logo/osfe.png') }}" alt="Logo de la OSFE" class="max-h-24 w-auto object-contain transition-transform duration-300 group-hover:scale-105"
                            width="420" height="210">
                        <span class="text-xs font-semibold text-primary hover:underline text-center">Órgano Superior de Fiscalización del Estado</span>
                    </a>
                </div>
            </div>
        </div>

    </div>
</section>
@endsection