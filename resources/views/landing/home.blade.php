@extends('landing.layout')

@section('content')
  <section class="py-12 md:py-20 animate-fade-in animate-duration-normal">
    <div class="container mx-auto px-4 max-w-7xl pt-10">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
        <div>
          <div class="w-11/12 mx-auto lg:mx-0">
            <span class="block uppercase text-base-content/75 font-semibold text-xs sm:text-sm tracking-wider mb-3 animate-fade-in-down animate-duration-fast">
              {{ config('syndicate.name') }}
            </span>

            <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold font-display tracking-tight text-balance mb-4 text-base-content animate-fade-in-up animate-duration-normal">
              {{ config('syndicate.acronym') }}
            </h1>

            <div data-vue="hero/slogan" class="min-h-[4rem] text-pretty"></div>

            <div class="flex flex-wrap items-center gap-3 mb-6 lg:mb-8 animate-fade-in-up animate-delay-200 animate-duration-normal">
              <a class="btn btn-primary" href="{{ route('filament.portal.auth.login') }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/></svg>
                Acceder al Portal
              </a>
              <a class="btn btn-outline" href="{{ route('transparency.normatives') }}">
                Transparencia Sindical
              </a>
              <a class="btn btn-ghost hover:bg-base-200" href="{{ route('financial-reports.index') }}">
                Informes Financieros
              </a>
              <a class="btn btn-ghost hover:bg-base-200" href="{{ route('simulator.show') }}">
                Simulador de préstamos
              </a>
            </div>

            <div class="animate-fade-in animate-delay-300">
              <p class="uppercase text-base-content/70 font-semibold text-xs tracking-wide">
                En unidad permanente, TODOS SOMOS LA FUERZA
              </p>
            </div>
          </div>
        </div>

        <div class="rounded-box overflow-hidden flex shadow-md border border-base-200 animate-fade-in-right animate-duration-normal">
          <x-landing-home-carousel />
        </div>
      </div>
    </div>
  </section>

  <x-landing-home-birthday />

  <section class="py-12 border-t border-base-300 timeline-view animate-fade-in animate-range-entry">
    <div class="container mx-auto px-4 max-w-7xl text-center">
      <h2 class="text-xs uppercase tracking-widest text-base-content/60 font-semibold text-balance mb-6">
        Afiliaciones y Representación Sindical
      </h2>
      <div class="flex flex-wrap items-center justify-center gap-12">
        <div class="w-1/2 md:w-1/3 lg:w-1/4">
          <img class="max-w-44 w-full mx-auto rounded-full opacity-85 hover:opacity-100 transition-opacity duration-300"
            src="{{ asset('assets/img/logo.webp') }}"
            width="176"
            height="176"
            alt="OST-SIUT-ITSM Logotipo Oficial" />
        </div>
        <div class="w-1/2 md:w-1/3 lg:w-1/4">
          <img class="max-w-44 w-full mx-auto opacity-85 hover:opacity-100 transition-opacity duration-300"
            src="{{ asset('assets/img/logo/logo-snes.png') }}"
            width="176"
            height="176"
            alt="Sindicato Nacional de Educación Superior (SNES)" />
        </div>
      </div>
    </div>
  </section>

  <div data-vue="contact" class="border-t border-base-300 timeline-view animate-fade-in animate-range-entry"
    data-props="{{ json_encode(['syndicate' => config('syndicate')]) }}"></div>
@endsection