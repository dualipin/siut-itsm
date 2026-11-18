@extends('document.layout')

@section('title', 'Simulación de Préstamo')

@section('content')
@php
    $documentColors ??= [
        'primary' => $primaryColor ?? '#611232',
        'primary_content' => '#ffffff',
        'warning' => '#f59e0b',
        'warning_content' => '#92400e',
    ];
@endphp
<div style="margin-top: 10px;">

    @include('pdf.partials.loan-simulation-disclaimer')
    @include('pdf.partials.loan-financial-summary')

    {{-- ===== PAGE BREAK ===== --}}
    <div style="page-break-before: always;"></div>

    @include('pdf.partials.loan-amortization-table')
</div>
@endsection
