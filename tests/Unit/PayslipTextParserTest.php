<?php

use App\Support\PayslipTextParser;

function itsmPayslipText(): string
{
    return <<<'TXT'
Comprobante Fiscal Digital por Internet
INSTITUTO TECNOLOGICO SUPERIOR DE MACUSPANA Fecha: 22/Oct/2026
RFC: ITS001130EA1 Reg Pat: E7912372108 Hora: 11:11:09
D0054 - ALEXANDER RAMIREZ ARCOS Ejercicio: 2026
RFC: RAAA8009045Y3 Periodo: 19 04 Quincenal 01/Oct/2026 - 15/Oct/2026
CURP: RAAA800904HTCMRL06 Días de Pago: 15.000
Fecha Ini Relación Lab: 15/Ago/2009 Fecha Pago: 14/Oct/2026
Jornada: 01 Diurna Puesto: DOCENTE
NSS: 83098002666 Depto: DIVISION DE ING EN GESTION
Tipo salario: Fijo SBC: $ 1,244.23
Domicilio Fiscal: 86700 Régimen fiscal: 605 Sueldos y Salarios
Percepciones Deducciones
Subtotal $ 17,269.05 Neto del recibo $ 13,741.55
TXT;
}

test('it reads every registration field from a payroll CFDI text layer', function () {
    $fields = PayslipTextParser::parse(itsmPayslipText());

    expect($fields)->toMatchArray([
        'nombre' => 'ALEXANDER RAMIREZ ARCOS',
        'numero_empleado' => 'D0054',
        'rfc' => 'RAAA8009045Y3',
        'curp' => 'RAAA800904HTCMRL06',
        'nss' => '83098002666',
        'categoria' => 'DOCENTE',
        'departamento' => 'DIVISION DE ING EN GESTION',
        'salario' => '13741.55',
        'periodo' => '01/10/2026 al 15/10/2026',
        'fecha_pago' => '2026-10-14',
        'fecha_ingreso' => '2009-08-15',
        'fecha_nacimiento' => '1980-09-04',
    ])->and(PayslipTextParser::isComplete($fields))->toBeTrue();
});

test('it leaves fields empty and incomplete when the text is not a payslip', function () {
    $fields = PayslipTextParser::parse('Hola mundo');

    expect($fields['nss'])->toBe('')
        ->and(PayslipTextParser::isComplete($fields))->toBeFalse();
});
