@extends('base')

@section('base-content')
<div class="min-h-dvh flex items-center justify-center bg-base-100 px-4">
    <div class="text-center max-w-md">
        <div class="mb-8 animate-bounce">
            <span class="text-9xl font-bold text-base-content/20">404</span>
        </div>

        <h1 class="text-3xl md:text-4xl font-bold font-display mb-4 text-base-content">
            Página no encontrada
        </h1>

        <p class="text-base-content/70 mb-8 text-lg">
            Lo sentimos, la página que buscas no existe o ha sido movida.
        </p>

        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a
                href="{{ route('home') }}"
                class="btn btn-primary"
            >
                Ir al inicio
            </a>
            <a
                href="{{ route('filament.portal.auth.login') }}"
                class="btn btn-outline btn-primary"
            >
                Acceder al Portal
            </a>
        </div>

        <div class="mt-10 pt-8 border-t border-base-300">
            <p class="text-sm text-base-content/50">
                ¿Crees que esto es un error?
                <a href="{{ route('contact') }}" class="link link-primary ml-1">
                    Contáctanos
                </a>
            </p>
        </div>
    </div>
</div>
@endsection