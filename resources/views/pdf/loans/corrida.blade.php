@extends('document.layout')

@section('title', 'Corrida Financiera - '.$loan->folio)

@section('content')
@php
    $documentColors ??= [
        'primary' => '#611232',
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
<div style="margin-top: 15px;">
    <div style="text-align: center; margin-bottom: 12px;">
        <h2 style="font-size: 1.6em; color: {{ $documentColors['primary'] }}; margin-bottom: 4px;">Corrida Financiera Oficial</h2>
        <p style="color: {{ $documentColors['base_content'] }}; font-size: 0.95em;">
            Préstamo Folio: <strong>{{ $loan->folio }}</strong> | Titular: <strong>{{ $user->full_name }}</strong>
        </p>
    </div>

    @include('pdf.partials.loan-financial-summary')

    {{-- ===== PAGE BREAK ===== --}}
    <div style="page-break-before: always; padding-top: 10px;"></div>

    @include('pdf.partials.loan-amortization-table')

    <div style="margin-top: 30px; text-align: center;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 50%; text-align: center; vertical-align: bottom;">
                    @if($loan->signature_data)
                        <img src="{{ $loan->signature_data }}" style="max-height: 40px; max-width: 130px; margin: 0 auto; display: block;" alt="Firma">
                    @endif
                    <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 4px; font-weight: bold; font-size: 8pt;">{{ $user->full_name }}</div>
                    <div style="font-size: 7pt; color: #555;">Firma del Solicitante</div>
                </td>
                <td style="width: 50%; text-align: center; vertical-align: bottom;">
                    <div style="border-top: 1px solid #000; width: 80%; margin: 35px auto 0 auto; padding-top: 4px; font-weight: bold; font-size: 8pt;">Secretaría de Finanzas SIUTITSM</div>
                    <div style="font-size: 7pt; color: #555;">Autorización y Validación</div>
                </td>
            </tr>
        </table>
    </div>
</div>
@endsection
