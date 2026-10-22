# Plan: Corrección de días reales en la primera quincena de nómina

## Objetivo

Eliminar el hardcode de 15 días en la primera iteración del loop de nómina y reemplazarlo
por días reales del calendario, igual que ocurre en las quincenas 2..N.

## Problema actual

En `LoanSimulationService::buildAmortizationTable()`, al entrar al `for`, tanto
`$previousDate` como `$currentDate` apuntan a `$firstFortnight`. En la iteración `i=0`
el `diffInDays` entre ellas daría 0, por eso el código hardcodea 15. Esto produce errores
calendario-dependientes en la primera quincena:

| `$firstFortnight` | Días reales | Días cobrados | Error |
|---|---|---|---|
| Día 15 de mes de 31 días (ene, mar, may…) | 16 | 15 | −1 día (favor deudor) |
| Último día de mes | 15 | 15 | Exacto |
| Feb 15 (año no bisiesto) | 13 | 15 | **+2 días (cargo indebido)** |
| Feb 15 (año bisiesto) | 14 | 15 | **+1 día (cargo indebido)** |
| Meses de 30 días en día 15 (abr, jun…) | 15 | 15 | Exacto |

El caso más grave es `first_fortnight = Feb 15` en año no bisiesto: cobra 2 días de interés
de más al deudor.

## Enfoque de la solución

Cambiar la inicialización del loop para que `$currentDate` ya apunte a la siguiente quincena
antes de la primera iteración, y `$previousDate` apunte al `$firstFortnight`. Así el
`diffInDays` siempre trabaja con dos fechas distintas y el hardcode desaparece.

La `date` de cada fila sigue siendo la fecha de **fin** del período (igual que hoy), porque
`$currentDate` ya es el fin de la quincena al entrar al cuerpo del loop.

## Alcance

- **Cambia**: `buildAmortizationTable()` en `LoanSimulationService` — inicialización de
  `$previousDate` / `$currentDate` y condición `$days` en la primera iteración.
- **No cambia**: lógica de benefits, extra_days, controlador, vistas, resumen financiero.
- **Tests**: agregar un test que verifique los días reales de la primera quincena
  (e.g. Feb 15 → 13 días, o Ene 15 → 16 días). Revisar que los tests existentes
  sigan pasando sin modificación.

---

## Sub-tarea 1 — Corregir el loop de nómina en `LoanSimulationService`

**Intent**
Reemplazar el hardcode `$days = ($i === 0) ? 15 : …` por `$days = $previousDate->diffInDays($currentDate)`
uniforme, avanzando `$currentDate` antes del loop para que el primer `diffInDays` sea válido.

**Expected Outcomes**
- La primera quincena calcula días reales: 16 para ene/15, 13 para feb/15 (no bisiesto), etc.
- Las quincenas 2..N siguen calculando exactamente lo mismo que hoy.
- El campo `date` de cada fila sigue siendo la fecha de fin del período.
- El `period` counter no cambia.

**Todo**
- [ ] Cambiar `$previousDate = $firstFortnight->copy()` por apuntar al inicio del período,
      y `$currentDate = $this->nextFortnight($firstFortnight)` para apuntar al fin.
- [ ] Eliminar la condición ternaria `($i === 0) ? 15 : …` y usar siempre `$previousDate->diffInDays($currentDate)`.
- [ ] Al final de cada iteración: `$previousDate = $currentDate->copy()` y
      `$currentDate = $this->nextFortnight($currentDate)` — igual que hoy.
- [ ] Verificar que `$rows[]['date']` sigue siendo `$currentDate->format('Y-m-d')` (fin del período).

**Relevant Context**
- Archivo: `app/Services/LoanSimulationService.php`, líneas 72–106
- Método auxiliar: `nextFortnight()` líneas 272–287
- El campo `date` de las filas `extra_days` (línea 61) no se toca — esa fecha ya es `$firstFortnight`.

**Status**: `[ ] pending`

---

## Sub-tarea 2 — Agregar tests que cubran días reales de la primera quincena

**Intent**
Probar explícitamente que la primera quincena usa días reales, cubriendo el caso más grave
(Feb 15, año no bisiesto = 13 días) y el caso de mes largo (Ene 15 = 16 días).

**Expected Outcomes**
- Test A: `first_fortnight = 2026-02-15` → primera quincena cobra interés de 13 días.
- Test B: `first_fortnight = 2026-01-15` → primera quincena cobra interés de 16 días.
- Todos los tests existentes siguen pasando sin modificación.

**Todo**
- [ ] Agregar test A: verificar `interest` de la primera fila nomina cuando `first_fortnight = 2026-02-15`
      usando `dailyRate = 0.06/30` y `days = 13`.
- [ ] Agregar test B: verificar `interest` de la primera fila nomina cuando `first_fortnight = 2026-01-15`
      usando `dailyRate = 0.06/30` y `days = 16`.
- [ ] Correr toda la suite para confirmar que no hay regresiones.

**Relevant Context**
- Archivo: `tests/Unit/LoanSimulationServiceTest.php`
- Tests existentes de días reales para quincenas 2..N: líneas 216–275 — seguir el mismo patrón.
- `nextFortnight(Feb 15)` → `Feb 28` en 2026 (no bisiesto) = 13 días.
- `nextFortnight(Jan 15)` → `Jan 31` = 16 días.

**Status**: `[ ] pending`
