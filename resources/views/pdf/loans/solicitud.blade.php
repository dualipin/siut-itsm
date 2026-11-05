<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Solicitud de Préstamo - {{ $loan->folio }}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; font-size: 10pt; color: #111827; line-height: 1.35; padding: 20px 25px; }
    .header { width: 100%; border-bottom: 2px solid #611232; padding-bottom: 8px; margin-bottom: 12px; }
    .header table { width: 100%; }
    .header td { vertical-align: middle; }
    .logo { max-height: 45px; }
    .title { text-align: center; font-size: 13pt; font-weight: bold; color: #611232; text-transform: uppercase; margin-bottom: 10px; }
    .badge { font-size: 9pt; font-weight: bold; padding: 3px 6px; background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 4px; display: inline-block; }
    .section-title { font-size: 10pt; font-weight: bold; color: #611232; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; margin: 10px 0 6px 0; text-transform: uppercase; }
    .data-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .data-table td { padding: 3px 6px; font-size: 9pt; vertical-align: top; }
    .data-table td.label { font-weight: bold; color: #374151; width: 30%; }
    .grid-table { width: 100%; border-collapse: collapse; margin: 8px 0; }
    .grid-table th, .grid-table td { border: 1px solid #9ca3af; padding: 4px 6px; font-size: 8.5pt; text-align: left; }
    .grid-table th { background-color: #f9fafb; font-weight: bold; }
    .legal-text { font-size: 7.5pt; text-align: justify; color: #374151; margin: 8px 0; line-height: 1.25; }
    .signatures-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .signatures-table td { width: 33.33%; text-align: center; vertical-align: bottom; padding: 0 10px; }
    .sig-line { border-top: 1px solid #000; margin-top: 35px; padding-top: 4px; font-size: 8pt; font-weight: bold; }
    .sig-sub { font-size: 7pt; color: #4b5563; }
    .sig-img { max-height: 45px; max-width: 140px; margin: 0 auto; display: block; }
    .huella-box { width: 70px; height: 90px; border: 1px solid #000; margin: 0 auto 5px auto; text-align: center; font-size: 7pt; color: #6b7280; padding-top: 35px; }
  </style>
</head>
<body>
  <div class="header">
    <table>
      <tr>
        <td style="width: 20%;">
          @if(file_exists(public_path('assets/images/logo.webp')))
            <img src="{{ public_path('assets/images/logo.webp') }}" class="logo" alt="Logo">
          @endif
        </td>
        <td style="text-align: center; width: 60%;">
          <div style="font-weight: bold; font-size: 11pt; color: #611232;">SINDICATO ÚNICO DE TRABAJADORES DEL ITSM</div>
          <div style="font-size: 8pt; color: #4b5563;">Caja de Ahorro y Préstamos de los Trabajadores</div>
        </td>
        <td style="text-align: right; width: 20%;">
          <span class="badge">{{ $loan->folio }}</span>
        </td>
      </tr>
    </table>
  </div>

  <div class="title">Solicitud de Préstamo a la Caja de Ahorro</div>

  <div class="section-title">Datos del Solicitante</div>
  <table class="data-table">
    <tr>
      <td class="label">Nombre completo:</td>
      <td>{{ $user->full_name }}</td>
      <td class="label">CURP:</td>
      <td>{{ $user->curp ?? 'No registrada' }}</td>
    </tr>
    <tr>
      <td class="label">Categoría / Puesto:</td>
      <td>{{ $user->category ?? 'No especificada' }}</td>
      <td class="label">Teléfono:</td>
      <td>{{ $user->phone ?? 'No especificado' }}</td>
    </tr>
    <tr>
      <td class="label">Dirección:</td>
      <td colspan="3">{{ $user->address ?? 'No especificada' }}</td>
    </tr>
    <tr>
      <td class="label">Cuenta bancaria / Banco:</td>
      <td>{{ $loan->bank_name ?? 'N/A' }} — {{ $loan->bank_account ?? 'N/A' }}</td>
      <td class="label">CLABE Interbancaria:</td>
      <td>{{ $loan->interbank_clabe ?? 'N/A' }}</td>
    </tr>
  </table>

  <div class="section-title">Condiciones del Préstamo Solicitado</div>
  <table class="data-table">
    <tr>
      <td class="label">Monto Solicitado:</td>
      <td style="font-weight: bold; color: #15803d;">$ {{ number_format($loan->requested_amount, 2) }}</td>
      <td class="label">Fecha de Solicitud:</td>
      <td>{{ $loan->created_at->format('d/m/Y') }}</td>
    </tr>
    <tr>
      <td class="label">Forma de Pago:</td>
      <td>{{ $loan->payment_type->getLabel() }}</td>
      <td class="label">Tasa Mensual:</td>
      <td>{{ number_format($loan->monthly_rate, 2) }}%</td>
    </tr>
    @if($loan->payment_type->hasNomina())
    <tr>
      <td class="label">Monto Vía Nómina:</td>
      <td>$ {{ number_format($loan->nomina_amount, 2) }}</td>
      <td class="label">Plazo Quincenas:</td>
      <td>{{ $loan->nomina_fortnights }} quincenas (inicia {{ $loan->first_fortnight_date?->format('d/m/Y') }})</td>
    </tr>
    @endif
  </table>

  @if($loan->loanBenefits->isNotEmpty())
  <div class="section-title">Desglose de Pago por Prestaciones</div>
  <table class="grid-table">
    <thead>
      <tr>
        <th>Núm</th>
        <th>Prestación</th>
        <th>Fecha de Cobro</th>
        <th style="text-align: right;">Monto Solicitado</th>
      </tr>
    </thead>
    <tbody>
      @foreach($loan->loanBenefits as $idx => $lb)
      <tr>
        <td>{{ $idx + 1 }}</td>
        <td>{{ $lb->benefit->name ?? 'Prestación' }}</td>
        <td>{{ $lb->collection_date?->format('d/m/Y') }}</td>
        <td style="text-align: right;">$ {{ number_format($lb->requested_amount, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  <div class="legal-text">
    En base a lo anterior doy mi autorización formal e <strong>irrevocable</strong> para que se me descuente el presente préstamo con los intereses generados respectivos aplicados a mi sueldo quincenal y/o a las prestaciones estipuladas, bajo los términos de esta solicitud y de la corrida de amortizaciones a la cual me comprometo pagar. Manifiesto mi libre consentimiento y reconozco que cualquier otro documento posterior que pretenda revocar este descuento carecerá de validez legal alguna frente al Sindicato y al Tecnológico.
  </div>

  <table class="signatures-table">
    <tr>
      <td>
        @if($loan->signature_data)
          <img src="{{ $loan->signature_data }}" class="sig-img" alt="Firma Solicitante">
        @endif
        <div class="sig-line">{{ $user->full_name }}</div>
        <div class="sig-sub">Solicitante (Firma digitalizada registrada)</div>
      </td>
      <td>
        <div class="huella-box">Huella digital</div>
        <div class="sig-sub">Registro de Huella</div>
      </td>
      <td>
        <div class="sig-line" style="margin-top: 55px;">Secretaría de Finanzas SIUTITSM</div>
        <div class="sig-sub">Revisión y Vo. Bo.</div>
      </td>
    </tr>
  </table>

  <div style="font-size: 6.5pt; color: #9ca3af; text-align: center; margin-top: 15px;">
    Documento oficial generado electrónicamente por el Sistema SIUT ITSM el {{ now()->format('d/m/Y H:i:s') }} — Folio {{ $loan->folio }}
  </div>
</body>
</html>
