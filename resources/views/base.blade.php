<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="siut">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'Laravel'))</title>
    @hasSection('description')
        <meta name="description" content="@yield('description')">
    @endif

    @fonts

    <!-- Styles / Scripts -->
    <x-theme-style />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')) || app()->environment('testing'))
        @vite(['resources/css/app.css'])
        @yield('base-head')
    @endif


</head>

<body class="bg-base-100 text-base-content">
    @yield('base-content')
</body>

</html>
