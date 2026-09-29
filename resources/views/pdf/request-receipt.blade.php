@extends('document.layout')

@section('title', 'Comprobante de Solicitud - ' . $userRequest->folio)

@section('content')
<div style="margin-top: 30px; text-align: center;">
    <h2 style="font-size: 1.8em; margin-bottom: 10px; color: {{ $primaryColor }};">Comprobante de Solicitud</h2>
    <p style="font-size: 1.2em; color: #555;">Folio: <strong>{{ $userRequest->folio }}</strong></p>
</div>

<hr style="border: 0; border-top: 2px solid {{ $primaryColor }}; margin: 20px 0;">

<div style="margin-bottom: 20px;">
    <h3 style="font-size: 1.4em; color: {{ $primaryColor }}; margin-bottom: 10px;">Datos del Agremiado</h3>
    <table style="width: 100%; border-collapse: collapse; font-size: 1.1em;">
        <tr>
            <td style="padding: 5px 0; width: 30%;"><strong>Nombre:</strong></td>
            <td style="padding: 5px 0;">{{ $userRequest->user->name }} {{ $userRequest->user->surnames }}</td>
        </tr>
        <tr>
            <td style="padding: 5px 0;"><strong>CURP:</strong></td>
            <td style="padding: 5px 0;">{{ $userRequest->user->curp ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td style="padding: 5px 0;"><strong>Correo Electrónico:</strong></td>
            <td style="padding: 5px 0;">{{ $userRequest->user->email }}</td>
        </tr>
    </table>
</div>

<div style="margin-bottom: 20px;">
    <h3 style="font-size: 1.4em; color: {{ $primaryColor }}; margin-bottom: 10px;">Detalles de la Solicitud</h3>
    <table style="width: 100%; border-collapse: collapse; font-size: 1.1em;">
        <tr>
            <td style="padding: 5px 0; width: 30%;"><strong>Tipo de Solicitud:</strong></td>
            <td style="padding: 5px 0;">{{ $userRequest->requestType->name }}</td>
        </tr>
        <tr>
            <td style="padding: 5px 0;"><strong>Estado:</strong></td>
            <td style="padding: 5px 0;">{{ $userRequest->status->getLabel() }}</td>
        </tr>
        <tr>
            <td style="padding: 5px 0;"><strong>Fecha de Solicitud:</strong></td>
            <td style="padding: 5px 0;">{{ $userRequest->created_at->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td style="padding: 5px 0; vertical-align: top;"><strong>Motivo / Descripción:</strong></td>
            <td style="padding: 5px 0; text-align: justify;">{{ $userRequest->reason }}</td>
        </tr>
    </table>
</div>

@if(!empty($userRequest->additional_data))
<div style="margin-bottom: 20px;">
    <h3 style="font-size: 1.4em; color: {{ $primaryColor }}; margin-bottom: 10px;">Información Adicional</h3>
    <table style="width: 100%; border-collapse: collapse; font-size: 1.1em;">
        @foreach($userRequest->additional_data as $key => $value)
        @php
            $label = ucfirst(str_replace('_', ' ', $key));
            if (!empty($userRequest->requestType->custom_fields)) {
                $field = collect($userRequest->requestType->custom_fields)->firstWhere('name', $key);
                if ($field) {
                    $label = $field['label'];
                }
            }
        @endphp
        <tr>
            <td style="padding: 5px 0; width: 30%;"><strong>{{ $label }}:</strong></td>
            <td style="padding: 5px 0;">
                @if(is_array($value))
                    {{ implode(', ', $value) }}
                @else
                    {{ $value }}
                @endif
            </td>
        </tr>
        @endforeach
    </table>
</div>
@endif

<div style="margin-top: 50px; text-align: center;">
    <p style="font-size: 1.1em; color: #555;">Este documento es un comprobante oficial de su solicitud ante el Sindicato.</p>
</div>
@endsection
