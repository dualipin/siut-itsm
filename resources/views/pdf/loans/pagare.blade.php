<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Pagaré - {{ $loan->folio }}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; padding: 25px 30px; }
    .pagare-box { border: 2px solid #000; border-radius: 8px; padding: 16px 20px; background-color: #f7faf7; }
    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    .title-box { background: #000; color: #fff; text-align: center; font-size: 15pt; font-weight: bold; font-style: italic; letter-spacing: 6px; padding: 6px 20px; border-radius: 6px; }
    .header-info { text-align: right; font-size: 9pt; }
    .header-info p { margin: 2px 0; }
    .content { text-align: justify; margin: 12px 0; line-height: 1.45; font-size: 9.5pt; }
    .content p { margin-bottom: 8px; }
    .underline { border-bottom: 1px solid #555; padding: 0 4px; font-weight: bold; }
    .amount-words-box { border: 1px solid #777; border-radius: 6px; padding: 6px 10px; font-weight: bold; font-size: 9pt; text-align: center; background: #fff; margin: 8px 0; }
    .debtor-table { width: 100%; border-collapse: collapse; margin-top: 18px; }
    .debtor-table td { vertical-align: top; }
    .debtor-box { border: 1px solid #777; border-radius: 6px; padding: 8px 12px; background: #fff; font-size: 8.5pt; }
    .debtor-box td { padding: 2px 4px; }
    .debtor-box td.label { font-weight: bold; width: 25%; }
    .sig-img { max-height: 45px; max-width: 140px; margin: 0 auto; display: block; }
    .sig-line { border-top: 1px solid #000; margin-top: 35px; padding-top: 4px; font-size: 8.5pt; font-weight: bold; text-align: center; }
  </style>
</head>
<body>
  <div class="pagare-box">
    <table class="header-table">
      <tr>
        <td style="width: 40%;">
          <div class="title-box">PAGARÉ</div>
        </td>
        <td class="header-info" style="width: 60%;">
          <p><strong>No. {{ $loan->folio }}</strong> — Bueno por <strong>$ {{ number_format($loan->approved_amount ?? $loan->requested_amount, 2) }}</strong></p>
          <p>En Macuspana, Tabasco a {{ $approvalDateLong ?? now()->translatedFormat('d \d\e F \d\e Y') }}</p>
        </td>
      </tr>
    </table>

    <div class="content">
      <p>
        Debe(mos) y pagaré(mos) incondicionalmente por este Pagaré a la orden del
        <span class="underline">Sindicato Único de Trabajadores del Instituto Tecnológico Superior de Macuspana</span>
        en <span class="underline">Macuspana, Tabasco</span> a más tardar el día
        <span class="underline">{{ $lastPaymentDate ?? ($loan->disbursed_at?->format('d/m/Y') ?? '30/11/'.now()->year) }}</span>.
      </p>

      <p>La cantidad de:</p>
      <div class="amount-words-box">
        {{ $amountInWords }}
      </div>

      <p>
        Valor recibido a mi (nuestra) entera satisfacción. Este pagaré está sujeto a la condición de que, al no pagarse a su vencimiento, causará intereses moratorios al tipo de <span class="underline">{{ number_format($loan->monthly_rate, 2) }}%</span> mensual, pagadero en esta ciudad juntamente con el principal.
      </p>
    </div>

    <table class="debtor-table">
      <tr>
        <td style="width: 55%; padding-right: 15px;">
          <div class="debtor-box">
            <div style="font-weight: bold; text-align: center; border-bottom: 1px solid #ddd; padding-bottom: 3px; margin-bottom: 4px;">Datos del Deudor</div>
            <table>
              <tr>
                <td class="label">Nombre:</td>
                <td>{{ $user->full_name }}</td>
              </tr>
              <tr>
                <td class="label">CURP:</td>
                <td>{{ $user->curp ?? 'N/A' }}</td>
              </tr>
              <tr>
                <td class="label">Dirección:</td>
                <td>{{ $user->address ?? 'N/A' }}</td>
              </tr>
              <tr>
                <td class="label">Teléfono:</td>
                <td>{{ $user->phone ?? 'N/A' }}</td>
              </tr>
            </table>
          </div>
        </td>
        <td style="width: 45%; text-align: center; vertical-align: bottom;">
          <div style="font-weight: bold; font-size: 9pt; margin-bottom: 10px;">Acepto incondicionalmente</div>
          @if($loan->signature_data)
            <img src="{{ $loan->signature_data }}" class="sig-img" alt="Firma">
          @endif
          <div class="sig-line">{{ $user->full_name }}</div>
          <div style="font-size: 7.5pt; color: #555;">Firma del Deudor</div>
        </td>
      </tr>
    </table>
  </div>
</body>
</html>
