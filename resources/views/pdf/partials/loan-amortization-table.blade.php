<div style="text-align: center; margin-bottom: 12px; margin-top: 6px;">
    <h2 style="font-size: 16px; font-weight: bold; color: {{ $documentColors['primary'] }}; margin: 0;">Tabla de Amortización</h2>
</div>

<table style="width: 100%; border-collapse: collapse; font-size: 9.5px;">
    <thead style="display: table-header-group;">
        <tr style="background: {{ $documentColors['primary'] }}; color: {{ $documentColors['primary_content'] }};">
            <th style="padding: 6px 5px; text-align: center; width: 24px; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.03em;">#</th>
            <th style="padding: 6px 6px; text-align: left; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.03em;">Descripción</th>
            <th style="padding: 6px 5px; text-align: center; width: 62px; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.03em; white-space: nowrap;">Fecha</th>
            <th style="padding: 6px 6px; text-align: right; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.03em; white-space: nowrap;">Saldo inicial</th>
            <th style="padding: 6px 6px; text-align: right; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.03em; white-space: nowrap;">Capital</th>
            <th style="padding: 6px 6px; text-align: right; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.03em; white-space: nowrap;">Interés</th>
            <th style="padding: 6px 6px; text-align: right; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.03em; white-space: nowrap;">Pago</th>
            <th style="padding: 6px 6px; text-align: right; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.03em; white-space: nowrap;">Saldo final</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($amortizationTable as $row)
            @php
                $rowStyle = match($row['type']) {
                    'extra_days'     => 'background: #f3f4f6; color: #374151;',
                    'benefit_growth' => 'background: #faf8f5; color: #6b7280; font-style: italic;',
                    'benefit'        => 'background: #fdf6e7; color: #111827; font-weight: bold;',
                    default          => ($loop->even ? 'background: #f9fafb; color: #111827;' : 'background: #ffffff; color: #111827;'),
                };
            @endphp
            <tr style="border-bottom: 1px solid #e5e7eb; page-break-inside: avoid; {{ $rowStyle }}">
                <td style="padding: 5px 5px; text-align: center;">{{ $row['period'] }}</td>
                <td style="padding: 5px 6px;">{{ $row['label'] }}</td>
                <td style="padding: 5px 5px; text-align: center; white-space: nowrap;">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                <td style="padding: 5px 6px; text-align: right; white-space: nowrap;">$ {{ number_format($row['opening_balance'], 2) }}</td>
                <td style="padding: 5px 6px; text-align: right; white-space: nowrap;">{{ $row['type'] === 'benefit_growth' ? '—' : '$ '.number_format($row['principal'], 2) }}</td>
                <td style="padding: 5px 6px; text-align: right; white-space: nowrap;">$ {{ number_format($row['interest'], 2) }}</td>
                <td style="padding: 5px 6px; text-align: right; font-weight: bold; white-space: nowrap;">{{ $row['type'] === 'benefit_growth' ? '—' : '$ '.number_format($row['payment'], 2) }}</td>
                <td style="padding: 5px 6px; text-align: right; white-space: nowrap;">$ {{ number_format($row['closing_balance'], 2) }}</td>
            </tr>
        @endforeach
        <tr style="border-top: 2px solid {{ $documentColors['primary'] }}; border-bottom: 2px solid {{ $documentColors['primary'] }}; font-weight: bold; background: #f3f4f6; color: #111827; page-break-inside: avoid;">
            <td colspan="4" style="padding: 7px 6px; text-align: right; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.04em;">Totales:</td>
            <td style="padding: 7px 6px; text-align: right; white-space: nowrap;">$ {{ number_format($summary['total_principal'], 2) }}</td>
            <td style="padding: 7px 6px; text-align: right; white-space: nowrap;">$ {{ number_format($summary['total_interest'], 2) }}</td>
            <td style="padding: 7px 6px; text-align: right; color: {{ $documentColors['primary'] }}; white-space: nowrap;">$ {{ number_format($summary['total_payment'], 2) }}</td>
            <td style="padding: 7px 6px;"></td>
        </tr>
    </tbody>
</table>

<div style="margin-top: 14px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 8.5px; line-height: 1.45; color: #4b5563;">
    <p style="margin: 0 0 3px 0;"><strong>Nómina quincenal:</strong> interés simple sobre saldo insoluto (método alemán). Días previos a la primera quincena se reflejan en «Días adicionales».</p>
    <p style="margin: 0 0 3px 0;"><strong>Prestaciones:</strong> interés compuesto quincena a quincena; las filas en cursiva muestran el crecimiento del saldo (sin pago, marcado con «—») y la fila resaltada el pago total.</p>
    <p style="margin: 8px 0 0 0; text-align: center; color: #6b7280; font-size: 8px;">Generado el {{ now()->format('d/m/Y H:i') }}.</p>
</div>
