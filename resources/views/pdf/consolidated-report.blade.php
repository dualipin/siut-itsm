@extends('document.layout')

@section('title', 'Reporte Consolidado de Peticiones - ' . $annualPetition->year)

@section('content')
<div style="margin-top: 30px; text-align: center;">
    <h2 style="font-size: 1.8em; margin-bottom: 10px; color: {{ $primaryColor }};">Reporte Consolidado de Peticiones</h2>
    <p style="font-size: 1.2em; color: #555;">Pliego Anual {{ $annualPetition->year }}</p>
</div>

<hr style="border: 0; border-top: 2px solid {{ $primaryColor }}; margin: 20px 0;">

<div style="margin-bottom: 20px;">
    <table style="width: 100%; border-collapse: collapse; font-size: 1.1em;">
        <tr>
            <td style="padding: 5px 0; width: 30%;"><strong>A&ntilde;o:</strong></td>
            <td style="padding: 5px 0;">{{ $annualPetition->year }}</td>
        </tr>
        <tr>
            <td style="padding: 5px 0;"><strong>Fecha L&iacute;mite:</strong></td>
            <td style="padding: 5px 0;">{{ $annualPetition->deadline?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td style="padding: 5px 0;"><strong>Total de Peticiones:</strong></td>
            <td style="padding: 5px 0;">{{ $petitionRequests->count() }}</td>
        </tr>
        <tr>
            <td style="padding: 5px 0;"><strong>Fecha de Emisi&oacute;n:</strong></td>
            <td style="padding: 5px 0;">{{ now()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>
</div>

<table style="width: 100%; border-collapse: collapse; font-size: 0.95em;">
    <thead>
        <tr style="background-color: #f2f2f2; border-bottom: 2px solid {{ $primaryColor }};">
            <th style="padding: 6px 4px; width: 5%; text-align: center;">#</th>
            <th style="padding: 6px 4px; width: 16%; text-align: left;">CURP</th>
            <th style="padding: 6px 4px; width: 16%; text-align: left;">Nombre</th>
            <th style="padding: 6px 4px; text-align: left;">Propuesta</th>
            <th style="padding: 6px 4px; width: 10%; text-align: center;">Adjunto</th>
            <th style="padding: 6px 4px; width: 14%; text-align: left;">Fecha de Registro</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($petitionRequests as $index => $petitionRequest)
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 5px 4px; text-align: center; vertical-align: top;">{{ $index + 1 }}</td>
                <td style="padding: 5px 4px; vertical-align: top;">{{ $petitionRequest->curp }}</td>
                <td style="padding: 5px 4px; vertical-align: top;">{{ $petitionRequest->agremiado_name ?? '—' }}</td>
                <td style="padding: 5px 4px; vertical-align: top; text-align: justify;">{{ $petitionRequest->proposal }}</td>
                <td style="padding: 5px 4px; text-align: center; vertical-align: top;">
                    {{ $petitionRequest->hasMedia('proposal_files') ? 'S' : 'N' }}
                </td>
                <td style="padding: 5px 4px; vertical-align: top;">{{ $petitionRequest->created_at->format('d/m/Y H:i') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div style="margin-top: 40px; text-align: center;">
    <p style="font-size: 1.05em; color: #555;">Documento generado por el Sistema de Pliegos Anuales.</p>
</div>
@endsection
