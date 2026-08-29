@extends('landing.layout')
@section('content')

  <section class="py-12 md:py-20 bg-base-100 text-base-content overflow-x-hidden">
    <div class="container mx-auto px-4 max-w-7xl">
      <div class="mb-10 animate-fade-in-down animate-duration-normal">
        <h1 class="text-3xl md:text-5xl font-black uppercase tracking-tight text-primary">
          Conoce tu Sindicato
        </h1>
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 mt-8 animate-fade-in-up">
        <!-- Contenido principal (Misión, Visión, etc.) -->
        <div class="lg:col-span-7 lg:order-2 space-y-8">
          <div class="max-w-2xl lg:ml-auto">
            <span
              class="block uppercase text-xs font-semibold tracking-wider text-base-content/60 mb-3 animate-fade-in-up timeline-view animate-range-entry animate-delay-100">
              Sobre Nosotros
            </span>

            <div class="space-y-8">
              <!-- MISIÓN -->
              <div class="animate-fade-in-up timeline-view animate-range-entry animate-delay-100">
                <h2 class="text-2xl font-bold text-primary mb-3 uppercase tracking-wide">Misión</h2>
                <p class="text-base-content/80 leading-relaxed">Defender y mejorar las condiciones laborales, humanas y
                  sociales de nuestros agremiados, promoviendo la unidad y el respeto en todo momento.</p>
              </div>

              <!-- VISIÓN -->
              <div class="animate-fade-in-up timeline-view animate-range-entry animate-delay-200">
                <h2 class="text-2xl font-bold text-primary mb-3 uppercase tracking-wide">Visión</h2>
                <p class="text-base-content/80 leading-relaxed">Lograr consolidar a nuestro Sindicato OSTITSM como el
                  mejor en el País, en materia laboral, educativa, social, política y cultural; que este a la vanguardia y
                  sobre todo que permee y coadyuve, en poner en alto a nuestro Instituto Tecnológico Superior de Macuspana
                  como una institución de prestigio social.</p>
              </div>

              <!-- OBJETIVOS -->
              <div class="animate-fade-in-up timeline-view animate-range-entry animate-delay-300">
                <h2 class="text-2xl font-bold text-primary mb-3 uppercase tracking-wide">Objetivos</h2>
                <p class="text-base-content/80 leading-relaxed">A través de las leyes de nuestro Estado y nuestro País y
                  haciendo valer nuestra bandera sindical legal y plenamente establecida: Defenderemos a toda costa los
                  derechos de los trabajadores Sindicalizados, asumiendo en todo momento derechos y obligaciones como
                  entes laborales.</p>
              </div>

              <!-- METAS -->
              <div class="animate-fade-in-up timeline-view animate-range-entry animate-delay-400 space-y-4">
                <h2 class="text-2xl font-bold text-primary uppercase tracking-wide">Metas</h2>
                <h4 class="text-xs font-bold text-secondary uppercase tracking-widest">A corto, mediano y largo plazo</h4>

                <ul class="flex flex-col gap-3 p-0 list-none text-base-content/80">
                  <li class="flex items-start gap-3">
                    <span
                      class="inline-flex items-center justify-center rounded-full bg-success/20 text-success p-1 size-6 shrink-0 mt-0.5">
                      <i class="bi bi-check text-sm font-bold"></i>
                    </span>
                    <span>Recuperar las prestaciones en litigios de defensas de los Agremiados.</span>
                  </li>
                  <li class="flex items-start gap-3">
                    <span
                      class="inline-flex items-center justify-center rounded-full bg-success/20 text-success p-1 size-6 shrink-0 mt-0.5">
                      <i class="bi bi-check text-sm font-bold"></i>
                    </span>
                    <span>Lograr que a ningún Administrativo y Docente sindicalizado se les adeude ningún pago
                      devengado.</span>
                  </li>
                  <li class="flex items-start gap-3">
                    <span
                      class="inline-flex items-center justify-center rounded-full bg-success/20 text-success p-1 size-6 shrink-0 mt-0.5">
                      <i class="bi bi-check text-sm font-bold"></i>
                    </span>
                    <span>Forjar mejores estrategias colectivas para mantener la unidad e incrementarla.</span>
                  </li>
                  <li class="flex items-start gap-3">
                    <span
                      class="inline-flex items-center justify-center rounded-full bg-success/20 text-success p-1 size-6 shrink-0 mt-0.5">
                      <i class="bi bi-check text-sm font-bold"></i>
                    </span>
                    <span>Buscar ser un sólo Sindicato en el ITSM, buscando los canales de hermandad entre los no
                      Agremiados.</span>
                  </li>
                  <li class="flex items-start gap-3">
                    <span
                      class="inline-flex items-center justify-center rounded-full bg-success/20 text-success p-1 size-6 shrink-0 mt-0.5">
                      <i class="bi bi-check text-sm font-bold"></i>
                    </span>
                    <span>Buscar siempre la mejora humana, salarial y de prestaciones de nuestros agremiados.</span>
                  </li>
                  <li class="flex items-start gap-3">
                    <span
                      class="inline-flex items-center justify-center rounded-full bg-success/20 text-success p-1 size-6 shrink-0 mt-0.5">
                      <i class="bi bi-check text-sm font-bold"></i>
                    </span>
                    <span>Evitar a toda costa los disturbios y las divisiones entre el gremio, que sólo conllevan a
                      divisionismos lesivos de la Familia OST.</span>
                  </li>
                  <li class="flex items-start gap-3">
                    <span
                      class="inline-flex items-center justify-center rounded-full bg-success/20 text-success p-1 size-6 shrink-0 mt-0.5">
                      <i class="bi bi-check text-sm font-bold"></i>
                    </span>
                    <span>Realizar revisiones de salarios y todo el Contrato Colectivo cuando por Ley corresponda.</span>
                  </li>
                  <li class="flex items-start gap-3">
                    <span
                      class="inline-flex items-center justify-center rounded-full bg-success/20 text-success p-1 size-6 shrink-0 mt-0.5">
                      <i class="bi bi-check text-sm font-bold"></i>
                    </span>
                    <span>Ponerse en los zapatos de los agremiados, cuando violen sus derechos y estos soliciten el apoyo
                      sindical.</span>
                  </li>
                  <li class="flex items-start gap-3">
                    <span
                      class="inline-flex items-center justify-center rounded-full bg-success/20 text-success p-1 size-6 shrink-0 mt-0.5">
                      <i class="bi bi-check text-sm font-bold"></i>
                    </span>
                    <span>Emprender acciones Jurídicas cuando el derecho laboral, sea violado o no reconocido algún
                      Agremiado.</span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <!-- Columna Lateral (Foto del Secretario y Compromiso) -->
        <div class="lg:col-span-5 lg:order-1 space-y-6">
          <div class="sticky top-24 space-y-6">
            <div
              class="card bg-base-200 shadow-md p-4 overflow-hidden border border-base-content/10 animate-fade-in-up timeline-view animate-range-entry">
              <img
                class="w-full h-auto rounded-xl object-cover shadow-sm hover:scale-[1.02] transition-transform duration-300"
                src="/assets/images/about_2-min.jpg" alt="Imagen Sindicato OSTITSM">

              <div class="mt-4 text-center">
                <div class="font-bold text-lg text-base-content">MIDS. Luiz Sosa Castro</div>
                <div class="text-sm text-base-content/70 font-medium">Secretario General SIUT ITSM</div>
              </div>
            </div>

            <!-- Tarjeta de Compromiso con daisyUI -->
            <div
              class="card bg-primary text-primary-content shadow-xl p-6 flex flex-row gap-4 items-start animate-fade-in-up timeline-view animate-range-entry animate-delay-100">
              <div
                class="bg-primary-content/25 text-primary-content rounded-full p-3 inline-flex items-center justify-center shrink-0">
                <i class="bi bi-lightbulb text-xl"></i>
              </div>
              <div class="space-y-2">
                <h3 class="card-title text-lg uppercase font-bold tracking-wider text-primary-content">Compromiso</h3>
                <p class="text-sm opacity-90 leading-relaxed">
                  Nuestro compromiso es defender y mejorar las condiciones laborales, humanas y sociales de nuestros
                  agremiados, promoviendo la unidad y el respeto en todo momento.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

@endsection