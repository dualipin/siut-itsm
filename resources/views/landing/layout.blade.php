@extends('base')

@section('base-head')
    @vite(['resources/js/island.ts'])
@endsection

@section('base-content')
    <div class="drawer drawer-end">
        <input id="landing-drawer" type="checkbox" class="drawer-toggle" />
        <div class="drawer-content min-h-dvh flex flex-col min-w-0">
            <x-landing-navbar />
            <main class="min-w-0 flex-1">
                @yield('content')
            </main>
            <x-landing-floating-elements />
            <x-landing-footer />
        </div>
        <div class="drawer-side z-50 lg:hidden">
            <label for="landing-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
            <x-landing-mobile-menu />
        </div>
    </div>
@endsection
