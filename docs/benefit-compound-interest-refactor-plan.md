# Plan: Refactor del cálculo de interés compuesto para prestaciones

## Objetivo

Reemplazar la fórmula de interés compuesto actualmente usada para prestaciones por un cálculo
quincena-por-quincena con tasa diaria equivalente. La corrida financiera del PDF mostrará el
crecimiento del saldo período a período, dando trazabilidad completa al usuario.

## Alcance

- **Solo afecta**: bloque `benefits` de `LoanSimulationService`.
- **No cambia**: lógica de nómina, lógica de `extra_days`, controlador, vistas de resumen.
- **Cambia**: fórmula de tasa diaria, generación de filas, vista de tabla de amortización (estilos de `benefit_growth`), tests de prestaciones.

## Decisiones de diseño confirmadas

| # | Decisión |
|---|----------|
| D1 | Tasa diaria equivalente: `(1 + r_m)^(1/30) - 1` — no `r_m / 30` |
| D2 | Cada quincena genera una fila `type = 'benefit_growth'`; la última genera `type = 'benefit'` |
| D3 | Filas `benefit_growth`: `payment = 0`, `principal = 0`; fila `benefit`: `principal + interés` |
| D4 | `label` de filas intermedias: `"<nombre prestación> — Q<n>"`, última: `"<nombre prestación>"` — el nombre real se pasa en el array de params (`name`) |
| D5 | `base_date` = última quincena nómina, **excepto** si `(collection_date - last_nomina_date) <= 15 días` → usar `disbursement_date` |
| D6 | Las filas de crecimiento se incluyen en `$amortizationTable` (expuestas al PDF) |
| D7 | `date` de cada fila = fecha de **fin** de la quincena (mismo criterio que el Python) |

---

## Sub-tarea 1 — Refactorizar el servicio y actualizar la vista Blade

**Intent**
Reemplazar el bloque de cálculo de prestaciones (una sola fila con interés total) por una
iteración quincena-por-quincena con tasa diaria equivalente, y reflejar las nuevas filas en el PDF.

**Expected Outcomes**
- La tasa diaria se calcula como `(1 + monthly_rate)^(1/30) - 1`.
- Para cada prestación se determina el `base_date` con la regla D5.
- Se itera desde `base_date` hasta `collection_date` usando `nextFortnight()`.
- Cada quincena intermedia produce una fila `benefit_growth`; la última produce `benefit`.
- El `date` de cada fila es la fecha de **fin** de la quincena.
- El `label` usa el nombre real de la prestación (recibido en el array de params como `name`).
- La vista Blade distingue `benefit_growth` visualmente y muestra `—` en la columna "Pago".

**Todo**
- [ ] Ampliar el schema de `$params['payment_methods']['benefits'][n]` para incluir `name: string`.
- [ ] En `simulate()`, consultar `Benefit::whereIn('id', $ids)->pluck('name', 'id')` y enriquecer el array de benefits con `name` antes de pasarlo al servicio.
- [ ] Extraer el cálculo de tasa diaria en un método privado `dailyRate(float $monthlyRate): float`.
- [ ] Agregar método privado `benefitBaseDate(Carbon $disbursement, ?Carbon $lastNomina, Carbon $collectionDate): Carbon` que implemente D5.
- [ ] Reemplazar el bloque `foreach ($benefitsPayments ...)` por la nueva iteración quincena-por-quincena.
- [ ] Verificar que `period` sigue incrementándose correctamente al mezclar filas nómina + benefit_growth + benefit.
- [ ] En `loan-amortization-table.blade.php`, agregar condición de estilo para `benefit_growth`.
- [ ] Mostrar `—` en la celda "Pago" cuando `$row['type'] === 'benefit_growth'`.
- [ ] Actualizar la nota al pie del PDF para mencionar el cálculo quincena-por-quincena.

**Relevant Context**
- Servicio: `app/Services/LoanSimulationService.php` — método `buildAmortizationTable()` líneas 108–147, auxiliar `nextFortnight()` línea 220
- Controlador: `app/Http/Controllers/LoanSimulatorController.php` líneas 68–76 (mapeo de benefits)
- Vista: `resources/views/pdf/partials/loan-amortization-table.blade.php` líneas 22, 29, 47
- El modelo `Benefit` ya se carga en el controlador (`show()`); en `simulate()` hacer un segundo query `Benefit::whereIn` para resolver los nombres (opción A confirmada — no confiar en el cliente)

**Status**: `[ ] pending`

---

## Sub-tarea 2 — Actualizar los tests de prestaciones

**Intent**  
El test actual que verifica el comportamiento viejo de benefits (`toHaveCount(1)` + fórmula `r/30`)
fallará con el cambio. Reemplazarlo por tests que cubran el nuevo comportamiento.

**Expected Outcomes**
- Test A: una prestación sin nómina desde `disbursement_date` hasta `collection_date` genera el
  número correcto de filas quincenales (`benefit_growth` intermedias + 1 `benefit` final).
- Test B: la tasa diaria usada en el cálculo es `(1 + monthly_rate)^(1/30) - 1` (no `r/30`).
- Test C: cuando `collection_date - last_nomina_date <= 15 días`, el `base_date` es el desembolso
  (verificable por el número de filas generadas, que será mayor).

**Todo**
- [ ] Eliminar el test `'calculates compound interest for a benefit payment'` (línea 54).
- [ ] Agregar test A: conteo correcto de filas para benefit sin nómina.
- [ ] Agregar test B: verificar que el interés de la primera fila usa la tasa diaria equivalente.
- [ ] Agregar test C: verificar la regla de excepción del `base_date` con nómina cuya última
  quincena cae ≤ 15 días antes del cobro de la prestación.

**Relevant Context**
- Archivo: `tests/Unit/LoanSimulationServiceTest.php`
- Test a eliminar: líneas 54–85
- Los tests de nómina (líneas 10–33, 35–52, 87–191) **no se tocan**.
- El array de params en los nuevos tests debe incluir `name` en cada benefit.

**Status**: `[ ] pending`
