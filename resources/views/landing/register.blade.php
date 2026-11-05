@extends('landing.layout')

@section('title', 'Registro de Agremiados - SIUT ITSM')
@section('description', 'Solicita tu alta oficial en el padrón sindical del Sindicato Independiente de Trabajadores del ITSM.')

@section('content')
    <div class="py-6 sm:py-8 md:py-12 bg-base-200/50 min-h-[calc(100vh-16rem)] w-full max-w-full overflow-x-clip">
        <noscript>
            <div class="max-w-4xl mx-auto px-4 mb-8">
                <div class="alert alert-warning shadow-lg" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <h3 class="font-bold">JavaScript requerido</h3>
                        <div class="text-xs">Para completar el proceso de registro interactivo con verificación digital de documentos es necesario habilitar JavaScript en tu navegador.</div>
                    </div>
                </div>
            </div>
        </noscript>

        <div
            data-vue="registro/register-form"
            data-client="load"
            data-props="{{ json_encode([
                'documentsUrl' => route('registro.documentos.store'),
                'submitUrl' => route('registro.store'),
                'loginUrl' => route('filament.portal.auth.login'),
                'privacyUrl' => url('/transparencia/normativos'),
                'maxDocSize' => 10,
                'maxPhotoSize' => 2,
            ]) }}"
        ></div>
    </div>
@endsection
