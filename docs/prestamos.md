# Módulo de Préstamos del Sindicato

## Contexto del sistema existente

Se esta añadiendo un **módulo nuevo** a un sistema Laravel 13 en desarrollo.
Respetar **todas** las convenciones del proyecto sin excepción.


### Roles existentes (de `UserRole.php` — NO modificar ese archivo)
```php
// app\Enums\UserRole.php
case Agremiado = 'agremiado';
case Lider = 'lider';
case Admin = 'admin';
```
- **Solicitan** préstamos: `Agremiado`, `Lider`, `Admin`
- **Revisan / aprueban / validan**: `Admin`, `Lider`


## Convenciones importantes

- Cada prestación tiene asignada una fecha tentativa (día y mes) que se aplica de manera recurrente todos los años.
- El método de pago periódico (nomina) es siempre quincenal. Naturalmente se toma el d ia 15 y ultimo de cada mes. Ej. febrero tiene 28 días por lo que la primer quincena es el dia 15 y la segunda quincena de ese mes es propiamente el dia 28.
- Para el método de pago por nomina la ultima fecha valida es **30 de noviembre** del año en curso, no debe ser posible seleccionar una fecha posterior para cuando el método de pago sea nomina.
- El **30 de noviembre** del año en curso es la fecha limite para solicitar prestamos, después de esa fecha no debe ser posible solicitar un préstamo bajo ninguna prestación o mediante nomina.
- Respetar la tolerancia de 20 días entre la fecha de solicitud del préstamo y la primer quincena disponible, esta tolerancia tiene como objetivo garantizar que los empleados tengan tiempo suficiente para planificar sus pagos y que se pueda hacer el desembolso. Nota. Esta tolerancia en días no aplica para las prestaciones no periódicas.

### Cálculo: Interés simple — Método alemán

Lo siguiente es un ejemplo de cómo se calcula la tabla de amortización quincenal para un préstamo con interés simple alemán, el interés simple alemán solo aplica para préstamos con forma de pago por nomina

**Período inicial (días adicionales)**: Desde el dia de desembolso  hasta la primera fecha de quincena (día 15 o último día del mes):

```
interés días adicionales = saldo insoluto × (tasa anual / 365) × días adicionales
```

**Quincenas regulares**: Desde la primera quincena hasta la última quincena del período de pago.

```
pago capital       = monto aprobado / total quincenas
interés quincenal  = 
pago quincenal     = pago capital + interés quincenal
saldo final        = saldo insoluto − pago capital
```


### Cálculo: Interés compuesto

El monto se cobra de una sola vez en la fecha de la prestación y después de que se termine de pagar los plazos del prestamo por nomina, si no hay pago por nomina la fecha inicia desde la fecha de desembolso hasta la fecha de la prestación.

---

## Fase 1 — Solicitud, aprobación y desembolso

### Flujo 1 — Solicitud

1. El empleado abre el formulario de nueva solicitud.
2. Completa:
   - Monto solicitado.
   - **Forma de pago**: Puede ser solo nomina, solo una prestación, varias prestaciones o mixta (nómina + una o varias prestaciones):
     - `Nómina` -> Opciones desplegable en donde se pueda seleccionar las quincenas disponibles. Es importante mencionar que debe existir una tolerancia al mostrar las quincenas disponibles, la tolerancia debe ser entre la fecha actual y 20 días entre la primer quincena disponible. Ej. La fecha actual es 10/10/2026 + 20 días = 30/10/26 es decir que la primer quincena disponible sera la del 31/10/2026; pero si por ejemplo la fecha actual es 11/10/2026 + 20 días = 31/10/2026 por lo que la primer quincena disponible sera 15/11/2026.
     - `Prestaciones` -> selecciona una o varias entradas de las prestaciones disponibles; la fecha de cobro es la fecha de cada prestación. Nota. No se deben mostrar prestaciones cuya fecha de cobro ya haya pasado, en cambio muestra las prestaciones que
     - Sube un documento (pdf o imagen) con su último recibo de nómina por cada forma de pago (validación: solo PDF e imagen, máximo 5MB).
     - Indica cuanto de su salario desea destinar al pago del préstamo (solo aplica para nómina).
   - Firma la solicitud. El firmado de la solicitud podrá ser de 2 formas (el usuario solo debe elegir una forma): mediante la captura del trazo de la firma (el solicitante dibuja su firma) o usando la e.firma (crear el mecanismo para poder adoptar esta forma de identificación)

### Flujo 2 — Revisión documental (roles: Admin, Líder)

1. Lista filtrable de solicitudes.
2. El revisor puede:
   - **Aprobar**: define el monto aprobado (y monto aprobado por cada prestación), plazo (en caso de nomina) y la tasa de interés. El sistema:
     - Genera: **Pagaré PDF** + **Anuencia de descuento PDF** + **Formato solicitud PDF**.
   - **Rechazar**: guarda e informa el motivo del rechazo.
   - **Lista de espera**.
3. El revisor puede ver el estado de la solicitud.
4. El sistema envía notificación al solicitante con el resultado de la revisión.
5. El revisor puede descargar los documentos.


#### Anuencia de descuento (PDF base)

Este es el ejemplo base para generar el PDF de la anuencia de descuento. Anteriormente se utilizaba una plantilla latte para generar los PDFs.

```latte
{extends ../../@document.latte}


{block content}
    <style>
      .page {
        margin: 0 auto;
        font-family: Arial, sans-serif;
        font-size: 11pt;
        color: #000000;
      }

      /* ── Fecha ── */
      .fecha {
        text-align: right;
        margin-bottom: 18px;
      }

      /* ── Destinatario ── */
      .destinatario {
        margin-bottom: 6px;
      }

      .destinatario p {
        margin: 0;
        font-weight: bold;
        text-transform: uppercase;
      }

      /* ── ATN ── */
      .atencion {
        margin-top: 14px;
        margin-bottom: 14px;
        text-align: center;
      }

      .atencion p {
        margin: 0;
        font-weight: bold;
      }

      .atencion .sub {
        font-weight: bold;
      }

      /* ── Cuerpo ── */
      .body-text {
        text-align: justify;
        margin-bottom: 14px;
        line-height: 1.5;
      }

      /* ── Tabla ── */
      .tabla-aplica {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
        font-size: 10pt;
      }

      .tabla-aplica th,
      .tabla-aplica td {
        border: 1px solid #000000;
        padding: 4px 8px;
        text-align: center;
        vertical-align: top;
      }

      .tabla-aplica th {
        font-weight: bold;
        background-color: #ffffff;
      }

      .tabla-aplica td.col-num {
        width: 6%;
      }

      .tabla-aplica td.col-prestacion {
        width: 40%;
        text-align: left;
      }

      .tabla-aplica td.col-si {
        width: 15%;
      }

      .tabla-aplica td.col-no {
        width: 15%;
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
        margin-top: 18px;
        margin-bottom: 10px;
      }

      .clausula {
        text-align: justify;
        margin-bottom: 10px;
        line-height: 1.5;
      }

      /* ── Sección de firmas ── */
      .firmas-section {
        margin-top: 30px;
      }

      .firma-block {
        margin-bottom: 30px;
      }

      .firma-label {
        font-weight: bold;
        margin-bottom: 40px;
        text-align: center;
      }

      .firma-line {
        border-top: 1px solid #000000;
        width: 80%;
        margin: 0 auto 6px auto;
      }

      .firma-desc {
        text-align: center;
        font-weight: bold;
        font-size: 10pt;
        width: 80%;
        margin: 0 auto;
      }

      /* Firmas en dos columnas */
      .firmas-dos-col {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
      }

      .firmas-dos-col td {
        width: 50%;
        text-align: center;
        vertical-align: top;
        padding: 0 10px;
      }

      .firmas-dos-col .firma-inner-line {
        border-top: 1px solid #000000;
        width: 90%;
        margin: 0 auto 6px auto;
      }

      .firmas-dos-col .firma-inner-desc {
        font-weight: bold;
        font-size: 10pt;
      }

      /* ── CCP ── */
      .ccp-section {
        margin-top: 24px;
      }

      .ccp-section p {
        margin: 2px 0;
        font-weight: bold;
        font-size: 10pt;
      }

      .asunto {
        font-weight: bold;
        text-transform: uppercase;
        margin-bottom: 10px;
        text-align: right;
        width: 100%;
      }
    </style>
  <div class="page">

    <!-- Fecha -->
    <p class="fecha">
      Macuspana, Tabasco a {$approval_date|date: 'd'} de {$approval_date|date: 'F'} de {$approval_date|date: 'Y'}.
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
      <p>AT&#8217;N: LIC. EDUARDO ANTONIO CORNELIO MONTEJO.</p>
      <p class="sub">ENCARGADO DE LA SUBDIRECCIÓN DE ADMINISTRACIÓN Y FINANZAS DEL ITSM.</p>
    </div>

    <!-- Párrafo principal -->
    <p class="body-text">
      Quien suscribe {$borrower_name}
      y de conformidad en lo establecido por el Artículo 110 fracción IV de la Ley Federal del Trabajo,
      y por medio del presente tengo a bien solicitarle se me descuente de mi nómina quincenal la cantidad de
      {$installment_amount_label}
      y a partir de la fecha {$first_payment_date} hasta pagar en su totalidad el presente adeudo que he contraído
      de manera voluntaria con la caja de ahorro de los Agremiados del Sindicato Único de Trabajadores del Instituto
      Tecnológico Superior de Macuspana por la cantidad total de este préstamo de {$approved_amount_label}
      más el {$interest_rate}% de interés de manera mensual y de ser necesario se me descuente de las otras prestaciones
      dentro del año o de las prestaciones de fin de año de acuerdo a lo listado en la siguiente tabla, al igual
      que los otros posibles prestamos que pueda contraer de acuerdo a mi capacidad de pago con el SIUTITSM
      y que sean referenciados de esta misma forma.
    </p>

    <!-- Tabla -->
    <table class="tabla-aplica">
      <tr>
        <th colspan="2" style="text-align: center;">APLICA LA TABLA</th>
        <th class="col-si"></th>
        <th class="col-no"></th>
        <th></th>
      </tr>
      <tr>
        <th class="col-num">Núm</th>
        <th class="col-prestacion">Prestación</th>
        <th class="col-si">CANTIDAD</th>
        <th class="col-no"></th>
        <th class="col-obs"><strong>Observación</strong></th>
      </tr>
      {if count($payment_configs) > 0}
        {foreach $payment_configs as $index => $config}
          <tr>
            <td class="col-num">{$index + 1}</td>
            <td class="col-prestacion">{$config['income_type_name']}</td>
            <td class="col-si">{$config['installment_amount_label']}</td>
            <td class="col-no">{$config['total_amount_label']}</td>
            <td class="col-obs">{$config['number_of_installments']} parcialidades</td>
          </tr>
        {/foreach}
      {else}
        <tr>
          <td class="col-num">1</td>
          <td class="col-prestacion">Nómina quincenal</td>
          <td class="col-si">{$installment_amount_label}</td>
          <td class="col-no">{$approved_amount_label}</td>
          <td class="col-obs">{$term_fortnights} parcialidades estimadas</td>
        </tr>
      {/if}
    </table>

    <!-- Párrafo irrevocabilidad -->
    <p class="body-text">
      He de expresar que estoy consciente que la Ley establece que solo se puede descontar nominalmente al
      salario hasta un 30% del sueldo neto, más sin embargo autorizo de manera <strong>irrevocable</strong> me sea
      descontado el porcentaje mayor a lo dictado por Ley, en virtud de que me veo en la necesidad de adquirir
      un recurso económico con la ayuda de mi Organización Sindical de Trabajadores del Instituto Tecnológico
      Superior de Macuspana, que de no ser así no podría obtener dicho beneficio y por el cual expreso mi
      consentimiento al Departamento de Recursos Humanos del Instituto Tecnológico Superior de Macuspana que
      se aplique el descuento arriba indicado en carácter legitimo e <strong>irrevocable</strong> por convenir así a mis
      intereses y el de mi Familia, solicitando que cualquier otro documento que mi persona realice o presente
      posterior a este y que además contenga solicitud de no descuento de este préstamo o cualquier otro adquirido
      a través del Sindicato Único de Trabajadores del Instituto Tecnológico Superior de Macuspana, en relación
      a revocar este documento, no se me conceda valor legal alguno y este no sea tomado en cuenta ya que me
      obligo a respetar ante su persona y el Sindicato único de Trabajadores del Instituto tecnológico Superior
      de Macuspana y/o quien lo represente, las siguientes cláusulas de irrevocabilidad de este documento:
    </p>

    <!-- Cláusulas -->
    <p class="clausulas-title">CLÁUSULAS DE IRREVOCABILIDAD ABSOLUTA</p>

    <p class="clausula">
      <strong>PRIMERO:</strong> El presente acuerdo no podrá ser revocado o modificado por ningún documento posterior,
      como contratos, acuerdos, cartas, oficios o cualquier otro tipo de comunicación que mi persona realice ante
      el Instituto Tecnológico Superior de Macuspana o ante el Departamento de Recursos Humanos o en a su caso
      a la Dirección General o cualquier otra área del Instituto.
    </p>

    <p class="clausula">
      <strong>SEGUNDO:</strong> Para efectos de la irrevocabilidad expreso que cualquier intento de revocar o modificar
      el presente acuerdo mediante un documento posterior será nulo y sin efecto. El presente acuerdo permanecerá
      en vigor y será ejecutable según los términos que he manifestado en su totalidad.
    </p>

    <p class="clausula">
      <strong>TERCERO:</strong> Ambas partes hemos leído y entendido los términos y condiciones del presente acuerdo
      y lo aceptamos de manera expresa en su totalidad, por una parte; el Prestamista y por otra parte la
      Representación de la Caja de Ahorro del Sindicato único de Trabajadores del Instituto Tecnológico Superior
      de Macuspana al Igual que un testigo de asistencia en la elaboración y firma de dicho documento-convenio.
    </p>

    <!-- Firmas -->
    <div class="firmas-section">

      <!-- Firma 1: Prestador -->
      <div class="firma-block">
        <p class="firma-label">Atentamente</p>
        <div class="firma-line"></div>
        <p class="firma-desc">{$borrower_name}</p>
        <p class="firma-desc">
          Nombre, Firma y huella del Prestador a la Caja de Ahorro del SINDICATO ÚNICO DE TRABAJADORES
          DEL INSTITUTO TECNOLÓGICO SUPERIOR DE MACUSPANA.
        </p>
      </div>

      <!-- Firma 2: Otorgante -->
      <div class="firma-block">
        <p class="firma-label">Atentamente</p>
        <div class="firma-line"></div>
        <p class="firma-desc">Representación SIUTITSM</p>
        <p class="firma-desc">
          Nombre, Firma y huella del Otorgante del Préstamo de a la Caja de Ahorro del SINDICATO ÚNICO
          DE TRABAJADORES DEL INSTITUTO TECNOLÓGICO SUPERIOR DE MACUSPANA.
        </p>
      </div>

      <!-- Firma 3: Testigo (centrado) -->
      <div class="firma-block">
        <p class="firma-label" style="text-align:center;">TESTIGO</p>
        <div class="firma-line"></div>
        <p class="firma-desc">Nombre, Firma y huella.</p>
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
{/block}
```



#### Pagare (PDF)

Este es el ejemplo base para generar el PDF del pagare. Anteriormente se utilizaba una plantilla latte para generar los PDFs.

```latte
<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport"
        content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>PAGARE</title>
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
    }
  </style>
</head>
<body class="container-main">

<div class="container-children">
  <div class="hijo">
    <style>
      .pagare {
        border: 2px solid black;
        padding: 8px 15px;
        margin: 20px;
        border-radius: 10px;
        position: relative;

        /* fondo verde suave similar a la foto */
        background-color: #eaf6ea;
      }

      .title-box {
        text-align: center;
        border-radius: 10px;
        padding: 10px 40px;
        font-size: 20px;
        background-color: black;
        color: white;
        letter-spacing: 8px;
        font-style: italic;
        font-weight: bold;
      }

      .between {
        text-align: justify;
      }

      .between:after {
        content: "";
        display: inline-block;
        width: 100%;
      }

      .between > * {
        display: inline-block;
        vertical-align: top;
      }

      .underline {
        border-bottom: 1px solid #aea9a9;
        min-width: 100px;
        padding: 0 5px;
      }

      .content {
        margin: 8px 0;
        text-align: justify;
        word-wrap: break-word;
      }

      .header {
        font-size: 13px;
      }

      .header p {
        margin: 5px 0;
      }

      .table-content {
        display: table;
        width: 100%;
        border-spacing: 0;
      }

      .cell-underline {
        border-bottom: 1px solid #aea9a9;
        display: table-cell;
      }

      .cell {
        display: table-cell;
      }

      .justify {
        text-align: justify;
        width: 100%;
      }

      .bold {
        font-weight: bold;
      }

      .content p {
        margin-top: 5px;
      }

      .box {
        border: 1px solid #aea9a9;
        padding: 10px;
        border-radius: 10px;
      }

      .footer-table {
        width: 100%;
        border-spacing: 0;
        margin-top: 20px;
      }

      .footer-table__column {
        width: 50%;
      }

      .text-center {
        text-align: center;
      }

      .table {
        width: 100%;
        border-spacing: 0;
      }

      .text-sm {
        font-size: 10px;
      }

      .border {
        border: 1px solid #aea9a9;
        border-radius: 10px;
        padding: 5px;
      }

      .cell-space {
        padding: 5px;
      }
    </style>

    <section class="pagare">
      <div class="between">
        <h1 class="title-box">
          Pagaré
        </h1>

        <div class="header">
          <p>
            No. {$loan_id}. Bueno por {$approved_amount_label}
          </p>
          <p>
            En Macuspana, Tabasco a {$approval_date_long}.
          </p>
        </div>

      </div>


      <div class="content">
        <p class="justify">
          Debe(mos) y pagare(mos) incondicionalmente por este Pagaré a la orden de
          <span class="bold underline"> Sindicato Único de Trabajadores de Instituto Tecnológico Superior de
        Macuspana</span>
          en <span class="underline bold">Macuspana, Tabasco</span> el <span class="underline" style="padding: 0 100px">{$last_payment_date}</span>
        </p>
        <p>
          La cantidad de:
        </p>
        <p class="box">
          {$approved_amount_words}
        </p>
        <p>
          Valor recibido a mi (nuestra) entera satisfacción. Este pagaré forma parte de una serie numerada de 1 al <span
                  class="underline" style="padding: 0 20px">{$term_fortnights}</span> y todos están sujetos a la condición de que, al no
          pagarse
          cualquiera de ellos a su vencimiento, serán exigibles todos los que le sigan en número, además de los ya
          vencidos,
          desde la fecha de vencimiento de este documento hasta el día de su liquidación, causará intereses moratorios
          al
          tipo de <span class="underline" style="padding: 0 20px">{$interest_rate}</span>% mensual, pagadero en esta ciudad juntamente
          con
          el
          principal.
        </p>
      </div>

      <table class="table">
        <tr>
          <td class="footer-table__column">
            <table class="table border">
              <tr>
                <td class="text-center text-sm">
                  Datos del deudor
                </td>
              </tr>
              <tr>
                <td class="cell-underline">
                  Nombre: {$borrower_name}
                </td>
              </tr>
              <tr>
                <td class="cell-underline">
                  Dirección: {$borrower_address}
                </td>
              </tr>
              <tr>
                <td class="cell-underline">
                  Teléfono: {$borrower_phone}
                </td>
              </tr>
            </table>
          </td>
          <td class="cell-space"></td>
          <td class="footer-table__column">
            <table class="table">
              <tr>
                <td class="bold">
                  Acepto(amos)
                </td>
              </tr>
              <tr>
                <td class="cell-space"></td>
              </tr>
              <tr>
                <td class="cell-underline cell-space">{$borrower_name}</td>
              </tr>
              <tr>
                <td class="text-center">
                  Firma(s)
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </section>
  </div>
</div>

</body>

</html>
```

#### Formato de Solicitud (PDF)

Este es el ejemplo base para generar el PDF del formato de solicitud.

```latte
{extends ../../@document.latte}

{block content}
  <style>
    .justify-between {
      text-align: justify;
      font-size: 0;

    }

    .justify-between:after {
      content: "";
      display: inline-block;
      width: 100%;
    }

    .justify-between > * {
      display: inline-block;
      vertical-align: top;
    }

    /* Ensure tables remain tables (avoid forcing table elements to inline-block) */
    .justify-between > table {
      display: table !important;
      vertical-align: top;
      width: 100%;
    }

    .stamp-header strong {
      display: inline-block;
      font-size: 12px;
      vertical-align: top;
    }


    .title {
      text-align: center;
      border-radius: 10px;
      padding: 5px;
      margin: 10px 0;
      font-size: 20px;
    }

    .container {
      font-size: 14px;
      padding: 5px 10px;
      position: relative;
    }

    .data p {
      margin: 5px 0;
    }

    .data ul > li {
      padding: 5px;
    }

    .table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }

    .table th,
    .table td {
      border: 1px solid black;
      padding: 5px;
      text-align: left;
    }

    .box {
      border: 1px solid black;
      margin-top: 10px;
      margin-bottom: 10px;
      width: 100%;
      padding: 10px;
      min-height: 100px
    }

    .border {
      border: 1px solid black;
      margin-top: 10px;
      margin-bottom: 10px;
      width: 100%;
    }

    .anexo {
      margin: 10px 0;
      font-size: 10px;
    }

    .anexo ul {
      list-style: none;
      padding-left: 10px;
    }

    .anexo p {
      margin-top: 5px;
    }

    .square {
      display: inline-block;
      width: 12px;
      height: 12px;
      border: 1px solid black;
      margin-left: 5px;
    }

    .signature-section {
      width: 100%;
      margin-top: 25px;
    }

    .signature-section {
      width: 100%;
      margin-top: 25px;
    }

    .signature-section table {
      width: 100%;
      border-collapse: collapse;
      text-align: center;
    }

    .signature-section td {
      padding: 5px;
      vertical-align: top;
    }

    .signature-section__center {
      width: 70%;
    }

    .signature-section__right-left {
      width: 15%;
    }

    .huella {
      border: 1px solid black;
      width: 100px;
      height: 150px;
      margin: 0 auto;
    }

    .huella p {
      font-size: 9px;
      text-align: center;
      margin-top: 5px;
    }

    .signature {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }

    .signature td {
      padding: 4px;
      text-align: center;
      vertical-align: middle;
      word-wrap: break-word;
    }

    .signature-row-auth td {
      width: 50%;
      vertical-align: top;
    }

    .signature-line {
      border-bottom: 1px solid black;
      margin-top: 40px;
      vertical-align: bottom;
      margin-left: 5px;
      margin-right: 5px;
    }


    .ccp {
      font-size: 8px;
      margin-top: 20px;
      font-style: italic;
    }
  </style>
  <section class="justify-between stamp-header">
    <strong>Préstamo No. {$loan_id}</strong>
    <strong>
      Fecha de otorgamiento del préstamo: {$approval_date}
    </strong>
  </section>

  <h1 class="bg-primary text-bg-primary title">
    SOLICITUD DE PRÉSTAMO A LA CAJA DE AHORRO {date('Y-m-d')|date: 'Y'}
  </h1>

  <section class="container data">
    <p>
      <strong>
        Nombre del que Solicita el préstamo:
      </strong>
      <span>
      {$borrower_name}
    </span>
    </p>
    <p>
      <strong>
        Monto Solicitado del Préstamo:
      </strong>
      <span>
      {$approved_amount_label}
    </span>
    </p>
    <p>
      <strong>
        Dia en que solicita el préstamo:
      </strong>
      <span>
      {$application_date}
    </span>
    </p>
    <p>
      <strong>
        Forma de pago en la que acepta se le aplique el descuento del préstamo solicitado; en caso de ser
        aprobado:
      </strong>
    </p>

    <ul>
      <li>Descuento en parcialidades estimadas de {$installment_amount_label}</li>
      <li>Total de configuraciones de descuento: {$payment_config_count}</li>
    </ul>
  </section>

  <section class="container justify-between">
    <p>
      <strong>Categoria:</strong>
      <span>
      {$borrower_category}
    </span>
    </p>
    <p>
      <strong>Teléfono:</strong>
      <span>{$borrower_phone}</span>
    </p>
  </section>
  <section class="container justify-between">
    <div>
      <p>
        <strong>N. DE CUENTA Y/O CLABE INTERBANCARIA:</strong>
      </p>
      <p>
        {$borrower_interbank_code}
      </p>
    </div>
    <div>
      <p>
        <strong>BANCO:</strong>
      </p>
      <p>
        {$borrower_bank_name}
      </p>
    </div>


    <table class="table">
      <thead>
      <tr>
        <th>
          N. Pagare
        </th>
        <th>
          Monto Pagare
        </th>
        <th>
          Interés
        </th>
        <th>
          Fecha
        </th>
        <th>
          Otros
        </th>
        <th>
          Nombre y firma del trabajador
        </th>
      </tr>
      </thead>
      <tbody>
      <tr>
        <td>{$loan_id}</td>
        <td>{$approved_amount_label}</td>
        <td>{$interest_rate_label}</td>
        <td>{$first_payment_date}</td>
        <td>Plazo: {$term_fortnights} quincenas</td>
        <td>{$borrower_name}</td>
      </tr>
      </tbody>
    </table>

    <table class="box">
      <tr>
        <td>
          Cuenta de depósito: {$borrower_bank_account}
        </td>
      </tr>
    </table>

    <p>
      En base a lo anterior doy mi autorización para que se me descuente este préstamo con los intereses generados
      respectivos aplicados a mi sueldo quincenal y/o a las prestaciones de fin de año, bajo los términos de esta
      solicitud la cual fue requisitada por mi persona y como Trabajador del Instituto Tecnológico Superior de
      Macuspana, estando consiente y expresando mi voluntad que estos descuentos disminuirán mis alcenses netos de
      salario y el de mis prestaciones que percibo en caso de que haya afectado este concepto, haciendo énfasis y
      puntualizando que ningún otro escrito posterior al presente que se genere por mi persona, la Subdirección de
      Administración, o la Dirección General del Instituto Tecnológico Superior de Macuspana, impedirá mi voluntad de
      que se realice este descuento, ya que tiene la potestad de ser <strong>irrevocable</strong> a cualquier otro
      documento emitido por
      mi persona de manera posterior, ante el departamento de Recursos Humanos, el cual he manifestado con base a que
      fue un recurso que solicite con anticipación a la Caja de Ahorro del Sindicato Único de Trabajadores del Instituto
      Tecnológico Superior de Macuspana y que de buena fé me otorgaron dejando en garantía mi sueldo y/o Prestaciones y
      firmando la corrida tabular de amortizaciones a la cual me comprometí pagar.
      El sindicato se obliga a dejarme un porcentaje en mi salario al igual que un servidor es obliga a no disponer de
      lo aquí acordado en mi nomina respectiva.
      En caso de cambiar mi situación de pago a la caja de ahorro acepto se me cobre 1% más del interés ordinario
      mensual.
      Acepto que solamente tendré una oportunidad de hacer cambio de mi situación de cobro tomando en cuenta el punto
      número 2.
      En caso de hacer algún pago anticipado en efectivo éste será depositado al banco a la cuenta de la caja de ahorro
      y solamente entregaré la ficha de depósito de la cantidad abonada al representante de la caja de ahorro.
      Acepto que no podré hacer cambios en el Departamento de Personal ni en la Subdirección de Administración y
      Finanzas ni en la Dirección General del Instituto Tecnológico superior de Macuspana a este préstamo otorgado para
      evitar mi incumplimiento al pago del mismo; a excepción se haya liquidado con anterioridad y un servidor junto con
      el sindicato lo expresemos por escrito al área correspondiente del ITSM.
      En caso de incumplir con alguna de estas cláusulas el sindicato queda libre de hacer cualquier proceso legal
      correspondiente.
    </p>

    <div class="anexo">
      <strong>
        Interés de préstamos de la caja de ahorro de {date('Y-m-d')|date: 'Y'}
      </strong>
      <ul>
        <li>
          Ahorrador y agremiado 6.0%
        </li>
        <li>
          Agremiado y no ahorrador 7.5%
        </li>
        <li>
          Ahorrador y no agremiado 8.5%
        </li>
        <li>
          No ahorrador y no agremiado 9.5%
        </li>
      </ul>

      <p>
        <strong>NOTA: </strong>Cualquier cambio de lo arriba solicitado, favor de pasar a la oficina Sindical para
        modificar o atender el asunto a tratar, Todos los préstamos solicitados son vía nómina.
      </p>

      <p>
        Mucho agradeceremos su comprensión al respecto. Ayúdenos a darles un buen servicio. Gracias.
      </p>

      <table>
        <tr>
          <td>Préstamo sujeto a revisión:</td>
          <td>
            <table class="border">
              <tr>
                <td><span>SI</span> <span class="square"></span></td>
                <td><span>NO</span> <span class="square"></span></td>
              </tr>
              <tr>
                <td style="border-top: 1px solid black; text-align: center" colspan="2">
                  <strong>APROBACIÓN</strong>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </div>

    <p>
      Se anexa corrida de amortización de descuento, copia de mi recibo de pago Nominales/Prestaciones reciente y copia
      de la credencial de elector o (INE) y Pagaré en caso de hacer aprobada esta solicitud.
    </p>

    <div class="signature-section">
      <table>
        <tr>
          <td class="signature-section__right-left"></td>
          <td class="signature-section__center">
            <table class="signature">
              <tr>
                <td colspan="2"><strong>ATENTAMENTE</strong></td>
              </tr>
              <tr>
                <td colspan="2">
                  <div class="signature-line">
                    {$borrower_name}
                  </div>
                </td>
              </tr>
              <tr>
                <td colspan="2">Nombre y firma del solicitante</td>
              </tr>
              <tr>
                <td colspan="2"><strong>Vo. Bo.</strong></td>
              </tr>
              <tr class="signature-row-auth">
                <td>
                  <strong>AUTORIZO</strong>
                  <div class="signature-line"></div>
                </td>
                <td>
                  <strong>SUPERVISO</strong>
                  <div class="signature-line"></div>
                </td>
              </tr>
              <tr class="signature-row-auth">
                <td>MIDS Luiz Sosa Castro</td>
                <td>Ing. Jesus Antonio Lopez Hernandez</td>
              </tr>
              <tr class="signature-row-auth">
                <td><strong>Secretario General</strong></td>
                <td><strong>Secretario de Finanzas</strong></td>
              </tr>
            </table>
          </td>
          <td class="signature-section__right-left">
            <div class="huella">
              <p>Huella del Trabajador</p>
            </div>
          </td>
        </tr>
      </table>
    </div>
  </section>

  <div class="ccp">
    <p>c.c.p Interesado</p>
    <p>c.c.p Sindicato Único de Trabajadores del Instituto Tecnológico Superior de Macuspana</p>
    <p>c.c.p Departamento de Personal, para su conocimiento</p>
  </div>

{/block}
```

### Flujo 3 — Desembolso (roles: Admin, Lider)

1. El revisor registra la fecha de desembolso por el monto aprobado.
2. El sistema genera automáticamente la tabla de amortización quincenal y la corrida financiera (PDF).


---

## Fase 2 — Seguimiento, pagos y casos especiales

### Seguimiento (todos los roles con acceso al préstamo)

1. El empleado ve su corrida financiera activa en sus prestamos.
2. Ve próxima quincena y saldo restante.
3. Puede solicitar liquidación anticipada (activa Flujo 6c).
4. Puede ver su **estado de cuenta** (todos sus préstamos: activos, liquidados, etc).


### Registro de pagos regulares (roles: Admin, Lider)

Crear un mecanismo para registrar pagos de quincenas, ya sea de forma manual o mediante un archivo CSV.


### Casos especiales

#### a. Pico (quincena no pagada)
- La quincena pasa a no pagada.
- Se calculan los días de atraso y el interés moratorio correspondiente.
- Se regenera la tabla desde esa quincena: suma el interés moratorio al saldo insoluto y recalcula las cuotas restantes.
- Genera un comprobante de cargo moratorio.

#### b. Pago anticipado / abono a capital
- Se registra.
- Aplicación: primero a intereses moratorios pendientes, luego a capital.
- Genera comprobante.

#### c. Liquidación total
- Calcula el saldo exacto al día (interés devengado hasta hoy).
- Genera comprobante.

#### d. Reestructuración
- Cierra el préstamo original.
- Crea nuevo registro.
- Registra la reestructuración (saldo insoluto, interés pendiente, interés moratorio, nuevo monto, nueva tasa, nuevo plazo).
- El nuevo préstamo entra al **Flujo 2** para aprobación.
- Genera comprobante.


## Tasas de interés


| Tipo | Tasa mensual |
|---|---|
| `Agremiado` + ahorrador | 6 % |
| `Agremiado` + no ahorrador | 7.5 % |
| `NoAgremiado` + ahorrador | 8 % |
| `NoAgremiado` + no ahorrador | 9.5 % |

> El revisor puede sobreescribir la tasa de interés libremente al aprobar. El sistema guarda siempre la tasa pactada; no se recalcula automáticamente. Los valores aqui descritos son solo de referencia para el revisor.
