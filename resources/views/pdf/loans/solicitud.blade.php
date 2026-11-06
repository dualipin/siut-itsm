@extends('document.layout')

@section('title', 'Solicitud de Préstamo - ' . $loan->folio)

@section('content')
@php
    $documentColors = array_merge([
        'primary' => '#611232',
        'primary_content' => '#ffffff',
        'base_100' => '#f9fafb',
        'base_200' => '#f2f2f2',
        'base_300' => '#dddddd',
        'base_content' => '#000000',
    ], is_array($documentColors ?? null) ? $documentColors : []);

    $amount = (float) ($loan->approved_amount ?? $loan->requested_amount);
    $monthlyRate = (float) $loan->monthly_rate;
    $hasNomina = $loan->payment_type->hasNomina();
    $nominaFortnights = (int) ($loan->nomina_fortnights ?? 0);
    $calculatedInstallment = ($hasNomina && $nominaFortnights > 0)
        ? round(($loan->nomina_amount ?? $amount) / $nominaFortnights, 2)
        : ($installmentAmount ?? $amount);
    $firstDate = $loan->first_fortnight_date?->format('d/m/Y') ?? ($firstFortnightDate ?? now()->addDays(15)->format('d/m/Y'));
    $paymentConfigCount = ($hasNomina ? 1 : 0) + $loan->loanBenefits->count();
    $year = $loan->created_at?->year ?? now()->year;
@endphp

<style>
    .stamp-header {
        width: 100%;
        margin-bottom: 6px;
    }
    .stamp-header strong {
        font-size: 11px;
    }
    .title {
        text-align: center;
        border-radius: 6px;
        padding: 5px;
        margin: 8px 0;
        font-size: 14px;
        font-weight: bold;
        background-color: {{ $documentColors['primary'] }};
        color: {{ $documentColors['primary_content'] }};
        text-transform: uppercase;
    }
    .data p {
        margin: 3px 0;
        font-size: 9.5px;
    }
    .data ul {
        margin: 3px 0 5px 18px;
        font-size: 9px;
    }
    .data ul > li {
        padding: 1px 0;
    }
    .two-col-grid {
        width: 100%;
        border-collapse: collapse;
        margin: 3px 0;
        font-size: 9.5px;
    }
    .two-col-grid td {
        padding: 2px 0;
        vertical-align: top;
    }
    .table-pagare {
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
        font-size: 8.5px;
    }
    .table-pagare th,
    .table-pagare td {
        border: 1px solid black;
        padding: 3px 4px;
        text-align: left;
    }
    .table-pagare th {
        background-color: #f3f4f6;
        font-weight: bold;
    }
    .box-deposito {
        border: 1px solid black;
        margin: 6px 0;
        width: 100%;
        padding: 5px 8px;
        font-size: 9px;
    }
    .legal-text {
        font-size: 8px;
        text-align: justify;
        line-height: 1.25;
        margin: 5px 0;
    }
    .anexo {
        margin: 6px 0;
        font-size: 8px;
    }
    .anexo ul {
        list-style: none;
        padding-left: 8px;
        margin: 2px 0;
    }
    .anexo p {
        margin-top: 3px;
    }
    .square {
        display: inline-block;
        width: 10px;
        height: 10px;
        border: 1px solid black;
        margin-left: 3px;
        vertical-align: middle;
    }
    .signature-section {
        width: 100%;
        margin-top: 10px;
    }
    .signature-section table {
        width: 100%;
        border-collapse: collapse;
        text-align: center;
    }
    .signature-section td {
        padding: 3px;
        vertical-align: top;
    }
    .huella {
        border: 1px solid black;
        width: 80px;
        height: 110px;
        margin: 0 auto;
        text-align: center;
    }
    .huella p {
        font-size: 7.5px;
        text-align: center;
        margin-top: 4px;
        color: #555;
    }
    .signature-line {
        border-bottom: 1px solid black;
        margin-top: 25px;
        margin-bottom: 2px;
    }
    .sig-img {
        max-height: 35px;
        max-width: 120px;
        margin: 0 auto -5px auto;
        display: block;
    }
    .ccp {
        font-size: 7.5px;
        margin-top: 8px;
        font-style: italic;
    }
    .ccp p {
        margin: 1px 0;
    }
</style>

<div class="stamp-header">
    <table style="width: 100%;">
        <tr>
            <td style="text-align: left;"><strong>Préstamo No. {{ $loan->folio }}</strong></td>
            <td style="text-align: right;"><strong>Fecha de otorgamiento del préstamo: {{ $approvalDateLong ?? now()->translatedFormat('d/m/Y') }}</strong></td>
        </tr>
    </table>
</div>

<h1 class="title">
    SOLICITUD DE PRÉSTAMO A LA CAJA DE AHORRO {{ $year }}
</h1>

<section class="data">
    <p><strong>Nombre del que Solicita el préstamo:</strong> <span>{{ $user->full_name }}</span></p>
    <p><strong>Monto Solicitado del Préstamo:</strong> <span>$ {{ number_format($amount, 2) }}</span></p>
    <p><strong>Dia en que solicita el préstamo:</strong> <span>{{ $loan->created_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}</span></p>
    <p><strong>Forma de pago en la que acepta se le aplique el descuento del préstamo solicitado; en caso de ser aprobado:</strong></p>
    <ul>
        <li>Descuento en parcialidades estimadas de $ {{ number_format($calculatedInstallment, 2) }}</li>
        <li>Total de configuraciones de descuento: {{ $paymentConfigCount }}</li>
    </ul>
</section>

<table class="two-col-grid">
    <tr>
        <td style="width: 50%;"><strong>Categoria:</strong> {{ $user->category ?? 'No especificada' }}</td>
        <td style="width: 50%;"><strong>Teléfono:</strong> {{ $user->phone ?? 'No especificado' }}</td>
    </tr>
    <tr>
        <td><strong>N. DE CUENTA Y/O CLABE INTERBANCARIA:</strong> {{ $loan->interbank_clabe ?? ($user->interbank_clabe ?? 'N/A') }}</td>
        <td><strong>BANCO:</strong> {{ $loan->bank_name ?? 'N/A' }}</td>
    </tr>
</table>

<table class="table-pagare">
    <thead>
        <tr>
            <th>N. Pagare</th>
            <th>Monto Pagare</th>
            <th>Interés</th>
            <th>Fecha</th>
            <th>Otros</th>
            <th>Nombre y firma del trabajador</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $loan->folio }}</td>
            <td>$ {{ number_format($amount, 2) }}</td>
            <td>{{ $monthlyRate }}%</td>
            <td>{{ $firstDate }}</td>
            <td>Plazo: {{ $nominaFortnights }} quincenas</td>
            <td>{{ $user->full_name }}</td>
        </tr>
    </tbody>
</table>

<table class="box-deposito">
    <tr>
        <td>Cuenta de depósito: {{ $loan->bank_account ?? 'N/A' }}</td>
    </tr>
</table>

<p class="legal-text">
    En base a lo anterior doy mi autorización para que se me descuente este préstamo con los intereses generados respectivos aplicados a mi sueldo quincenal y/o a las prestaciones de fin de año, bajo los términos de esta solicitud la cual fue requisitada por mi persona y como Trabajador del Instituto Tecnológico Superior de Macuspana, estando consiente y expresando mi voluntad que estos descuentos disminuirán mis alcenses netos de salario y el de mis prestaciones que percibo en caso de que haya afectado este concepto, haciendo énfasis y puntualizando que ningún otro escrito posterior al presente que se genere por mi persona, la Subdirección de Administración, o la Dirección General del Instituto Tecnológico Superior de Macuspana, impedirá mi voluntad de que se realice este descuento, ya que tiene la potestad de ser <strong>irrevocable</strong> a cualquier otro documento emitido por mi persona de manera posterior, ante el departamento de Recursos Humanos, el cual he manifestado con base a que fue un recurso que solicite con anticipación a la Caja de Ahorro del Sindicato Único de Trabajadores del Instituto Tecnológico Superior de Macuspana y que de buena fé me otorgaron dejando en garantía mi sueldo y/o Prestaciones y firmando la corrida tabular de amortizaciones a la cual me comprometí pagar.
</p>

<p class="legal-text">
    1. El sindicato se obliga a dejarme un porcentaje en mi salario al igual que un servidor es obliga a no disponer de lo aquí acordado en mi nomina respectiva.<br>
    2. En caso de cambiar mi situación de pago a la caja de ahorro acepto se me cobre 1% más del interés ordinario mensual.<br>
    3. Acepto que solamente tendré una oportunidad de hacer cambio de mi situación de cobro tomando en cuenta el punto número 2.<br>
    4. En caso de hacer algún pago anticipado en efectivo éste será depositado al banco a la cuenta de la caja de ahorro y solamente entregaré la ficha de depósito de la cantidad abonada al representante de la caja de ahorro.<br>
    5. Acepto que no podré hacer cambios en el Departamento de Personal ni en la Subdirección de Administración y Finanzas ni en la Dirección General del Instituto Tecnológico superior de Macuspana a este préstamo otorgado para evitar mi incumplimiento al pago del mismo; a excepción se haya liquidado con anterioridad y un servidor junto con el sindicato lo expresemos por escrito al área correspondiente del ITSM.<br>
    6. En caso de incumplir con alguna de estas cláusulas el sindicato queda libre de hacer cualquier proceso legal correspondiente.
</p>

<div class="anexo">
    <strong>Interés de préstamos de la caja de ahorro de {{ $year }}</strong>
    <ul>
        <li>Ahorrador y agremiado 6.0%</li>
        <li>Agremiado y no ahorrador 7.5%</li>
        <li>Ahorrador y no agremiado 8.5%</li>
        <li>No ahorrador y no agremiado 9.5%</li>
    </ul>

    <p>
        <strong>NOTA: </strong>Cualquier cambio de lo arriba solicitado, favor de pasar a la oficina Sindical para modificar o atender el asunto a tratar, Todos los préstamos solicitados son vía nómina. Mucho agradeceremos su comprensión al respecto. Ayúdenos a darles un buen servicio. Gracias.
    </p>

    <table style="margin-top: 5px;">
        <tr>
            <td style="vertical-align: middle;">Préstamo sujeto a revisión:</td>
            <td style="padding-left: 10px;">
                <table style="border: 1px solid black; border-collapse: collapse; font-size: 8px;">
                    <tr>
                        <td style="padding: 2px 6px; border: 1px solid black;">SI <span class="square"></span></td>
                        <td style="padding: 2px 6px; border: 1px solid black;">NO <span class="square"></span></td>
                    </tr>
                    <tr>
                        <td colspan="2" style="text-align: center; font-weight: bold; padding: 2px 6px;">APROBACIÓN</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

<p style="font-size: 8px; margin-top: 4px;">
    Se anexa corrida de amortización de descuento, copia de mi recibo de pago Nominales/Prestaciones reciente y copia de la credencial de elector o (INE) y Pagaré en caso de hacer aprobada esta solicitud.
</p>

<div class="signature-section">
    <table>
        <tr>
            <td style="width: 75%; vertical-align: top;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td colspan="2" style="font-weight: bold; font-size: 9px; padding-bottom: 2px;">ATENTAMENTE</td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding-top: 15px;">
                            @if($loan->signature_data)
                                <img src="{{ $loan->signature_data }}" class="sig-img" alt="Firma Solicitante">
                            @endif
                            <div class="signature-line" style="width: 80%; margin: 0 auto 3px auto;"></div>
                            <div style="font-size: 8.5px; font-weight: bold;">{{ $user->full_name }}</div>
                            <div style="font-size: 7.5px; color: #444;">Nombre y firma del solicitante</div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="font-weight: bold; font-size: 9px; padding-top: 8px;">Vo. Bo.</td>
                    </tr>
                    <tr>
                        <td style="width: 50%; padding: 5px 10px;">
                            <strong style="font-size: 8.5px;">AUTORIZO</strong>
                            <div class="signature-line" style="margin-top: 30px;"></div>
                            <div style="font-size: 8px;">MIDS Luiz Sosa Castro</div>
                            <div style="font-weight: bold; font-size: 7.5px;">Secretario General</div>
                        </td>
                        <td style="width: 50%; padding: 5px 10px;">
                            <strong style="font-size: 8.5px;">SUPERVISO</strong>
                            <div class="signature-line" style="margin-top: 30px;"></div>
                            <div style="font-size: 8px;">Ing. Jesus Antonio Lopez Hernandez</div>
                            <div style="font-weight: bold; font-size: 7.5px;">Secretario de Finanzas</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 25%; text-align: center; vertical-align: middle;">
                <div class="huella">
                    <p>Huella del Trabajador</p>
                </div>
            </td>
        </tr>
    </table>
</div>

<div class="ccp">
    <p>c.c.p Interesado</p>
    <p>c.c.p Sindicato Único de Trabajadores del Instituto Tecnológico Superior de Macuspana</p>
    <p>c.c.p Departamento de Personal, para su conocimiento</p>
</div>
@endsection
