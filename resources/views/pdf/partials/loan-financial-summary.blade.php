@php
    $isOfficial = $isOfficial ?? false;
@endphp
@if (! $isOfficial)
<div style="text-align: center; margin-bottom: 14px;">
    <h2 style="font-size: 16px; font-weight: bold; color: {{ $documentColors['primary'] }}; margin: 0;">Corrida Financiera</h2>
</div>
@endif

<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
    <tbody>
        <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 9px 12px; width: 55%; color: #4b5563; font-weight: bold; text-transform: uppercase; font-size: 9px; letter-spacing: 0.04em;">{{ $isOfficial ? 'Monto del préstamo' : 'Monto simulado' }}</td>
            <td style="padding: 9px 12px; text-align: right; font-size: 13px; font-weight: bold; color: {{ $documentColors['primary'] }}; white-space: nowrap;">$ {{ number_format($summary['amount'], 2) }}</td>
        </tr>
        <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 8px 12px; color: #4b5563; font-weight: bold; text-transform: uppercase; font-size: 9px; letter-spacing: 0.04em;">Tasa de interés mensual</td>
            <td style="padding: 8px 12px; text-align: right; font-size: 10.5px; font-weight: bold; color: #111827; white-space: nowrap;">{{ $summary['monthly_rate_percent'] }}</td>
        </tr>
        <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 8px 12px; color: #4b5563; font-weight: bold; text-transform: uppercase; font-size: 9px; letter-spacing: 0.04em;">Año fiscal</td>
            <td style="padding: 8px 12px; text-align: right; font-size: 10.5px; font-weight: bold; color: #111827; white-space: nowrap;">{{ $summary['fiscal_year'] ?? \Illuminate\Support\Carbon::parse($summary['disbursement_date'])->year }}</td>
        </tr>
        <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 8px 12px; color: #4b5563; font-weight: bold; text-transform: uppercase; font-size: 9px; letter-spacing: 0.04em;">Fecha de desembolso</td>
            <td style="padding: 8px 12px; text-align: right; font-size: 10.5px; color: #111827; white-space: nowrap;">{{ \Illuminate\Support\Carbon::parse($summary['disbursement_date'])->format('d/m/Y') }}</td>
        </tr>
        @if ($summary['fortnights'])
        <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 8px 12px; color: #4b5563; font-weight: bold; text-transform: uppercase; font-size: 9px; letter-spacing: 0.04em;">Plazo</td>
            <td style="padding: 8px 12px; text-align: right; font-size: 10.5px; color: #111827; white-space: nowrap;">{{ $summary['fortnights'] }} {{ (int) $summary['fortnights'] === 1 ? 'quincena' : 'quincenas' }}</td>
        </tr>
        @endif
        @if ($summary['first_fortnight'])
        <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 8px 12px; color: #4b5563; font-weight: bold; text-transform: uppercase; font-size: 9px; letter-spacing: 0.04em;">Primera quincena</td>
            <td style="padding: 8px 12px; text-align: right; font-size: 10.5px; color: #111827; white-space: nowrap;">{{ \Illuminate\Support\Carbon::parse($summary['first_fortnight'])->format('d/m/Y') }}</td>
        </tr>
        @endif
        @if ($summary['last_fortnight'])
        <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 8px 12px; color: #4b5563; font-weight: bold; text-transform: uppercase; font-size: 9px; letter-spacing: 0.04em;">Última quincena</td>
            <td style="padding: 8px 12px; text-align: right; font-size: 10.5px; color: #111827; white-space: nowrap;">{{ \Illuminate\Support\Carbon::parse($summary['last_fortnight'])->format('d/m/Y') }}</td>
        </tr>
        @endif
    </tbody>
</table>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
    <thead>
        <tr style="background: {{ $documentColors['primary'] }}; color: {{ $documentColors['primary_content'] }};">
            <th style="padding: 8px 12px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em;">Concepto</th>
            <th style="padding: 8px 12px; text-align: right; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;">Importe</th>
        </tr>
    </thead>
    <tbody>
        <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 8px 12px; font-size: 10.5px; color: #111827;">Capital (monto principal)</td>
            <td style="padding: 8px 12px; text-align: right; font-size: 10.5px; color: #111827; white-space: nowrap;">$ {{ number_format($summary['total_principal'], 2) }}</td>
        </tr>
        <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 8px 12px; font-size: 10.5px; color: #111827;">Total de intereses</td>
            <td style="padding: 8px 12px; text-align: right; font-size: 10.5px; color: #111827; white-space: nowrap;">$ {{ number_format($summary['total_interest'], 2) }}</td>
        </tr>
        <tr style="border-top: 1.5px solid {{ $documentColors['primary'] }}; border-bottom: 2px solid {{ $documentColors['primary'] }}; font-weight: bold; background: #fdf6e7;">
            <td style="padding: 10px 12px; font-size: 12px; color: #111827;">Total a pagar</td>
            <td style="padding: 10px 12px; text-align: right; font-size: 12px; color: {{ $documentColors['primary'] }}; white-space: nowrap;">$ {{ number_format($summary['total_payment'], 2) }}</td>
        </tr>
    </tbody>
</table>
