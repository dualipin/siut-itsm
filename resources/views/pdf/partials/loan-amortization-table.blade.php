<div style="text-align: center; margin-bottom: 18px; margin-top: 20px;">
    <h2 style="font-size: 1.6em; color: {{ $documentColors['primary'] }};">Tabla de Amortización</h2>
</div>

<hr style="border: 0; border-top: 2px solid {{ $documentColors['primary'] }}; margin-bottom: 16px;">

<table style="width: 100%; border-collapse: collapse; font-size: 0.9em;">
    <thead>
        <tr style="background: {{ $documentColors['primary'] }}; color: {{ $documentColors['primary_content'] }};">
            <th style="padding: 6px 8px; text-align: center;">#</th>
            <th style="padding: 6px 8px; text-align: left;">Descripción</th>
            <th style="padding: 6px 8px; text-align: center;">Fecha</th>
            <th style="padding: 6px 8px; text-align: right;">Saldo inicial</th>
            <th style="padding: 6px 8px; text-align: right;">Capital</th>
            <th style="padding: 6px 8px; text-align: right;">Interés</th>
            <th style="padding: 6px 8px; text-align: right;">Pago</th>
            <th style="padding: 6px 8px; text-align: right;">Saldo final</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($amortizationTable as $row)
            @php
                $rowStyle = match($row['type']) {
                    'extra_days'     => 'background: '.$documentColors['base_200'].';',
                    'benefit_growth' => 'background: '.$documentColors['base_100'].'; color: '.$documentColors['base_content'].'; font-style: italic;',
                    'benefit'        => 'background: '.$documentColors['info'].';',
                    default          => '',
                };
            @endphp
            <tr style="border-bottom: 1px solid {{ $documentColors['base_300'] }}; {{ $rowStyle }}">
                <td style="padding: 5px 8px; text-align: center;">{{ $row['period'] }}</td>
                <td style="padding: 5px 8px;">{{ $row['label'] }}</td>
                <td style="padding: 5px 8px; text-align: center;">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                <td style="padding: 5px 8px; text-align: right;">$ {{ number_format($row['opening_balance'], 2) }}</td>
                <td style="padding: 5px 8px; text-align: right;">{{ $row['type'] === 'benefit_growth' ? '—' : '$ '.number_format($row['principal'], 2) }}</td>
                <td style="padding: 5px 8px; text-align: right;">$ {{ number_format($row['interest'], 2) }}</td>
                <td style="padding: 5px 8px; text-align: right; font-weight: bold;">{{ $row['type'] === 'benefit_growth' ? '—' : '$ '.number_format($row['payment'], 2) }}</td>
                <td style="padding: 5px 8px; text-align: right;">$ {{ number_format($row['closing_balance'], 2) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr style="border-top: 2px solid {{ $documentColors['primary'] }}; font-weight: bold; background: {{ $documentColors['base_100'] }};">
            <td colspan="4" style="padding: 6px 8px; text-align: right;">Totales:</td>
            <td style="padding: 6px 8px; text-align: right;">$ {{ number_format($summary['total_principal'], 2) }}</td>
            <td style="padding: 6px 8px; text-align: right;">$ {{ number_format($summary['total_interest'], 2) }}</td>
            <td style="padding: 6px 8px; text-align: right;">$ {{ number_format($summary['total_payment'], 2) }}</td>
            <td style="padding: 6px 8px;"></td>
        </tr>
    </tfoot>
</table>

<div style="margin-top: 18px; font-size: 0.8em; color: {{ $documentColors['base_content'] }};">
    <p>* El interés simple (método alemán) se aplica a los pagos por nómina quincenal.</p>
    <p>* Las prestaciones crecen quincena a quincena con la tasa diaria equivalente usando interés compuesto; las filas en cursiva muestran el crecimiento intermedio y la fila final concentra el pago total.</p>
    <p style="margin-top: 6px; text-align: center; color: {{ $documentColors['base_content'] }};">Generado el {{ now()->format('d/m/Y H:i') }}. Documento informativo sin validez legal.</p>
</div>
