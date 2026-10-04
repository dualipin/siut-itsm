@extends('document.layout')

@section('title', 'Acuse de Peticiones - ' . $annualPetition->year)

@section('content')
<div style="margin-top: 30px; text-align: center;">
    <h2 style="font-size: 1.8em; margin-bottom: 10px; color: {{ $primaryColor }};">Acuse de Recepci&oacute;n de Peticiones</h2>
    <p style="font-size: 1.2em; color: #555;">Pliego Anual {{ $annualPetition->year }}</p>
</div>

<hr style="border: 0; border-top: 2px solid {{ $primaryColor }}; margin: 20px 0;">

<div style="margin-bottom: 20px;">
    <h3 style="font-size: 1.4em; color: {{ $primaryColor }}; margin-bottom: 10px;">Datos de la Convocatoria</h3>
    <table style="width: 100%; border-collapse: collapse; font-size: 1.1em;">
        <tr>
            <td style="padding: 5px 0; width: 30%;"><strong>A&ntilde;o:</strong></td>
            <td style="padding: 5px 0;">{{ $annualPetition->year }}</td>
        </tr>
        <tr>
            <td style="padding: 5px 0;"><strong>Fecha L&iacute;mite:</strong></td>
            <td style="padding: 5px 0;">{{ $annualPetition->deadline?->format('d/m/Y') }}</td>
        </tr>
        @if ($petitionRequests->first()?->agremiado_name)
    <tr>
        <td style="padding: 5px 0;"><strong>Nombre del Agremiado:</strong></td>
        <td style="padding: 5px 0;">{{ $petitionRequests->first()->agremiado_name }}</td>
    </tr>
@endif
        <tr>
            <td style="padding: 5px 0;"><strong>CURP del Agremiado:</strong></td>
            <td style="padding: 5px 0;">{{ $curp }}</td>
        </tr>
        <tr>
            <td style="padding: 5px 0;"><strong>Fecha de Registro:</strong></td>
            <td style="padding: 5px 0;">{{ now()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>
</div>

<div style="margin-bottom: 20px;">
    <h3 style="font-size: 1.4em; color: {{ $primaryColor }}; margin-bottom: 10px;">Peticiones Registradas ({{ $petitionRequests->count() }})</h3>
    <table style="width: 100%; border-collapse: collapse; font-size: 1.05em;">
        <thead>
            <tr style="border-bottom: 2px solid {{ $primaryColor }}; text-align: left;">
                <th style="padding: 6px 0; width: 8%;">#</th>
                <th style="padding: 6px 0;">Propuesta</th>
                <th style="padding: 6px 0; width: 22%;">Adjunto</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($petitionRequests as $index => $petition)
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 6px 0; vertical-align: top;">{{ $index + 1 }}</td>
                    <td style="padding: 6px 0; vertical-align: top; text-align: justify;">{{ $petition->proposal }}</td>
                    <td style="padding: 6px 0; vertical-align: top;">
                        @if ($petition->hasMedia('proposal_files'))
                            S&iacute;
                        @else
                            No
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div style="margin-top: 50px; text-align: center;">
    <p style="font-size: 1.1em; color: #555;">Este documento es un comprobante oficial del registro de sus peticiones ante el Sindicato.</p>
</div>
@endsection
