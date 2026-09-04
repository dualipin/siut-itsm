@extends('landing.layout')
@section('content')
  <section class="py-12 md:py-24">
    <div class="container mx-auto px-4 max-w-7xl pt-10">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
        <div>
          <div class="w-11/12 mx-auto lg:mx-0">
            <span class="block uppercase text-base-content/60 mb-4  animate-fade-in-up">
              {{ config('syndicate.name') }}
            </span>


            <h1 class="text-5xl md:text-6xl font-bold font-display mb-6 animate-fade-in-up animate-delay-100">
              {{ config('syndicate.acronym') }}
            </h1>


            <div data-vue="hero/slogan"></div>


            <div class="flex flex-wrap gap-3 mb-8 lg:mb-12 animate-fade-in-up animate-delay-300">
              <a class="btn btn-primary" href="{{ route('filament.portal.auth.login') }}">
                Acceder al Portal
              </a>
              <a class="btn btn-outline btn-primary" href="{{ route('transparency.normatives') }}">
                Transparencia
              </a>
              <a class="btn btn-outline btn-primary"
                href="{{ route('financial-reports.index') }}">
                Informes Financieros
              </a>
              <a class="btn btn-outline" href="">
                Simulador de prestamo
              </a>
            </div>

            <div class="animate-fade-in-up animate-delay-400">
              <span class="uppercase text-base-content/60 mb-3 block font-semibold text-sm">
                En unidad permanente, TODOS SOMOS LA FUERZA
              </span>
            </div>
          </div>
        </div>

        <div class="animate-fade-in animate-delay-500 rounded-box overflow-hidden flex">
          <x-landing-home-carousel />
        </div>
      </div>
    </div>
  </section>

  <section class="py-12 mt-12 border-t border-base-300 timeline-view animate-zoom-in animate-range-entry">
    <div class="container mx-auto px-4 max-w-7xl text-center">
      <div class="flex flex-wrap items-center justify-center gap-12">
        <div class="w-1/2 md:w-1/3 lg:w-1/4">
          <img class="max-w-50 w-full mx-auto  rounded-full opacity-80  hover:opacity-100 transition-all duration-300"
            src={{ asset('assets/img/logo.webp') }} alt="OST-SIUT-ITSM Logo" />
        </div>
        <div class="w-1/2 md:w-1/3 lg:w-1/4">
          <img src={{ asset('assets/img/logo/logo-snes.png') }}
            class="max-w-50 w-full mx-auto  opacity-80  hover:opacity-100 transition-all duration-300" alt="Cliente 2" />
        </div>
      </div>
    </div>
  </section>





  <div data-vue="contact" class="animate-fade-in-up animate-delay-600 border-t border-base-300"
    data-props="{{ json_encode(['syndicate' => config('syndicate')]) }}"></div>
@endsection