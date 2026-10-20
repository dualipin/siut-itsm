# Plan: Rediseño del Simulador de Préstamo (UX + Datos + Cálculo)

## Resumen

Tres grupos de cambios coordinados sobre el simulador público de préstamos:

1. **Corrección del motor de cálculo** — reemplazar tasa anual/365 por tasa mensual/30; ajustar el contrato de datos del servicio (eliminar `amount` raíz, usar `nomina.amount`).
2. **Cambios estructurales de datos** — el request y el controlador adoptan la nueva estructura; la tasa pasa de campo libre a selector de las 4 opciones del reglamento.
3. **Rediseño UX/UI** — tipografía, zonas táctiles, tarjetas de forma de pago, paneles informativos, resumen de total, orientado a usuarios mayores de 40 años.

Archivos afectados:
- `app/Services/LoanSimulationService.php`
- `app/Http/Requests/StoreLoanSimulationRequest.php`
- `app/Http/Controllers/LoanSimulatorController.php`
- `resources/js/components/loans/simulator-form.vue`
- `tests/Unit/LoanSimulationServiceTest.php`
- `tests/Feature/LoanSimulatorControllerTest.php`

---

## Sub-tarea 1 — Corregir el motor de cálculo del servicio

**Status:** [ ] pending

### Intent
El servicio usa `tasa_anual / 365 × días` para calcular interés. El método correcto confirmado es **capital constante + interés sobre saldo insoluto**, con la tasa convertida de **mensual a diaria** (`tasa_mensual / 30`). También se elimina el campo raíz `amount` y se introduce `nomina.amount` para el tramo de nómina.

### Método matemático confirmado
- **Tasa diaria** = `tasa_mensual / 30`
- **Días adicionales** (disbursement → primera quincena): `interés = saldo × tasa_diaria × días_adicionales`
- **Quincenas regulares**: `interés = saldo × tasa_diaria × días_reales_entre_quincenas`
  - Los días entre quincenas NO son siempre 15. Se calcula `diffInDays(fechaAnterior, fechaActual)` para cada período.
  - Ejemplos: 15-Feb → 28-Feb = **13 días**; 15-Ene → 31-Ene = **16 días**; 31-Ene → 15-Feb = **15 días**.
- **Capital por quincena** = `nomina_amount / total_quincenas` (constante)
- **Cuota** = `capital + interés` (decreciente quincena a quincena, con variación por días reales)
- **Saldo final** = `saldo_anterior − capital`
- La última quincena absorbe el remanente de redondeo.

### Expected Outcomes
- El servicio recibe `nomina.amount` en lugar de `params['amount']` para el tramo de nómina.
- El cálculo de interés usa `monthly_rate / 30 × días_reales` en lugar de `annual_rate / 365 × 15`.
- El parámetro `annual_rate` se renombra a `monthly_rate` en el contrato interno del servicio.
- El resumen (`buildFinancialRunSummary`) refleja el campo renombrado.
- Los tests unitarios pasan con los nuevos parámetros y valores esperados verificados con días reales.
- El parcial PDF `pdf/partials/loan-amortization-table.blade.php` muestra "Tasa mensual" y usa `monthly_rate_percent`.

### Todo List
1. En `LoanSimulationService::buildAmortizationTable`: reemplazar `$amount = $params['amount']` por `$amount = (float) ($params['payment_methods']['nomina']['amount'] ?? 0)`.
2. Renombrar `$annualRate` → `$monthlyRate` en todo el método; actualizar la fórmula a `$monthlyRate / 30`.
3. En el bucle de quincenas regulares: introducir `$previousDate` para rastrear la fecha de la quincena anterior; calcular `$days = $previousDate->diffInDays($currentDate)` y usar `$days` en lugar del literal `15` en la fórmula de interés. Para la primera quincena, `$previousDate` = `$firstFortnight` y los días = 15 (la fecha de inicio ya es la primera quincena).
4. En el PHPDoc del servicio: actualizar el shape de `$params` — eliminar `amount` de nivel raíz, añadir `amount` dentro de `nomina`, cambiar `annual_rate` → `monthly_rate`.
5. En `buildFinancialRunSummary`: actualizar referencias a `$params['annual_rate']` → `$params['monthly_rate']`; en el array de retorno cambiar `annual_rate` → `monthly_rate` y `annual_rate_percent` → `monthly_rate_percent`.
6. Actualizar `tests/Unit/LoanSimulationServiceTest.php`: todos los casos pasan `nomina.amount` en lugar de `amount` en raíz; actualizar tasa a `monthly_rate` (ej. `0.06` mensual); añadir un test específico que verifica que la segunda quincena de febrero usa 13 días de interés; recalcular valores esperados con la nueva fórmula de días reales.
7. En `resources/views/pdf/partials/loan-amortization-table.blade.php`: mantener la etiqueta "Tasa mensual" y la clave `$summary['monthly_rate_percent']`.

### Relevant Context
- `app/Services/LoanSimulationService.php` líneas 33–147 (`buildAmortizationTable`), 174–207 (`buildFinancialRunSummary`).
- `tests/Unit/LoanSimulationServiceTest.php` — todos los tests referencian `amount` en raíz y `annual_rate`.
- `resources/views/pdf/partials/loan-amortization-table.blade.php` — tabla de amortización y totales.
- El literal `15` en la línea 75 del servicio es el único punto a reemplazar por `$days` calculado con `diffInDays`.

---

## Sub-tarea 2 — Actualizar Request y Controlador

**Status:** [ ] pending

### Intent
Adaptar `StoreLoanSimulationRequest` y `LoanSimulatorController` a la nueva estructura: eliminar `amount` raíz, añadir `nomina.amount`, cambiar `annual_rate` libre a `monthly_rate` con valores permitidos del reglamento (6, 7.5, 8, 9.5 — expresados como tasa mensual equivalente).

### Tasas del reglamento (tasa mensual)
El usuario selecciona la tasa **mensual** directamente. Las 4 opciones son:

| Etiqueta en UI | Valor mensual a enviar |
|---|---|
| 6 % mensual — Agremiado ahorrador | 6 |
| 7.5 % mensual — Agremiado no ahorrador | 7.5 |
| 8 % mensual — NoAgremiado ahorrador | 8 |
| 9.5 % mensual — NoAgremiado no ahorrador | 9.5 |


### Expected Outcomes
- El request valida `monthly_rate` con `Rule::in([6, 7.5, 8, 9.5])` en lugar de `annual_rate` con rango libre.
- El request valida `nomina.amount` (requerido si `payment_type` incluye nómina, numérico, min:1).
- Se elimina la regla `amount` del nivel raíz.
- El controlador mapea `monthly_rate` al servicio correctamente.
- Los tests de feature pasan con la nueva estructura del payload.

### Todo List
1. En `StoreLoanSimulationRequest::rules()`: eliminar regla `amount`; añadir `nomina.amount` con `required_if` cuando `payment_type` es `nomina` o `mixed`; cambiar `annual_rate` → `monthly_rate` con `Rule::in([6, 7.5, 8, 9.5])`.
2. Actualizar `messages()` para reflejar los nuevos nombres de campo.
3. En `LoanSimulatorController::simulate()`: eliminar `'amount' => (float) $validated['amount']`; leer `monthly_rate` de `$validated`; pasar `nomina.amount` dentro de `$params['payment_methods']['nomina']`.
4. Actualizar `tests/Feature/LoanSimulatorControllerTest.php`: reemplazar `amount` raíz por `nomina.amount` dentro del array de nómina; cambiar `annual_rate` → `monthly_rate` con valor de la lista permitida.

### Relevant Context
- `app/Http/Requests/StoreLoanSimulationRequest.php` líneas 25–48 (reglas), 57–81 (mensajes).
- `app/Http/Controllers/LoanSimulatorController.php` líneas 52–75 (construcción de `$params`).
- `tests/Feature/LoanSimulatorControllerTest.php` — todos los payloads usan `amount` raíz y `annual_rate`.

---

## Sub-tarea 3 — Rediseñar el formulario: estructura de montos y tasa

**Status:** [ ] pending

### Intent
Eliminar el campo "Monto ($)" global de la sección 01. El monto de nómina se mueve dentro del fieldset de Nómina. Cada prestación seleccionada expone su propio campo de monto. La tasa pasa de input libre a select con 4 opciones etiquetadas. Se añade un resumen de total calculado en tiempo real.

### Cambios al estado del formulario (`useIslandForm`)
- Eliminar: `amount: ''` del nivel raíz.
- Añadir: `nomina.amount: ''` dentro del objeto `nomina`.
- Los montos de prestaciones ya viven en `benefits[*].amount` — sin cambio.
- Cambiar: `annual_rate: ''` → `monthly_rate: ''`.

### Expected Outcomes
- La sección "01 — Monto a simular" desaparece del formulario.
- El fieldset de Nómina incluye un campo "Monto nómina ($)" con nota "Solo el tramo que se descontará quincenalmente."
- Al seleccionar una prestación (checkbox), aparece debajo un campo de monto para esa prestación.
- La sección "03 — Tasa de interés" muestra un `<select>` con las 4 opciones etiquetadas (ej. "6 % — Agremiado ahorrador").
- Un panel de resumen sobre el botón de simular muestra "Total del préstamo: $X,XXX.XX" calculado en tiempo real; si el total es $0, muestra una advertencia en lugar del total.
- El método `syncBenefitAmounts` se elimina (ya no hay monto global que sincronizar).

### Todo List
1. En `<script setup>`: eliminar `amount` del estado inicial de `useIslandForm`; añadir `amount` dentro de `nomina`; cambiar `annual_rate` → `monthly_rate`.
2. Eliminar la función `syncBenefitAmounts` y su referencia en `@change` del input de monto.
3. Añadir computed `loanTotal` que suma `parseFloat(form.data.nomina.amount || 0)` más la suma de `form.data.benefits.map(b => parseFloat(b.amount || 0))`.
4. En el template: eliminar toda la sección `<!-- ===== 01 — Monto ===== -->`.
5. Dentro del fieldset de Nómina: añadir campo "Monto nómina ($)" con `v-model="form.data.nomina.amount"`, tipo `number`, `min="1"`, nota de ayuda "Solo el tramo que se descontará quincenalmente.", manejo de error `fieldError('nomina.amount')`.
6. En la lista de prestaciones: dentro de cada `<label>` de fila, añadir condicionalmente (`v-if="isBenefitSelected(benefit.id)"`) un `<input>` de monto por prestación con `v-model` sobre el elemento correspondiente de `form.data.benefits`.
7. Sección de tasa: reemplazar `<input type="number">` de `annual_rate` por `<select>` de `monthly_rate` con 4 opciones. Valor de cada opción: el número (ej. `6`); texto visible: `"6 % — Agremiado ahorrador"`, etc.
8. Antes del botón de simular: añadir panel de resumen con `loanTotal`. Si `loanTotal > 0`, mostrar "Total del préstamo: $X,XXX.00"; si `loanTotal === 0`, mostrar `<div class="alert alert-warning">` con texto "Ingresa al menos un monto para simular."

### Relevant Context
- `resources/js/components/loans/simulator-form.vue`
- `fieldError` ya soporta claves anidadas tipo `'nomina.amount'` — verificar que el helper lo maneje correctamente (línea 132–135 del archivo actual).
- `toggleBenefit` (línea 92–104) actualmente sincroniza `amount: form.data.amount` al añadir una prestación — debe cambiarse a `amount: ''` para que el usuario lo llene manualmente.

---

## Sub-tarea 4 — Rediseñar el selector de quincenas (select dinámico)

**Status:** [ ] pending

### Intent
Reemplazar el `<input type="number">` libre de "Número de quincenas" por un `<select>` cuyas opciones se recalculan dinámicamente según la primera quincena elegida, contando cuántas quincenas caben hasta el 30 de noviembre del año en curso. Añadir la fecha final calculada como texto informativo en tiempo real.

### Convenciones a respetar
- Última quincena válida: **30 de noviembre** del año en curso (hardcoded).
- Las quincenas caen en los días 15 y último de cada mes.
- La tolerancia de 20 días ya está resuelta en `availableFortnights` — no tocar esa lógica.

### Expected Outcomes
- El computed `availableFortnightCounts` devuelve un array de `{ value: number, label: string }` calculado a partir de `form.data.nomina.first_fortnight`: cuenta cuántas quincenas caben entre esa fecha y el 30 de Nov.
- El `<select>` de número de quincenas muestra esas opciones y se deshabilita si `first_fortnight` no está seleccionado.
- Al cambiar `first_fortnight`, el valor de `fortnights` se resetea a `''`.
- Debajo del select de quincenas aparece un `<p>` con la fecha final calculada: "Última quincena: DD Mmm YYYY" usando el mismo helper `nextFortnight` lógico (reimplementado en Vue).
- La fecha final solo se muestra si `fortnights` tiene valor.

### Todo List
1. Añadir computed `availableFortnightCounts`: toma `form.data.nomina.first_fortnight`, avanza quincenas desde esa fecha hasta el 30 de Nov (límite), devuelve array `[{ value: 1, label: '1 quincena' }, ..., { value: N, label: 'N quincenas' }]`.
2. Añadir watcher sobre `form.data.nomina.first_fortnight` que resetea `form.data.nomina.fortnights` a `''` cuando cambia.
3. Añadir computed `lastFortnightDate`: a partir de `first_fortnight` + `fortnights`, avanza N−1 veces con la misma lógica de `nextFortnight`, devuelve la fecha formateada "DD Mmm YYYY".
4. En el template: reemplazar el `<input type="number" id="fortnights">` por un `<select>` que itera `availableFortnightCounts`, con `:disabled="!form.data.nomina.first_fortnight"`.
5. Añadir `<p v-if="lastFortnightDate">Última quincena: {{ lastFortnightDate }}</p>` debajo del select.

### Relevant Context
- `availableFortnights` en `simulator-form.vue` (líneas 42–72) contiene la lógica de avance de quincenas — reutilizar ese patrón para `availableFortnightCounts` y `lastFortnightDate`.
- La función `nextFortnight` del servicio PHP debe replicarse en TypeScript para el cálculo del lado cliente.

---

## Sub-tarea 5 — Rediseño UX/UI: tipografía, tarjetas y paneles

**Status:** [ ] pending

### Intent
Aplicar todas las mejoras de accesibilidad visual y motriz: eliminar textos diminutos, reemplazar opacidades por colores sólidos, agrandar zonas táctiles, convertir los botones de forma de pago en tarjetas, mejorar las filas de prestaciones, y envolver la tabla de tasas en un panel informativo.

### Expected Outcomes
- Ningún texto por debajo de `text-sm` (14px) en el template.
- Ninguna clase `opacity-*` sobre texto — reemplazada por `text-base-content/60` o equivalente sólido.
- Todos los inputs, selects y botones tienen `min-h-[48px]`; el botón de submit `min-h-[52px]`.
- Los tres tipos de pago se muestran como tarjetas con título + descripción.
- Las filas de prestaciones: checkbox tamaño estándar, fila completa es área de clic, fondo resaltado cuando seleccionada.
- La sección "03 — Tasa de interés" incluye un panel `alert-info` con la tabla de 4 tasas del reglamento.
- Los `<h2>` de sección tienen ícono SVG + `text-base font-semibold`.

### Todo List
1. **Tipografía**: buscar y reemplazar todas las instancias de `text-[10px]`, `text-[11px]`, `text-[.7rem]`, `text-xs` en labels, párrafos de ayuda, mensajes de error y leyendas → mínimo `text-sm`; texto principal de labels → `text-base`.
2. **Opacidades**: reemplazar `opacity-50`, `opacity-60`, `opacity-70`, `opacity-80` en texto → `text-base-content/50`, `text-base-content/60`, etc.
3. **Touch targets**: cambiar `min-h-[42px]` → `min-h-[48px]` en todos los inputs y selects; botón de submit → `min-h-[52px]`.
4. **Tarjetas de forma de pago**: reemplazar el bloque `<div class="flex flex-wrap gap-2">` de radio buttons por un grid de 3 tarjetas. Cada tarjeta: radio `sr-only`, cuerpo con título (`text-base font-semibold`) + descripción (`text-sm text-base-content/60`). Descripciones: Nómina="Descuento directo de nómina en quincenas regulares.", Prestación="Se descuenta de tus prestaciones anuales.", Mixto="Combina quincenas de nómina y prestaciones.". Clase activa: `border-primary bg-primary/5 ring-2 ring-primary`.
5. **Filas de prestaciones**: quitar `checkbox-sm`; añadir `border border-transparent rounded-lg` base y `:class="{ 'bg-primary/10 border-primary': isBenefitSelected(benefit.id) }"` al `<label>`; nombre de prestación → `text-base`; fecha → `text-sm text-base-content/60`.
6. **Panel de tasas**: reemplazar el `<p class="label-text-alt ...">Tasas de referencia…</p>` por un `<div class="alert alert-info mt-3">` con tabla de 4 filas (Agremiado ahorrador 6%, Agremiado no ahorrador 7.5%, NoAgremiado ahorrador 8%, NoAgremiado no ahorrador 9.5%).
7. **Títulos de sección**: envolver cada `<h2>` en `flex items-center gap-2`; añadir ícono SVG Heroicons outline: billete para Monto (si aplica), tarjeta para Forma de pago, porcentaje para Tasa; tamaño `text-base font-semibold text-base-content/70 uppercase tracking-wide`.
8. **Disclaimer del submit**: cambiar `text-xs opacity-60` → `text-sm text-base-content/60 leading-relaxed`.

### Relevant Context
- `resources/js/components/loans/simulator-form.vue` — template completo.
- Convención del proyecto: DaisyUI 5 + Tailwind CSS v4 (`.ai/rules/frontend-conventions.md`).
- Usar íconos SVG inline (Heroicons outline) sin dependencias adicionales.

---

## Notas transversales

- **Orden de ejecución recomendado**: 1 → 2 → 3 → 4 → 5. Las sub-tareas 1 y 2 son prerequisito de 3, ya que cambian el contrato de datos.
- **Deuda técnica registrada**: la ambigüedad entre "tasa mensual" vs "tasa anual" en `docs/prestamos.md` debe resolverse con el área financiera del sindicato antes del pase a producción real.
- **Sin cambios a**: `app/Models/Benefit.php`, `app/Actions/GenerateLoanSimulationPdf.php`, rutas, vistas Blade, seeders. Solo los 6 archivos listados en el Resumen.
- **Tests**: cada sub-tarea incluye actualización de sus tests correspondientes. No borrar tests existentes, solo actualizarlos.
