@extends('document.layout')

@section('title', 'Simulación de Préstamo')

@section('content')
<div style="margin-top: 30px;">

    {{-- Simulation disclaimer banner --}}
    <div style="background: #fef3c7; border: 2px solid #f59e0b; border-radius: 4px; padding: 8px 14px; margin-bottom: 18px; text-align: center;">
        <strong style="color: #92400e; font-size: 1.1em;">⚠ SIMULACIÓN — Documento sin valor legal</strong>
        <p style="color: #92400e; font-size: 0.9em; margin-top: 4px;">Este documento fue generado con fines informativos únicamente y no representa un compromiso formal del sindicato.</p>
    </div>

    {{-- ===== CORRIDA FINANCIERA ===== --}}
    <div style="text-align: center; margin-bottom: 18px;">
        <h2 style="font-size: 1.8em; color: {{ $primaryColor }};">Corrida Financiera</h2>
        <p style="color: #555; font-size: 1em; margin-top: 4px;">Resumen del préstamo simulado</p>
    </div>

    <hr style="border: 0; border-top: 2px solid {{ $primaryColor }}; margin-bottom: 20px;">

    <table style="width: 100%; border-collapse: collapse; font-size: 1.05em; margin-bottom: 24px;">
        <tbody>
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 8px 10px; width: 45%; color: #6b7280; font-weight: 600; text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.05em;">Monto simulado</td>
                <td style="padding: 8px 10px; font-size: 1.2em; font-weight: bold; color: {{ $primaryColor }};">$ {{ number_format($summary['amount'], 2) }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 8px 10px; color: #6b7280; font-weight: 600; text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.05em;">Tasa de interés mensual</td>
                <td style="padding: 8px 10px; font-weight: bold;">{{ $summary['monthly_rate_percent'] }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 8px 10px; color: #6b7280; font-weight: 600; text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.05em;">Fecha de desembolso</td>
                <td style="padding: 8px 10px;">{{ \Illuminate\Support\Carbon::parse($summary['disbursement_date'])->format('d/m/Y') }}</td>
            </tr>
            @if ($summary['fortnights'])
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 8px 10px; color: #6b7280; font-weight: 600; text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.05em;">Plazo (quincenas)</td>
                <td style="padding: 8px 10px;">{{ $summary['fortnights'] }} quincenas</td>
            </tr>
            @endif
            @if ($summary['first_fortnight'])
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 8px 10px; color: #6b7280; font-weight: 600; text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.05em;">Primera quincena</td>
                <td style="padding: 8px 10px;">{{ \Illuminate\Support\Carbon::parse($summary['first_fortnight'])->format('d/m/Y') }}</td>
            </tr>
            @endif
            @if ($summary['last_fortnight'])
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 8px 10px; color: #6b7280; font-weight: 600; text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.05em;">Última quincena</td>
                <td style="padding: 8px 10px;">{{ \Illuminate\Support\Carbon::parse($summary['last_fortnight'])->format('d/m/Y') }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <table style="width: 100%; border-collapse: collapse; font-size: 1.05em; margin-bottom: 24px;">
        <thead>
            <tr style="background: {{ $primaryColor }}; color: #fff;">
                <th style="padding: 8px 10px; text-align: left; font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.05em;">Concepto</th>
                <th style="padding: 8px 10px; text-align: right; font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.05em;">Importe</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 8px 10px;">Capital (monto principal)</td>
                <td style="padding: 8px 10px; text-align: right;">$ {{ number_format($summary['total_principal'], 2) }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 8px 10px;">Total de intereses</td>
                <td style="padding: 8px 10px; text-align: right;">$ {{ number_format($summary['total_interest'], 2) }}</td>
            </tr>
            <tr style="border-bottom: 2px solid {{ $primaryColor }}; font-weight: bold; background: #f9fafb;">
                <td style="padding: 10px 10px; font-size: 1.1em;">Total a pagar</td>
                <td style="padding: 10px 10px; text-align: right; font-size: 1.1em; color: {{ $primaryColor }};">$ {{ number_format($summary['total_payment'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ===== PAGE BREAK ===== --}}
    <div style="page-break-before: always; padding-top: 10px;"></div>

    {{-- ===== TABLA DE AMORTIZACIÓN ===== --}}
    <div style="text-align: center; margin-bottom: 18px; margin-top: 20px;">
        <h2 style="font-size: 1.6em; color: {{ $primaryColor }};">Tabla de Amortización</h2>
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
        <p>* El interés simple (método alemán) se aplica a los pagos por nómina quincenal.</p>
        <p>* El interés compuesto se aplica a los pagos por prestación, cargado en la fecha de cobro de la prestación.</p>
        <p style="margin-top: 6px; text-align: center; color: #9ca3af;">Generado el {{ now()->format('d/m/Y H:i') }}. Documento informativo sin validez legal.</p>
    </div>
</div>
@endsection
