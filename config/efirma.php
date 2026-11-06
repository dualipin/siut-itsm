<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Directorio de certificados de las AC del SAT
    |--------------------------------------------------------------------------
    |
    | Archivos .cer/.crt (DER o PEM) usados como ancla de confianza para
    | validar la cadena del certificado de e.firma.
    |
    */
    'ca_path' => env('EFIRMA_CA_PATH', storage_path('app/sat-ca')),

    /*
    |--------------------------------------------------------------------------
    | Tolerancia del sello de tiempo (segundos)
    |--------------------------------------------------------------------------
    |
    | Diferencia maxima aceptada entre la hora declarada por el navegador en la
    | cadena original y la hora del servidor.
    |
    */
    'clock_tolerance' => (int) env('EFIRMA_CLOCK_TOLERANCE', 900),

];
