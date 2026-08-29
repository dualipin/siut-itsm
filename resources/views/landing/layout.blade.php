@extends('base')

@section('base-content')
    <div class="drawer drawer-end">
        <input id="landing-drawer" type="checkbox" class="drawer-toggle" />
        <div class="min-h-dvh flex flex-col">
            <x-landing-navbar />
            <main>
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