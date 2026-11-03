# Plan: Corregir PDF que muestra datos de simulaciones anteriores

## Resumen

El PDF generado por el simulador de préstamos muestra datos de simulaciones anteriores cuando el usuario hace múltiples simulaciones en la misma sesión sin recargar la página. Se identificaron tres causas que deben corregirse:

1. **Nombre de archivo fijo** — el PDF siempre se descarga con el mismo nombre `simulacion-prestamo.pdf`. Cuando el usuario hace múltiples simulaciones, el navegador reutiliza la pestaña o el archivo temporal anterior con ese nombre, mostrando el PDF anterior. El nombre del archivo llevará los primeros 8 caracteres del token UUID (ej. `simulacion-prestamo-a1b2c3d4.pdf`).
2. **Ausencia de headers HTTP de no-caché** — la respuesta del endpoint de descarga no incluye `Cache-Control: no-store`, lo que puede permitir que el navegador devuelva la respuesta anterior en caché HTTP.
3. **Copia superficial en `reset()` de `useIslandForm`** — bug latente: el método `reset()` usa `Object.assign` sobre el objeto `initialValues` original. Los campos anidados (`nomina`, `benefits`) comparten referencia, por lo que si se llama `reset()` los valores no se restauran correctamente.

---

## Sub-Tarea 1: Agregar token al nombre del archivo PDF descargado

**Intent**
Hacer que el nombre del archivo descargado sea único por simulación incluyendo el token UUID, eliminando la posibilidad de que el navegador reutilice un archivo con el mismo nombre del sistema de archivos temporal.

**Expected Outcomes**
- Cada descarga del PDF produce un archivo con nombre único (ej. `simulacion-prestamo-a1b2c3d4.pdf`, usando los primeros 8 caracteres del token).
- El navegador no puede confundir el archivo nuevo con el anterior porque tienen nombres distintos.

**Todo List**
- [ ] En `GenerateLoanSimulationPdf::execute()`, añadir un parámetro opcional `string $token = ''` al método.
- [ ] Usar el token (o los primeros 8 caracteres) para nombrar el archivo: `simulacion-prestamo-{token}.pdf`.
- [ ] En `LoanSimulatorController::download()`, pasar el `$token` al llamar a `$this->generateLoanSimulationPdf->execute($data, $token)`.

**Relevant Context**
- `app/Actions/GenerateLoanSimulationPdf.php` — línea 31: `->download('simulacion-prestamo.pdf')`
- `app/Http/Controllers/LoanSimulatorController.php` — línea 110: llamada al action

**Status:** [x] done

---

## Sub-Tarea 2: Agregar headers HTTP de no-caché a la respuesta del PDF

**Intent**
Asegurar que la respuesta HTTP del endpoint de descarga del PDF no sea almacenada en caché por el navegador ni por proxies intermedios.

**Expected Outcomes**
- La respuesta del PDF incluye `Cache-Control: no-store, no-cache, must-revalidate` y `Pragma: no-cache`.
- El navegador siempre hace una nueva petición al servidor al descargar el PDF, nunca sirve uno del caché HTTP.

**Todo List**
- [ ] En `GenerateLoanSimulationPdf::execute()`, después de obtener la respuesta de DomPDF, agregar los headers de no-caché antes de devolver el `Response`.
- [ ] Verificar que los headers no colisionen con los que establece DomPDF por defecto.

**Relevant Context**
- `app/Actions/GenerateLoanSimulationPdf.php` — línea 31: `->download('simulacion-prestamo.pdf')` devuelve un `Response` de Laravel.
- DomPDF establece sus propios headers de descarga (`Content-Disposition: attachment`), pero no necesariamente `Cache-Control: no-store`.

**Status:** [x] done

---

## Sub-Tarea 3: Corregir copia superficial en `reset()` de `useIslandForm`

**Intent**
Corregir el bug latente en el composable `useIslandForm` donde el método `reset()` hace una copia superficial (`Object.assign`) de `initialValues`, causando que los objetos anidados (como `nomina` y `benefits`) compartan referencia entre `form.data` e `initialValues`. Si el usuario llega a llamar `reset()` (o si en el futuro se agrega un reset automático al submit), los valores no se resetearían correctamente.

**Expected Outcomes**
- `reset()` restaura correctamente todos los campos del formulario a sus valores iniciales, incluyendo campos anidados.
- `initialValues` nunca es mutado por la edición del formulario.
- El formulario de simulación puede ser enviado múltiples veces con datos distintos sin contaminación entre envíos.

**Todo List**
- [ ] En `useIslandForm.ts`, guardar una copia profunda de `initialValues` al momento de crear el formulario: `const defaultValues = JSON.parse(JSON.stringify(initialValues))`.
- [ ] En el método `reset()`, usar `defaultValues` en lugar de `initialValues`: `Object.assign(form.data, JSON.parse(JSON.stringify(defaultValues)))`.

**Relevant Context**
- `resources/js/composables/useIslandForm.ts` — línea 59-62: método `reset()`
- `resources/js/components/loans/simulator-form.vue` — línea 20-29: uso de `useIslandForm` con objetos anidados

**Status:** [x] done
