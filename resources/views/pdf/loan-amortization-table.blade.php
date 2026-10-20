@extends('document.layout')

@section('title', 'Tabla de Amortización — SIMULACIÓN')

@section('content')
<div style="margin-top: 30px;">

    {{-- Simulation disclaimer banner --}}
    <div style="background: #fef3c7; border: 2px solid #f59e0b; border-radius: 4px; padding: 8px 14px; margin-bottom: 18px; text-align: center;">
        <strong style="color: #92400e; font-size: 1.1em;">⚠ SIMULACIÓN — Documento sin valor legal</strong>
        <p style="color: #92400e; font-size: 0.9em; margin-top: 4px;">Este documento fue generado con fines informativos únicamente y no representa un compromiso formal del sindicato.</p>
    </div>

    <div style="text-align: center; margin-bottom: 18px;">
        <h2 style="font-size: 1.6em; color: {{ $primaryColor }};">Tabla de Amortización</h2>
        <p style="color: #555; font-size: 1em; margin-top: 6px;">
            Monto: <strong>$ {{ number_format($summary['amount'], 2) }}</strong> &nbsp;|&nbsp;
            Tasa mensual: <strong>{{ $summary['monthly_rate_percent'] }}</strong> &nbsp;|&nbsp;
            Desembolso: <strong>{{ \Illuminate\Support\Carbon::parse($summary['disbursement_date'])->format('d/m/Y') }}</strong>
        </p>
    </div>

    <hr style="border: 0; border-top: 2px solid {{ $primaryColor }}; margin-bottom: 16px;">

    <table style="width: 100%; border-collapse: collapse; font-size: 0.9em;">
        <thead>
            <tr style="background: {{ $primaryColor }}; color: #fff;">
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
                <tr style="border-bottom: 1px solid #e5e7eb; {{ $row['type'] === 'extra_days' ? 'background: #f3f4f6;' : ($row['type'] === 'benefit' ? 'background: #eff6ff;' : '') }}">
                    <td style="padding: 5px 8px; text-align: center;">{{ $row['period'] }}</td>
                    <td style="padding: 5px 8px;">{{ $row['label'] }}</td>
                    <td style="padding: 5px 8px; text-align: center;">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                    <td style="padding: 5px 8px; text-align: right;">$ {{ number_format($row['opening_balance'], 2) }}</td>
                    <td style="padding: 5px 8px; text-align: right;">$ {{ number_format($row['principal'], 2) }}</td>
                    <td style="padding: 5px 8px; text-align: right;">$ {{ number_format($row['interest'], 2) }}</td>
                    <td style="padding: 5px 8px; text-align: right; font-weight: bold;">$ {{ number_format($row['payment'], 2) }}</td>
                    <td style="padding: 5px 8px; text-align: right;">$ {{ number_format($row['closing_balance'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="border-top: 2px solid {{ $primaryColor }}; font-weight: bold; background: #f9fafb;">
                <td colspan="4" style="padding: 6px 8px; text-align: right;">Totales:</td>
                <td style="padding: 6px 8px; text-align: right;">$ {{ number_format($summary['total_principal'], 2) }}</td>
                <td style="padding: 6px 8px; text-align: right;">$ {{ number_format($summary['total_interest'], 2) }}</td>
                <td style="padding: 6px 8px; text-align: right;">$ {{ number_format($summary['total_payment'], 2) }}</td>
                <td style="padding: 6px 8px;"></td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 18px; font-size: 0.8em; color: #6b7280;">
        <p>* Las cifras son aproximadas. El interés simple (método alemán) se aplica a los pagos por nómina.</p>
        <p>* El interés compuesto se aplica a los pagos por prestación, cargado en la fecha de cobro de la prestación.</p>
    </div>
</div>
@endsection
