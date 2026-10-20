@extends('document.layout')

@section('title', 'Simulación de Préstamo')

@section('content')
@php
    $documentColors ??= [
        'primary' => $primaryColor ?? '#611232',
        'primary_content' => '#ffffff',
        'base_100' => '#f9fafb',
        'base_200' => '#f2f2f2',
        'base_300' => '#dddddd',
        'base_content' => '#000000',
        'warning' => '#f59e0b',
        'warning_content' => '#92400e',
        'info' => '#eff6ff',
    ];
@endphp
<div style="margin-top: 30px;">

    @include('pdf.partials.loan-simulation-disclaimer')
    @include('pdf.partials.loan-financial-summary')

    {{-- ===== PAGE BREAK ===== --}}
    <div style="page-break-before: always; padding-top: 10px;"></div>

    @include('pdf.partials.loan-amortization-table')
</div>
@endsection
