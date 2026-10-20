<div style="text-align: center; margin-bottom: 18px;">
    <h2 style="font-size: 1.8em; color: {{ $documentColors['primary'] }};">Corrida Financiera</h2>
    <p style="color: {{ $documentColors['base_content'] }}; font-size: 1em; margin-top: 4px;">Resumen del préstamo simulado</p>
</div>

<hr style="border: 0; border-top: 2px solid {{ $documentColors['primary'] }}; margin-bottom: 20px;">

<table style="width: 100%; border-collapse: collapse; font-size: 1.05em; margin-bottom: 24px;">
    <tbody>
        <tr style="border-bottom: 1px solid {{ $documentColors['base_300'] }};">
            <td style="padding: 8px 10px; width: 45%; color: {{ $documentColors['base_content'] }}; font-weight: 600; text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.05em;">Monto simulado</td>
            <td style="padding: 8px 10px; font-size: 1.2em; font-weight: bold; color: {{ $documentColors['primary'] }};">$ {{ number_format($summary['amount'], 2) }}</td>
        </tr>
        <tr style="border-bottom: 1px solid {{ $documentColors['base_300'] }};">
            <td style="padding: 8px 10px; color: {{ $documentColors['base_content'] }}; font-weight: 600; text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.05em;">Tasa de interés mensual</td>
            <td style="padding: 8px 10px; font-weight: bold;">{{ $summary['monthly_rate_percent'] }}</td>
        </tr>
        <tr style="border-bottom: 1px solid {{ $documentColors['base_300'] }};">
            <td style="padding: 8px 10px; color: {{ $documentColors['base_content'] }}; font-weight: 600; text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.05em;">Fecha de desembolso</td>
            <td style="padding: 8px 10px;">{{ \Illuminate\Support\Carbon::parse($summary['disbursement_date'])->format('d/m/Y') }}</td>
        </tr>
        @if ($summary['fortnights'])
        <tr style="border-bottom: 1px solid {{ $documentColors['base_300'] }};">
            <td style="padding: 8px 10px; color: {{ $documentColors['base_content'] }}; font-weight: 600; text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.05em;">Plazo (quincenas)</td>
            <td style="padding: 8px 10px;">{{ $summary['fortnights'] }} quincenas</td>
        </tr>
        @endif
        @if ($summary['first_fortnight'])
        <tr style="border-bottom: 1px solid {{ $documentColors['base_300'] }};">
            <td style="padding: 8px 10px; color: {{ $documentColors['base_content'] }}; font-weight: 600; text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.05em;">Primera quincena</td>
            <td style="padding: 8px 10px;">{{ \Illuminate\Support\Carbon::parse($summary['first_fortnight'])->format('d/m/Y') }}</td>
        </tr>
        @endif
        @if ($summary['last_fortnight'])
        <tr style="border-bottom: 1px solid {{ $documentColors['base_300'] }};">
            <td style="padding: 8px 10px; color: {{ $documentColors['base_content'] }}; font-weight: 600; text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.05em;">Última quincena</td>
            <td style="padding: 8px 10px;">{{ \Illuminate\Support\Carbon::parse($summary['last_fortnight'])->format('d/m/Y') }}</td>
        </tr>
        @endif
    </tbody>
</table>

<table style="width: 100%; border-collapse: collapse; font-size: 1.05em; margin-bottom: 24px;">
    <thead>
        <tr style="background: {{ $documentColors['primary'] }}; color: {{ $documentColors['primary_content'] }};">
            <th style="padding: 8px 10px; text-align: left; font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.05em;">Concepto</th>
            <th style="padding: 8px 10px; text-align: right; font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.05em;">Importe</th>
        </tr>
    </thead>
    <tbody>
        <tr style="border-bottom: 1px solid {{ $documentColors['base_300'] }};">
            <td style="padding: 8px 10px;">Capital (monto principal)</td>
            <td style="padding: 8px 10px; text-align: right;">$ {{ number_format($summary['total_principal'], 2) }}</td>
        </tr>
        <tr style="border-bottom: 1px solid {{ $documentColors['base_300'] }};">
            <td style="padding: 8px 10px;">Total de intereses</td>
            <td style="padding: 8px 10px; text-align: right;">$ {{ number_format($summary['total_interest'], 2) }}</td>
        </tr>
        <tr style="border-bottom: 2px solid {{ $documentColors['primary'] }}; font-weight: bold; background: {{ $documentColors['base_100'] }};">
            <td style="padding: 10px 10px; font-size: 1.1em;">Total a pagar</td>
            <td style="padding: 10px 10px; text-align: right; font-size: 1.1em; color: {{ $documentColors['primary'] }};">$ {{ number_format($summary['total_payment'], 2) }}</td>
        </tr>
    </tbody>
</table>
