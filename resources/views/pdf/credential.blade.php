<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
        @page { margin: 0cm; }

        :root {
            --card-primary: #2E3A6B;
            --card-secondary: #E8621A;
            --card-text: #2E3A6B;
            --card-muted: #3B4A7A;
            --card-border: #E4E8F2;
            --card-surface: #FFFFFF;
            --card-bg: #FFFFFF;
        }

        body {
            font-family: Helvetica, sans-serif;
            display: flex;
            justify-content: center;
            padding-top: 2cm;
            color: var(--card-text);
        }

        .print-container {
            width: 100%;
            text-align: center;
        }

        .card-wrapper {
            display: inline-block;
            margin: 10px;
            vertical-align: top;
        }

        .card {
            width: 54mm;
            height: 85.6mm;
            background: var(--card-surface);
            position: relative;
            overflow: hidden;
            border: 0.5pt dashed var(--card-border);
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
            text-align: center;
        }

        .arc-blue {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 35mm;
            background-size: cover;
            background-position: center top;
            background-repeat: no-repeat;
            z-index: 1;
        }

        .arc-red {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 35mm;
            background-size: cover;
            background-position: center bottom;
            background-repeat: no-repeat;
            transform: scaleY(-1) scaleX(-1);
            z-index: 2;
        }

        .content {
            position: relative;
            z-index: 10;
            padding: 5mm 3mm;
            height: 100%;
        }

        .content-front {
            padding: 0 3mm;
        }

        .front-main {
            padding-top: 12mm;
        }

        .front-footer {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            padding: 0 2mm;
        }

        .photo {
            width: 18mm;
            height: 18mm;
            margin: 0 auto 2mm;
            border: 0.8mm solid var(--card-primary);
            border-radius: 50%;
            overflow: hidden;
            background-color: var(--card-surface);
            position: relative;
        }

        .photo img {
            width: 100%;
            height: 100%;
            display: block;
            min-width: 100%;
            min-height: 100%;
        }

        .photo-fallback {
            font-size: 6pt;
            color: var(--card-muted);
            line-height: 18mm;
            margin: 0;
        }

        .name {
            color: var(--card-primary);
            font-size: 10pt;
            font-weight: bold;
            margin: 0 0 0.5mm;
            text-transform: uppercase;
            line-height: 1.05;
        }

        .role {
            font-size: 6.8pt;
            color: var(--card-muted);
            margin: 0 0 1.5mm;
            text-transform: uppercase;
            line-height: 1.1;
        }

        .qr-pill {
            width: 29mm;
            height: 7.4mm;
            margin: 0 auto 1mm;
            padding: 0.8mm;
            border-radius: 2.5mm;
            background: var(--card-primary);
        }

        .qr-pill img {
            width: 7.4mm;
            height: 7.4mm;
            display: block;
            margin: 0 auto;
            background: var(--card-surface);
        }

        .qr-fallback {
            margin: 0;
            font-size: 5pt;
            color: var(--card-surface);
            line-height: 7.4mm;
        }

        .id-info {
            font-size: 6.4pt;
            font-weight: bold;
            margin-bottom: 1.5mm;
            line-height: 1.2;
            color: var(--card-text);
            text-transform: uppercase;
        }

        .membership {
            color: var(--card-text);
            line-height: 1.15;
        }

        .membership-lead {
            font-size: 5.8pt;
            font-weight: normal;
            text-transform: uppercase;
            margin-bottom: 0.4mm;
        }

        .membership-org {
            font-size: 6.2pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.15;
            margin-bottom: 1mm;
        }

        .membership-reg {
            font-size: 6.4pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .web {
            width: 100%;
            font-size: 6.2pt;
            color: var(--card-primary);
            font-weight: bold;
            margin-bottom: 1mm;
        }

        .logo-row {
            display: table;
            width: 100%;
            margin: 0;
        }

        .logo-cell {
            display: table-cell;
            width: 33.33%;
            height: 11mm;
            text-align: center;
            vertical-align: bottom;
        }

        .logo-img {
            max-width: 12mm;
            max-height: 9mm;
            object-fit: contain;
        }

        .logo-img-itsm {
            max-width: 17mm;
            max-height: 11mm;
            border-radius: 50%;
            object-fit: cover;
        }

        .content-back {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding-bottom: 16mm;
        }

        .back-qr-wrap {
            margin: 0 auto 2mm;
            text-align: center;
        }

        .back-qr-label {
            margin: 0 0 0.8mm;
            font-size: 4.8pt;
            color: var(--card-muted);
            text-transform: uppercase;
            letter-spacing: 0.2pt;
        }

        .back-title {
            font-size: 11pt;
            font-weight: bold;
            color: var(--card-primary);
            margin-top: 0;
            margin-bottom: 3mm;
        }

        .back-text {
            font-size: 6.1pt;
            text-align: justify;
            line-height: 1.2;
            margin-top: 10mm;
            margin-bottom: 2mm;
            padding: 0 4mm;
            color: var(--card-text);
        }

        .qr {
            width: 13mm;
            height: 13mm;
            margin: 0 auto;
            border: 0.25pt solid var(--card-border);
            background: var(--card-surface);
            overflow: hidden;
        }

        .qr img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .back-qr-fallback {
            margin: 0;
            font-size: 5pt;
            color: var(--card-muted);
            line-height: 13mm;
        }

        .v-box {
            border: 0.3mm solid var(--card-border);
            border-radius: 2mm;
            padding: 1.5mm;
            width: 70%;
            margin: 0 auto 2mm;
            background: var(--card-surface);
        }

        .v-label {
            margin: 0;
            font-size: 5pt;
            color: var(--card-muted);
        }

        .v-value {
            margin: 0;
            font-size: 9pt;
            font-weight: bold;
            color: var(--card-primary);
        }

        .sign-area {
            margin-top: 1mm;
            background: var(--card-surface);
            border-radius: 2mm;
            padding: 1mm 1mm 0.8mm;
            border: 0.25pt solid var(--card-border);
        }

        .sign-name {
            font-size: 7.5pt;
            font-weight: bold;
            margin: 0;
            color: var(--card-text);
        }

        .sign-role {
            font-size: 5.5pt;
            margin: 0;
            color: var(--card-muted);
        }

        .hash {
            font-size: 3.5pt;
            color: var(--card-muted);
            margin-top: 2pt;
            word-break: break-all;
            padding: 0 5mm;
            line-height: 1.15;
        }

        .meta {
            margin-top: 1mm;
            font-size: 4.8pt;
            color: var(--card-text);
            line-height: 1.2;
            padding: 0.8mm 1.4mm;
            border-radius: 1.6mm;
            background: var(--card-surface);
            border: 0.25pt solid var(--card-border);
        }
    </style>
</head>
<body>
<div class="print-container">
<p style="font-size: 10pt; color: #3B4A7A; margin-bottom: 1cm;">Guia de impresion: recorte por la linea punteada.</p>
<div class="card-wrapper">
<div class="card">
            @if (!empty($backgroundImage))
            <div class="arc-blue" style="background-image: url('{!! $backgroundImage !!}');"></div>
            <div class="arc-red" style="background-image: url('{!! $backgroundImage !!}');"></div>
            @else
            <div class="arc-blue"></div>
            <div class="arc-red"></div>
            @endif
            <div class="content content-front">
<div class="front-main">
<div class="photo">
                        @if (!empty($holder['photoSrc']))
                            <img src="{!! $holder['photoSrc'] !!}" alt="Foto">
                        @else
                            <p class="photo-fallback">SIN FOTO</p>
                        @endif
                    </div>
<div class="name">{{ $holder['fullName'] }}</div>
<div class="role">{{ $holder['cargo'] }}</div>
<div class="qr-pill">
                        @if (!empty($qrSrc))
                            <img src="{!! $qrSrc !!}" alt="QR">
                        @else
                            <p class="qr-fallback">SIN QR</p>
                        @endif
                    </div>
<div class="id-info">NO. IMSS {{ $holder['imss'] }}</div>
<div class="membership">
<div class="membership-lead">MIEMBRO ACTIVO DEL</div>
{{-- ponytail: saltos fijos para replicar el PDF original; usar $organization si cambia la razón social --}}
<div class="membership-org">
                            SINDICATO ÚNICO DE TRABAJADORES<br>
                            DEL INSTITUTO TECNOLÓGICO<br>
                            SUPERIOR DE MACUSPANA.
                        </div>
<div class="membership-reg">REG. RS-033/2019.</div>
</div>
</div>
<div class="front-footer">
<div class="web">{{ $website }}</div>
<div class="logo-row">
<div class="logo-cell">
                            @if (!empty($logos['tecnm']))
                                <img src="{!! $logos['tecnm'] !!}" alt="TecNM" class="logo-img">
                            @endif
                        </div>
<div class="logo-cell">
                            @if (!empty($logos['itsm']))
                                <img src="{!! $logos['itsm'] !!}" alt="ITSM" class="logo-img-itsm">
                            @endif
                        </div>
<div class="logo-cell">
                            @if (!empty($logos['siut']))
                                <img src="{!! $logos['siut'] !!}" alt="SIUT" class="logo-img">
                            @endif
                        </div>
</div>
</div>
</div>
</div>
<p style="font-size: 8pt; color: #3B4A7A;">Anverso</p>
</div>
<div class="card-wrapper">
<div class="card">
            @if (!empty($backgroundImage))
            <div class="arc-blue" style="background-image: url('{!! $backgroundImage !!}');"></div>
            <div class="arc-red" style="background-image: url('{!! $backgroundImage !!}');"></div>
            @else
            <div class="arc-blue"></div>
            <div class="arc-red"></div>
            @endif
            <div class="content content-back">
<div class="back-text">
Se acredita que la persona titular de esta
credencial pertenece al padron de
afiliados del
                    <strong>{{ $organization }}</strong>.
                </div>
<div class="v-box">
<p class="v-value">{{ $holder['vigencia'] }}</p>
</div>
<div class="back-qr-wrap">
<p class="back-qr-label">Validacion en linea</p>
<div class="qr">
                        @if (!empty($qrSrc))
                            <img src="{!! $qrSrc !!}" alt="QR">
                        @else
                            <p class="back-qr-fallback">SIN QR</p>
                        @endif
                    </div>
</div>
<div class="sign-area">
<p class="sign-name">{{ $signatory['name'] }}</p>
<p class="sign-role">{{ $signatory['role'] }}</p>
<div class="hash">Firma digital: {{ $signatory['signatureHash'] }}</div>
</div>
<div class="meta">
                    {{ $address }}<br>
                    RFC: {{ $rfc }} <br>
                    Emision: {{ $issuedAt }}<br>
</div>
</div>
</div>
<p style="font-size: 8pt; color: #3B4A7A;">Reverso</p>
</div>
</div>
</body>
</html>
