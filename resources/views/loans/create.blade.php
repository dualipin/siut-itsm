@extends('base')

@section('title', 'Solicitud de Préstamo - SIUT ITSM')

@section('base-head')
    @vite(['resources/js/island.ts'])
@endsection

@section('base-content')
<div class="min-h-screen bg-base-200/50 py-6 px-3 sm:px-6 lg:px-8">
    <div class="max-w-5xl mx-auto">
        <!-- Top bar with Back to Portal -->
        <div class="flex items-center justify-between mb-6">
            <a href="{{ route('filament.portal.pages.dashboard') }}" class="btn btn-ghost btn-sm gap-2 text-base-content/80 hover:text-base-content">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Volver al Portal
            </a>
            <div class="flex items-center gap-2">
                <span class="text-xs text-base-content/60">Sistema de Préstamos Sindicales</span>
                <span class="badge badge-primary badge-outline badge-sm">Fase 1</span>
            </div>
        </div>

        <noscript>
            <div class="alert alert-warning shadow-lg mb-6">
                <div>
                    <h3 class="font-bold">JavaScript Requerido</h3>
                    <div class="text-xs">Para solicitar un préstamo y validar tus comprobantes con verificación digital se requiere habilitar JavaScript.</div>
                </div>
            </div>
        </noscript>

        <!-- Vue Island -->
        <div
            data-vue="loans/application-form"
            data-client="load"
            data-props="{{ json_encode([
                'user' => $user,
                'isWindowOpen' => $isWindowOpen,
                'activeLoan' => $activeLoan,
                'fortnights' => $fortnights,
                'benefits' => $benefits,
                'storeUrl' => route('loans.store'),
                'uploadUrl' => route('loans.evidences.upload'),
                'submitUrl' => route('loans.submit', ['loan' => '__LOAN_ID__']),
                'signUrl' => route('loans.sign', ['loan' => '__LOAN_ID__']),
                'portalLoansUrl' => route('filament.portal.resources.loans.index'),
            ]) }}"
        ></div>
    </div>
</div>
@endsection
