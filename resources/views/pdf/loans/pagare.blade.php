<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>PAGARÉ - {{ $loan->folio }}</title>
  @php
      $amount = (float) ($loan->approved_amount ?? $loan->requested_amount);
      $monthlyRate = (float) $loan->monthly_rate;
      $term = (int) ($termFortnights ?? ($loan->nomina_fortnights ?? 1));
      if ($term <= 0) {
          $term = 1;
      }
  @endphp
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html, body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 11px;
    }

    .container-children {
      border-bottom: 1px dotted #000;
      padding-bottom: 20px;
    }

    .pagare {
      border: 2px solid black;
      padding: 14px 20px;
      margin: 25px;
      border-radius: 10px;
      position: relative;
      background-color: #eaf6ea;
    }

    .title-box {
      text-align: center;
      border-radius: 8px;
      padding: 8px 35px;
      font-size: 18px;
      background-color: black;
      color: white;
      letter-spacing: 8px;
      font-style: italic;
      font-weight: bold;
    }

    .between {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 10px;
    }

    .header-info {
      text-align: right;
      font-size: 11px;
    }

    .header-info p {
      margin: 3px 0;
    }

    .underline {
      border-bottom: 1px solid #777;
      padding: 0 4px;
    }

    .bold {
      font-weight: bold;
    }

    .content {
      margin: 10px 0;
      text-align: justify;
      line-height: 1.5;
      font-size: 10.5px;
    }

    .content p {
      margin-top: 6px;
    }

    .box {
      border: 1px solid #777;
      padding: 8px 12px;
      border-radius: 8px;
      background-color: #ffffff;
      font-weight: bold;
      text-align: center;
      margin: 6px 0;
      font-size: 10px;
    }

    .footer-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }

    .footer-table__column {
      width: 50%;
      vertical-align: top;
    }

    .border-box {
      border: 1px solid #777;
      border-radius: 8px;
      padding: 8px 12px;
      background-color: #ffffff;
    }

    .cell-underline {
      border-bottom: 1px solid #aaa;
      padding: 3px 0;
    }

    .sig-img {
      max-height: 40px;
      max-width: 130px;
      margin: 0 auto;
      display: block;
    }
  </style>
</head>
<body class="container-main">

<div class="container-children">
  <div class="hijo">
    <section class="pagare">
      <table class="between">
        <tr>
          <td style="width: 35%;">
            <div class="title-box">
              PAGARÉ
            </div>
          </td>
          <td class="header-info" style="width: 65%;">
            <p><strong>No. {{ $loan->folio }}. Bueno por $ {{ number_format($amount, 2) }}</strong></p>
            <p>En Macuspana, Tabasco a {{ $approvalDateLong ?? now()->translatedFormat('d \d\e F \d\e Y') }}.</p>
          </td>
        </tr>
      </table>

      <div class="content">
        <p>
          Debe(mos) y pagare(mos) incondicionalmente por este Pagaré a la orden de
          <span class="bold underline">Sindicato Único de Trabajadores del Instituto Tecnológico Superior de Macuspana</span>
          en <span class="underline bold">Macuspana, Tabasco</span> el
          <span class="underline bold" style="padding: 0 30px;">{{ $lastPaymentDate ?? ($loan->disbursed_at?->format('d/m/Y') ?? '30/11/'.now()->year) }}</span>
        </p>
        <p>
          La cantidad de:
        </p>
        <div class="box">
          {{ $amountInWords }}
        </div>
        <p>
          Valor recibido a mi (nuestra) entera satisfacción. Este pagaré forma parte de una serie numerada de 1 al
          <span class="underline bold" style="padding: 0 15px;">{{ $term }}</span> y todos están sujetos a la condición de que, al no pagarse cualquiera de ellos a su vencimiento, serán exigibles todos los que le sigan en número, además de los ya vencidos, desde la fecha de vencimiento de este documento hasta el día de su liquidación, causará intereses moratorios al tipo de
          <span class="underline bold" style="padding: 0 10px;">{{ $monthlyRate }}</span>% mensual, pagadero en esta ciudad juntamente con el principal.
        </p>
      </div>

      <table class="footer-table">
        <tr>
          <td class="footer-table__column" style="padding-right: 15px;">
            <div class="border-box">
              <div style="font-weight: bold; text-align: center; border-bottom: 1px solid #ccc; padding-bottom: 3px; margin-bottom: 5px; font-size: 10px;">
                Datos del deudor
              </div>
              <div class="cell-underline"><strong>Nombre:</strong> {{ $user->full_name }}</div>
              <div class="cell-underline"><strong>Dirección:</strong> {{ $user->address ?? 'No especificada' }}</div>
              <div class="cell-underline" style="border-bottom: none;"><strong>Teléfono:</strong> {{ $user->phone ?? 'No especificado' }}</div>
            </div>
          </td>
          <td class="footer-table__column" style="text-align: center; vertical-align: bottom;">
            <div style="font-weight: bold; margin-bottom: 8px;">Acepto(amos)</div>
            @if($loan->signature_data)
              <img src="{{ $loan->signature_data }}" class="sig-img" alt="Firma">
            @endif
            <div style="border-top: 1px solid #000; width: 85%; margin: 0 auto; padding-top: 3px; font-weight: bold;">
              {{ $user->full_name }}
            </div>
            <div style="font-size: 9px; color: #555;">Firma(s)</div>
          </td>
        </tr>
      </table>
    </section>
  </div>
</div>

</body>
</html>
