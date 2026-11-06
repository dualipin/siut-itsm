@extends('document.layout')

@section('title', 'Anuencia de Descuento - ' . $loan->folio)

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
    $firstDate = $loan->first_fortnight_date?->format('Y-m-d') ?? ($firstFortnightDate ?? now()->addDays(15)->format('Y-m-d'));
    $totalNominaAmount = ($loan->loanBenefits->isEmpty())
        ? $amount
        : (float) ($loan->nomina_amount ?? $amount);
@endphp

<style>
    .page {
        margin: 0 auto;
        font-family: Arial, sans-serif;
        font-size: 10pt;
        color: #000000;
    }

    /* ── Fecha ── */
    .fecha {
        text-align: right;
        margin-bottom: 12px;
        font-size: 10pt;
    }

    /* ── Asunto ── */
    .asunto {
        font-weight: bold;
        text-transform: uppercase;
        margin-bottom: 12px;
        text-align: right;
        font-size: 10pt;
    }

    /* ── Destinatario ── */
    .destinatario {
        margin-bottom: 6px;
        font-size: 9.5pt;
    }

    .destinatario p {
        margin: 0;
        font-weight: bold;
        text-transform: uppercase;
        line-height: 1.25;
    }

    /* ── ATN ── */
    .atencion {
        margin-top: 10px;
        margin-bottom: 12px;
        text-align: center;
        font-size: 9.5pt;
    }

    .atencion p {
        margin: 0;
        font-weight: bold;
        line-height: 1.25;
    }

    .atencion .sub {
        font-weight: bold;
    }

    /* ── Cuerpo ── */
    .body-text {
        text-align: justify;
        margin-bottom: 10px;
        line-height: 1.35;
        font-size: 9.5pt;
    }

    /* ── Tabla ── */
    .tabla-aplica {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
        font-size: 9pt;
    }

    .tabla-aplica th,
    .tabla-aplica td {
        border: 1px solid #000000;
        padding: 4px 6px;
        text-align: center;
        vertical-align: middle;
    }

    .tabla-aplica th {
        font-weight: bold;
        background-color: #ffffff;
    }

    .tabla-aplica td.col-num {
        width: 6%;
    }

    .tabla-aplica td.col-prestacion {
        width: 38%;
        text-align: left;
    }

    .tabla-aplica td.col-si {
        width: 16%;
    }

    .tabla-aplica td.col-no {
        width: 16%;
    }

    .tabla-aplica td.col-obs {
        width: 24%;
        text-align: left;
    }

    /* ── Cláusulas ── */
    .clausulas-title {
        text-align: center;
        font-weight: bold;
        text-decoration: underline;
        margin-top: 12px;
        margin-bottom: 8px;
        font-size: 10pt;
    }

    .clausula {
        text-align: justify;
        margin-bottom: 10px;
        line-height: 1.35;
        font-size: 9.5pt;
    }

    /* ── Sección de firmas ── */
    .firmas-section {
        margin-top: 20px;
    }

    .firma-block {
        margin-bottom: 18px;
        text-align: center;
    }

    .firma-label {
        font-weight: bold;
        margin-bottom: 25px;
        text-align: center;
        font-size: 9.5pt;
    }

    .sig-img {
        max-height: 45px;
        max-width: 140px;
        margin: 0 auto -8px auto;
        display: block;
    }

    .firma-line {
        border-top: 1px solid #000000;
        width: 75%;
        margin: 0 auto 5px auto;
    }

    .firma-desc {
        text-align: center;
        font-weight: bold;
        font-size: 9.5pt;
        width: 85%;
        margin: 0 auto;
        line-height: 1.25;
    }

    .firma-sub {
        text-align: center;
        font-weight: normal;
        font-size: 8.5pt;
        width: 85%;
        margin: 0 auto;
        line-height: 1.2;
    }

    /* ── CCP ── */
    .ccp-section {
        margin-top: 16px;
    }

    .ccp-section p {
        margin: 2px 0;
        font-weight: bold;
        font-size: 9pt;
    }
</style>

<div class="page">
    <!-- Fecha -->
    <p class="fecha">
        Macuspana, Tabasco a {{ $approvalDateLong ?? now()->translatedFormat('d \d\e F \d\e Y') }}.
    </p>

    <!-- Asunto -->
    <p class="asunto">ASUNTO: ANUENCIA DE DESCUENTO.</p>

    <!-- Destinatario -->
    <div class="destinatario">
        <p>LIC. MANOLO GÓMEZ MORALES</p>
        <p>DIRECTOR GENERAL DEL I.T.S.M.</p>
        <p>P R E S E N T E:</p>
    </div>

    <!-- Atención -->
    <div class="atencion">
        <p>AT'N: LIC. EDUARDO ANTONIO CORNELIO MONTEJO.</p>
        <p class="sub">ENCARGADO DE LA SUBDIRECCIÓN DE ADMINISTRACIÓN Y FINANZAS DEL ITSM.</p>
    </div>

    <!-- Párrafo principal -->
    <p class="body-text">
        Quien suscribe {{ $user->full_name }} y de conformidad en lo establecido por el Artículo 110 fracción IV de la Ley Federal del Trabajo, y por medio del presente tengo a bien solicitarle se me descuente de mi nómina quincenal la cantidad de ${{ number_format($calculatedInstallment, 2) }} y a partir de la fecha {{ $firstDate }} hasta pagar en su totalidad el presente adeudo que he contraído de manera voluntaria con la caja de ahorro de los Agremiados del Sindicato Único de Trabajadores del Instituto Tecnológico Superior de Macuspana por la cantidad total de este préstamo de ${{ number_format($amount, 2) }} más el {{ $monthlyRate }}% de interés de manera mensual y de ser necesario se me descuente de las otras prestaciones dentro del año o de las prestaciones de fin de año de acuerdo a lo listado en la siguiente tabla, al igual que los otros posibles prestamos que pueda contraer de acuerdo a mi capacidad de pago con el SIUTITSM y que sean referenciados de esta misma forma.
    </p>

    <!-- Tabla -->
    <table class="tabla-aplica">
        <thead>
            <tr>
                <th colspan="2" style="text-align: center;">APLICA LA TABLA:</th>
                <th class="col-si">SI</th>
                <th class="col-no">NO</th>
                <th></th>
            </tr>
            <tr>
                <th class="col-num">Núm</th>
                <th class="col-prestacion">Prestación</th>
                <th class="col-si">CANTIDAD</th>
                <th class="col-no"></th>
                <th class="col-obs">Observación</th>
            </tr>
        </thead>
        <tbody>
            @php $rowNum = 1; @endphp
            @if($hasNomina)
                <tr>
                    <td class="col-num">{{ $rowNum++ }}</td>
                    <td class="col-prestacion">Nómina quincenal</td>
                    <td class="col-si">${{ number_format($calculatedInstallment, 2) }}</td>
                    <td class="col-no">${{ number_format($totalNominaAmount, 2) }}</td>
                    <td class="col-obs">{{ $nominaFortnights }} parcialidades estimadas</td>
                </tr>
            @endif
            @foreach($loan->loanBenefits as $lb)
                @php
                    $bAmount = (float) ($lb->approved_amount ?? $lb->requested_amount);
                @endphp
                <tr>
                    <td class="col-num">{{ $rowNum++ }}</td>
                    <td class="col-prestacion">{{ $lb->benefit->name ?? 'Prestación' }}</td>
                    <td class="col-si">${{ number_format($bAmount, 2) }}</td>
                    <td class="col-no">${{ number_format($bAmount, 2) }}</td>
                    <td class="col-obs">1 parcialidad ({{ $lb->collection_date?->format('d/m/Y') }})</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Párrafo irrevocabilidad -->
    <p class="body-text">
        He de expresar que estoy consciente que la Ley establece que solo se puede descontar nominalmente al salario hasta un 30% del sueldo neto, más sin embargo autorizo de manera <strong>irrevocable</strong> me sea descontado el porcentaje mayor a lo dictado por Ley, en virtud de que me veo en la necesidad de adquirir un recurso económico con la ayuda de mi Organización Sindical de Trabajadores del Instituto Tecnológico Superior de Macuspana, que de no ser así no podría obtener dicho beneficio y por el cual expreso mi consentimiento al Departamento de Recursos Humanos del Instituto Tecnológico Superior de Macuspana que se aplique el descuento arriba indicado en carácter legitimo e <strong>irrevocable</strong> por convenir así a mis intereses y el de mi Familia, solicitando que cualquier otro documento que mi persona realice o presente posterior a este y que además contenga solicitud de no descuento de este préstamo o cualquier otro adquirido a través del Sindicato Único de Trabajadores del Instituto Tecnológico Superior de Macuspana, en relación a revocar este documento, no se me conceda valor legal alguno y este no sea tomado en cuenta ya que me obligo a respetar ante su persona y el Sindicato único de Trabajadores del Instituto tecnológico Superior de Macuspana y/o quien lo represente, las siguientes cláusulas de irrevocabilidad de este documento:
    </p>

    <!-- Cláusulas Título -->
    <p class="clausulas-title">CLÁUSULAS DE IRREVOCABILIDAD ABSOLUTA</p>

    {{-- Salto de página para la Foja 2 exactamente como el original --}}
    <div style="page-break-before: always;"></div>

    <p class="clausula">
        <strong>PRIMERO:</strong> El presente acuerdo no podrá ser revocado o modificado por ningún documento posterior, como contratos, acuerdos, cartas, oficios o cualquier otro tipo de comunicación que mi persona realice ante el Instituto Tecnológico Superior de Macuspana o ante el Departamento de Recursos Humanos o en a su caso a la Dirección General o cualquier otra área del Instituto.
    </p>

    <p class="clausula">
        <strong>SEGUNDO:</strong> Para efectos de la irrevocabilidad expreso que cualquier intento de revocar o modificar el presente acuerdo mediante un documento posterior será nulo y sin efecto. El presente acuerdo permanecerá en vigor y será ejecutable según los términos que he manifestado en su totalidad.
    </p>

    <p class="clausula">
        <strong>TERCERO:</strong> Ambas partes hemos leído y entendido los términos y condiciones del presente acuerdo y lo aceptamos de manera expresa en su totalidad, por una parte; el Prestamista y por otra parte la Representación de la Caja de Ahorro del Sindicato único de Trabajadores del Instituto Tecnológico Superior de Macuspana al Igual que un testigo de asistencia en la elaboración y firma de dicho documento-convenio.
    </p>

    <!-- Firmas -->
    <div class="firmas-section">
        <!-- Firma 1: Prestador -->
        <div class="firma-block">
            <p class="firma-label">Atentamente</p>
            @if($loan->signature_data)
                <img src="{{ $loan->signature_data }}" class="sig-img" alt="Firma">
            @endif
            <div class="firma-line"></div>
            <p class="firma-desc">{{ $user->full_name }}</p>
            <p class="firma-sub">
                Nombre, Firma y huella del Prestador a la Caja de Ahorro del SINDICATO ÚNICO DE TRABAJADORES DEL INSTITUTO TECNOLÓGICO SUPERIOR DE MACUSPANA.
            </p>
        </div>

        <!-- Firma 2: Otorgante -->
        <div class="firma-block">
            <p class="firma-label">Atentamente</p>
            <div class="firma-line"></div>
            <p class="firma-desc">Representación SIUTITSM</p>
            <p class="firma-sub">
                Nombre, Firma y huella del Otorgante del Préstamo de a la Caja de Ahorro del SINDICATO ÚNICO DE TRABAJADORES DEL INSTITUTO TECNOLÓGICO SUPERIOR DE MACUSPANA.
            </p>
        </div>

        <!-- Firma 3: Testigo -->
        <div class="firma-block">
            <p class="firma-label">TESTIGO</p>
            <div class="firma-line"></div>
            <p class="firma-sub">Nombre, Firma y huella.</p>
        </div>
    </div>

    <!-- CCP -->
    <div class="ccp-section">
        <p>C.C.P. Interesado.</p>
        <p>C.C.P. Sindicato Único de Trabajadores del Instituto Tecnológico Superior de Macuspana.</p>
        <p>C.C.P. Recursos Humanos.</p>
        <p>C.C.P. Archivo.</p>
    </div>
</div>
@endsection
