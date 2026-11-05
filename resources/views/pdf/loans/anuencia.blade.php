<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Anuencia de Descuento - {{ $loan->folio }}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; font-size: 10pt; color: #000; line-height: 1.4; padding: 25px 30px; }
    .fecha { text-align: right; margin-bottom: 14px; font-size: 9.5pt; }
    .asunto { font-weight: bold; text-align: right; margin-bottom: 12px; }
    .destinatario { margin-bottom: 12px; font-weight: bold; text-transform: uppercase; font-size: 9pt; }
    .destinatario p { margin: 1px 0; }
    .atencion { text-align: center; margin: 10px 0 14px 0; font-weight: bold; font-size: 8.5pt; }
    .body-text { text-align: justify; margin-bottom: 12px; font-size: 9pt; }
    .tabla-aplica { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 8.5pt; }
    .tabla-aplica th, .tabla-aplica td { border: 1px solid #000; padding: 4px 6px; text-align: center; }
    .tabla-aplica th { background: #f3f4f6; font-weight: bold; }
    .clausulas-title { text-align: center; font-weight: bold; text-decoration: underline; margin: 12px 0 8px 0; font-size: 9pt; }
    .clausula { text-align: justify; margin-bottom: 8px; font-size: 8pt; line-height: 1.3; }
    .firmas-dos-col { width: 100%; border-collapse: collapse; margin-top: 20px; }
    .firmas-dos-col td { width: 50%; text-align: center; vertical-align: bottom; padding: 0 15px; }
    .firma-inner-line { border-top: 1px solid #000; margin-top: 40px; padding-top: 4px; font-size: 8pt; font-weight: bold; }
    .sig-img { max-height: 45px; max-width: 140px; margin: 0 auto; display: block; }
    .ccp-section { margin-top: 15px; font-size: 7.5pt; font-weight: bold; color: #4b5563; }
    .ccp-section p { margin: 2px 0; }
  </style>
</head>
<body>
  <p class="fecha">
    Macuspana, Tabasco a {{ $approvalDateLong ?? now()->translatedFormat('d \d\e F \d\e Y') }}.
  </p>

  <p class="asunto">ASUNTO: ANUENCIA DE DESCUENTO.</p>

  <div class="destinatario">
    <p>LIC. MANOLO GÓMEZ MORALES</p>
    <p>DIRECTOR GENERAL DEL I.T.S.M.</p>
    <p>P R E S E N T E:</p>
  </div>

  <div class="atencion">
    <p>AT'N: ENCARGADO DE LA SUBDIRECCIÓN DE ADMINISTRACIÓN Y FINANZAS DEL ITSM.</p>
  </div>

  <p class="body-text">
    Quien suscribe <strong>{{ $user->full_name }}</strong>, de conformidad con lo establecido por el Artículo 110 fracción IV de la Ley Federal del Trabajo, por medio del presente tengo a bien solicitarle se me descuente de mis percepciones ordinarias y/o extraordinarias la cantidad aprobada de <strong>$ {{ number_format($loan->approved_amount ?? $loan->requested_amount, 2) }}</strong> más el <strong>{{ number_format($loan->monthly_rate, 2) }}%</strong> de interés mensual, adeudo que he contraído voluntariamente con la Caja de Ahorro de los Agremiados del Sindicato Único de Trabajadores del ITSM (SIUTITSM), de acuerdo con la siguiente programación de descuentos:
  </p>

  <table class="tabla-aplica">
    <thead>
      <tr>
        <th style="width: 8%;">Núm</th>
        <th style="width: 42%; text-align: left;">Concepto / Prestación</th>
        <th style="width: 25%;">Monto</th>
        <th style="width: 25%;">Observación</th>
      </tr>
    </thead>
    <tbody>
      @php $rowNum = 1; @endphp
      @if($loan->payment_type->hasNomina())
      <tr>
        <td>{{ $rowNum++ }}</td>
        <td style="text-align: left;">Nómina quincenal</td>
        <td>$ {{ number_format($loan->nomina_amount, 2) }}</td>
        <td>{{ $loan->nomina_fortnights }} quincenas (inicia {{ $loan->first_fortnight_date?->format('d/m/Y') }})</td>
      </tr>
      @endif
      @foreach($loan->loanBenefits as $lb)
      <tr>
        <td>{{ $rowNum++ }}</td>
        <td style="text-align: left;">{{ $lb->benefit->name ?? 'Prestación' }}</td>
        <td>$ {{ number_format($lb->approved_amount ?? $lb->requested_amount, 2) }}</td>
        <td>Fecha: {{ $lb->collection_date?->format('d/m/Y') }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <p class="body-text">
    Expreso estar consciente de los topes de ley, mas sin embargo autorizo de manera <strong>irrevocable</strong> el descuento acordado en virtud de la necesidad del recurso otorgado por mi Organización Sindical, manifestando mi pleno consentimiento y obligándome a respetar las siguientes cláusulas:
  </p>

  <p class="clausulas-title">CLÁUSULAS DE IRREVOCABILIDAD ABSOLUTA</p>

  <p class="clausula">
    <strong>PRIMERO:</strong> El presente acuerdo no podrá ser revocado o modificado por ningún documento posterior ante el Instituto Tecnológico Superior de Macuspana o ante el Departamento de Recursos Humanos o Dirección General.
  </p>
  <p class="clausula">
    <strong>SEGUNDO:</strong> Cualquier intento de revocar o suspender el presente descuento será nulo y sin efecto legal alguno, manteniéndose la prelación del descuento a favor de la Caja de Ahorro del SIUTITSM.
  </p>

  <table class="firmas-dos-col">
    <tr>
      <td>
        <p style="font-weight: bold; font-size: 8.5pt; margin-bottom: 5px;">Atentamente (El Trabajador)</p>
        @if($loan->signature_data)
          <img src="{{ $loan->signature_data }}" class="sig-img" alt="Firma">
        @endif
        <div class="firma-inner-line">{{ $user->full_name }}</div>
        <div style="font-size: 7pt; color: #555;">Firma registrada</div>
      </td>
      <td>
        <p style="font-weight: bold; font-size: 8.5pt; margin-bottom: 5px;">Por el Sindicato (Otorgante)</p>
        <div class="firma-inner-line" style="margin-top: 55px;">Representación SIUTITSM</div>
        <div style="font-size: 7pt; color: #555;">Secretaría de Finanzas</div>
      </td>
    </tr>
  </table>

  <div class="ccp-section">
    <p>C.C.P. Interesado.</p>
    <p>C.C.P. Sindicato Único de Trabajadores del ITSM.</p>
    <p>C.C.P. Recursos Humanos ITSM.</p>
    <p>C.C.P. Archivo.</p>
  </div>
</body>
</html>
