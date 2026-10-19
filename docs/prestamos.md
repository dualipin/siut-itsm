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

---

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

El monto se cobra de una sola vez en la fecha de la prestación.

```
monto_total = capital × (1 + tasa_anual / 365) ^ días_transcurridos
```

`días_transcurridos` = desde `disbursement_date` hasta la fecha de la prestación.

---



## Flujos de usuario paso a paso para Solicitar, Aprobar y Desembolsar un préstamo

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

---

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

---

### Flujo 3 — Desembolso (roles: Admin, Lider)

1. El revisor registra la fecha de desembolso por el monto aprobado.
2. El sistema genera automáticamente la tabla de amortización quincenal y la corrida financiera (PDF).


---

## Segunda Fase

### Flujo 4 — Seguimiento (todos los roles con acceso al préstamo)

1. El empleado ve su corrida financiera activa en sus prestamos.
2. Ve próxima quincena y saldo restante.
3. Puede solicitar liquidación anticipada (activa Flujo 6c).
4. Puede ver su **estado de cuenta** (todos sus préstamos: activos, liquidados, etc).

---

### Flujo 5 — Registro de pagos regulares (roles: Admin, Lider)

Crear un mecanismo para registrar pagos de quincenas, ya sea de forma manual o mediante un archivo CSV.

---

### Flujo 6 — Casos especiales

#### 6a. Pico (quincena no pagada)
- La quincena pasa a no pagada.
- Se calculan los días de atraso y el interés moratorio correspondiente.
- Se regenera la tabla desde esa quincena: suma el interés moratorio al saldo insoluto y recalcula las cuotas restantes.
- Genera un comprobante de cargo moratorio.

#### 6b. Pago anticipado / abono a capital
- Se registra.
- Aplicación: primero a intereses moratorios pendientes, luego a capital.
- Genera comprobante.

#### 6c. Liquidación total
- Calcula el saldo exacto al día (interés devengado hasta hoy).
- Genera comprobante.

#### 6d. Reestructuración
- Cierra el préstamo original.
- Crea nuevo registro.
- Registra la reestructuración (saldo insoluto, interés pendiente, interés moratorio, nuevo monto, nueva tasa, nuevo plazo).
- El nuevo préstamo entra al **Flujo 2** para aprobación.
- Genera comprobante.

---

## Reglas de negocio y cálculos

### Tasas de interés estándar

El tipo de socio se determina por `RoleEnum` + campo `is_saver` (ahorrador) en `users`:

| Tipo | Tasa anual |
|---|---|
| `Agremiado` + ahorrador | 6 % |
| `Agremiado` + no ahorrador | 7.5 % |
| `NoAgremiado` + ahorrador | 8 % |
| `NoAgremiado` + no ahorrador | 9.5 % |

> El revisor puede sobreescribir `applied_interest_rate` libremente al aprobar. El sistema guarda siempre la tasa pactada; no se recalcula automáticamente.

---


### Interés moratorio (picos)

```
interés_moratorio = saldo_pendiente × daily_default_rate × days_overdue
```

`daily_default_rate` se define al aprobar y se guarda en `loans.daily_default_rate`.

---

## Simulador de préstamos

Mostrar antes de enviar la solicitud (modal o sección expandible). También disponible como PDF descargable.

| # | Campo obligatorio |
|---|---|
| a | Nombre completo del prestamista (`lender_signatory`) |
| b | Monto solicitado / aprobado |
| c | Tasa de interés aplicable |
| d | Quincenas + días adicionales (ej. "Quincenas: 11 — Días adicionales: 5") |
| e | Forma de pago y fuente de descuento (ej. _"Se descontará con Bono Día del Padre"_ / _"Se pagará con Aguinaldo — total al cierre del año: $X"_) |
| f | Tabla de corrida financiera quincenal (ver abajo) |
| g | **Sumatoria final:** total capital, total intereses, total pagado |
| h | Firmantes dinámicos leídos de BD: **Secretario de Finanzas** + **Prestamista** |

---

## Tabla de corrida financiera (quincenal)

```
| # Quincena | Pago Capital | Interés Quincenal | Pago Quincenal | Saldo Final | Fecha de Pago |
|------------|-------------|-------------------|----------------|-------------|---------------|
| 1          | $XXX.XX     | $XXX.XX           | $XXX.XX        | $XXX.XX     | 15/Ene/2025   |
| ...        |             |                   |                |             |               |
| TOTALES    | $XXX.XX     | $XXX.XX           | $XXX.XX        | —           | —             |
```

- Fechas: **día 15** o **último día del mes**.
- Se regenera ante cualquier evento (pico, pago anticipado, reestructura), incrementando `table_version`; filas anteriores quedan `active = FALSE`.

---

## Documentos PDF

Todos generados server-side con **domPDF**.
Firmantes leídos dinámicamente de `loans.finance_signatory` y `loans.lender_signatory`.

| Documento | Cuándo se genera | `document_type` | Firmantes |
|---|---|---|---|
| Pagaré | Al aprobar | `pagare` | Prestamista |
| Anuencia de descuento | Al aprobar | `anuencia` | Prestamista |
| Corrida financiera | Al validar docs / bajo demanda | `corrida_financiera` | Sec. Finanzas + Prestamista |
| Estado de cuenta | Bajo demanda | generado al vuelo | — |
| Comprobante de movimiento | Por cada pago, pico o ajuste | `receipt_type` en `loan_receipts` | — |

---

## Convenciones de código obligatorias

1. Misma estructura que `app/Modules/Auth`: `Domain → Application → Infrastructure`.
2. Los repositorios extienden `PdoBaseRepository`; las interfaces van en `Domain/Repository/`.
3. Cero lógica de negocio en plantillas Latte; solo presentación.
4. Todos los montos como `DECIMAL(10,2)`; fechas con `DateTimeImmutable`.
5. Cada acción que cambie `loans.status` o modifique saldos genera un registro en `loan_receipts`.
6. Los casos de uso son `final readonly class` con constructor DI, igual que en `Auth`.
7. `LoansModule.php` registra sus propios `REPOSITORIES`, `SERVICES` y `USE_CASES` como constantes de array, igual que `AuthModule.php`.
8. Excepciones de dominio propias: `LoanNotFoundException`, `InvalidLoanStatusException`.

---

## Entregables esperados

1. `app/Modules/Loans/` — árbol completo de clases con la estructura definida arriba
2. Todos los casos de uso implementados (Flujos 1–6)
3. `AmortizationCalculator` — interés simple alemán, compuesto, regeneración de tabla y cálculo de picos
4. `PdfGenerator` — pagaré, anuencia, corrida financiera, estado de cuenta, comprobantes
5. Repositorios PDO para las 8 tablas existentes
6. Plantillas Latte — listado de solicitudes, detalle del préstamo, simulador, estado de cuenta
7. Validaciones server-side en PHP para todos los formularios
8. `LoansModule.php` con registro completo de dependencias