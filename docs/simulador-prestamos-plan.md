# Plan: Simulador de Préstamos

## Overview

Add a **public loan simulation page** at `/simulador-prestamo` that mirrors the Flujo 1 request flow from `prestamos.md`, with these differences:
- No authentication required (public island, same pattern as `petition-form.vue`)
- Adds an explicit **interest rate** input (since there is no Admin to approve it)
- No file uploads, no signature
- No database writes — purely computational
- Generates a PDF of the **corrida financiera** and **tabla de amortización** only (no contract, no disbursement act)
- "Disbursement date" is always today (not editable)

This feature also introduces the **`Benefit` model** (catálogo de prestaciones) used here and later by the real loan module.

---

## Sub-Tasks

---

### ST-1 — `Benefit` model and migration

**Intent**: Create the `benefits` table and Eloquent model that represents the catalog of union benefits (prestaciones). This catalog is shared by the simulator and the future real loan module.

**Expected Outcomes**
- Migration creates `benefits` with: `id`, `name`, `collection_day` (1–31), `collection_month` (1–12), `is_active`, timestamps, soft deletes.
- No `interest_type` column — interest type is determined by the payment method: **nómina = simple (German method)**, **prestación = compound**.
- `Benefit` model with fillable, casts, and a helper `collectionDateForYear(int $year): Carbon` that returns the collection date for a given year.
- Factory for use in tests.
- Seeder with all 12 known benefits.

**Todo List**
- [ ] `php artisan make:model Benefit --migration --factory --seed --no-interaction`
- [ ] Write migration: `name (string)`, `collection_day (tinyInteger)`, `collection_month (tinyInteger)`, `is_active (boolean default true)`, timestamps, soft deletes.
- [ ] Write model: fillable, casts, scope `active()`, helper `collectionDateForYear(int $year): Carbon`.
- [ ] Write factory with realistic data.
- [ ] Write seeder with all 12 known benefits (see list below).
- [ ] Add `BenefitSeeder` to `DatabaseSeeder`.

**Relevant Context**
- Pattern: `app/Models/UserRequest.php`, `app/Models/RequestType.php`
- No `InterestType` enum needed on `Benefit` — interest type is derived from payment method in `LoanSimulationService`.
- Migration pattern: `database/migrations/` — follow existing naming + soft deletes

**Seeder data**:

| Name | Month | Day |
|------|-------|-----|
| 5 Días Complemento al Aguinaldo | 12 | 20 |
| 9 Días Otras Prestaciones de Fin de Año | 12 | 20 |
| Aguinaldo 40 Días | 12 | 20 |
| Ajuste al Calendario 5 Días | 12 | 31 |
| Bono Navideño | 12 | 15 |
| Día de la Madre | 5 | 10 |
| Día del Maestro | 5 | 15 |
| Día del Padre | 6 | 21 |
| Día del Servidor Público | 6 | 30 |
| Prima Vacacional 12 Días, 1er. Periodo | 6 | 1 |
| Prima Vacacional 12 Días, 2do. Periodo | 12 | 1 |
| Retroactivo | 8 | 15 |

**Status**: `[ ] pending`

---

### ST-2 — `LoanSimulationService` — financial calculation engine

**Intent**: Encapsulate all loan math in a pure service class so it can be tested independently and reused by the future real loan module.

**Expected Outcomes**
- `App\Services\LoanSimulationService` with two public methods:
  - `buildAmortizationTable(array $params): array` — returns row-by-row table for nómina (German simple interest)
  - `buildFinancialRunSummary(array $params): array` — returns summary totals
- Handles the "extra days" period (disbursement → first fortnight)
- Handles **compound** interest: charged once at the benefit collection date — after nómina fortnights end, or from disbursement date if no nómina
- Returns only plain PHP arrays (no models, no DB, fully testable)
- The `$params` array is documented in the docblock

**Expected `$params` shape**
```php
[
    'amount'            => float,     // monto simulado
    'annual_rate'       => float,     // e.g. 0.06 for 6 %
    'payment_methods'   => [          // at least one of:
        'nomina' => [
            'fortnights'        => int,     // number of pay periods
            'first_fortnight'   => string,  // 'Y-m-d'
        ],
        'benefits' => [                     // array of benefit ids with amounts
            ['benefit_id' => int, 'amount' => float, 'collection_date' => string],
        ],
    ],
    'disbursement_date' => string,    // 'Y-m-d', always today for simulator
]
```

**Todo List**
- [ ] Create `app/Services/LoanSimulationService.php` with `buildAmortizationTable()` and `buildFinancialRunSummary()`.
- [ ] Implement German simple-interest fortnight logic (extra days + regular fortnights).
- [ ] Implement compound-interest single-charge logic.
- [ ] Write unit tests: at least one happy-path amortization test, one compound-interest test, one boundary test (last valid fortnight = Nov 30).

**Relevant Context**
- Calculation rules: `docs/prestamos.md` §§ "Cálculo: Interés simple — Método alemán" and "Cálculo: Interés compuesto"
- Service pattern: `app/Services/PetitionRequestService.php`
- Fortnight helper: last day of February = 28/29 (use Carbon `endOfMonth`); fortnights are always day-15 and last-day-of-month.
- Upper date boundary for nómina: **November 30 of current year**.

**Status**: `[ ] pending`

---

### ST-3 — PDF Blade templates (corrida financiera + tabla de amortización)

**Intent**: Create the two PDF Blade views that the simulator renders and the future real loan module will also use.

**Expected Outcomes**
- `resources/views/pdf/loan-simulation-combined.blade.php` — PDF único con la corrida financiera y la tabla de amortización.
- `resources/views/pdf/partials/loan-financial-summary.blade.php` — resumen reutilizable de la corrida financiera.
- `resources/views/pdf/partials/loan-amortization-table.blade.php` — tabla reutilizable de todas las quincenas.
- Both extend `resources/views/document/layout.blade.php`.
- Clearly marked "SIMULACIÓN — Documento sin valor legal" banner.

**Todo List**
- [ ] Study existing `resources/views/document/layout.blade.php` and a PDF view (e.g. `petition-voucher.blade.php`) for structure and variables pattern.
- [x] Create the combined PDF view and its reusable Blade partials.
- [ ] Include prominent simulation disclaimer banner.

**Relevant Context**
- Layout: `resources/views/document/layout.blade.php`
- Reference PDF view: `resources/views/pdf/petition-voucher.blade.php`
- Logo path: `public_path('assets/images/logo.webp')` (from existing actions)
- DomPDF version: `barryvdh/laravel-dompdf ^3.1`

**Status**: `[ ] pending`

---

### ST-4 — `GenerateLoanSimulationPdf` Action

**Intent**: Create the Action that wires `LoanSimulationService` output into the two PDF Blade views and returns the combined (or two separate) DomPDF objects.

**Expected Outcomes**
- `App\Actions\GenerateLoanSimulationPdf::execute(array $simulationData): \Illuminate\Http\Response` — streams a downloadable PDF.
- Accepts the structured output of `LoanSimulationService`.
- Returns a merged PDF (amortization table + financial run summary in one document) or two separate downloads (decide: one combined PDF is simpler and user-friendly).

**Todo List**
- [ ] Create `app/Actions/GenerateLoanSimulationPdf.php`.
- [ ] Use `Pdf::loadView()` for each template, merge into one response.
- [ ] Write a basic feature test that verifies a 200 response with `application/pdf` content-type.

**Relevant Context**
- Pattern: `app/Actions/GeneratePetitionVoucher.php`
- DomPDF API: `Pdf::loadView('blade.view', $data)->output()` for binary, `->download()` for response.
- Theme colors: pull `primaryColor` from `Theme` model if it exists, or use a constant fallback.

**Status**: `[ ] pending`

---

### ST-5 — `LoanSimulatorController` + API route

**Intent**: Create the public controller that receives the simulation form data, validates it, runs the service, and either returns JSON (with a PDF URL) or directly streams the PDF.

**Expected Outcomes**
- `App\Http\Controllers\LoanSimulatorController` with:
  - `show()` — returns the Blade view that hosts the Vue island (passes available benefits as JSON props).
  - `simulate()` — POST endpoint, validates input, runs `LoanSimulationService`, calls `GenerateLoanSimulationPdf`, returns JSON `{ pdf_url }` or streams PDF.
- `StoreLoanSimulationRequest` FormRequest for validation.
- Routes registered in `routes/web.php`:
  - `GET /simulador-prestamo` → `LoanSimulatorController@show`
  - `POST /api/simulador-prestamo/simulate` → `LoanSimulatorController@simulate`

**Validation rules**
- `amount`: required, numeric, min:1
- `annual_rate`: required, numeric, between:0.01,99.99
- `payment_type`: required, in:`nomina`,`benefit`,`mixed`
- `fortnights` (if nómina): required integer, min:1
- `first_fortnight` (if nómina): valid date, must be ≥ today + 20 days, ≤ Nov 30 current year
- `benefits` (if benefit/mixed): array of benefit IDs with amounts, each benefit must be `is_active = true`

**Todo List**
- [ ] `php artisan make:controller LoanSimulatorController --no-interaction`
- [ ] `php artisan make:request StoreLoanSimulationRequest --no-interaction`
- [ ] Implement `show()`: query active benefits, pass to Blade as `data-props`.
- [ ] Implement `simulate()`: validate → service → action → return `{ pdf_url }`.
- [ ] Register routes in `routes/web.php` (no auth middleware).
- [ ] Feature test: valid simulation returns PDF response; invalid input returns 422.

**Relevant Context**
- Controller pattern: `app/Http/Controllers/PublicPetitionController.php`
- FormRequest pattern: `app/Http/Requests/StorePetitionRequest.php`
- Route placement: `routes/web.php` — add to the public (unauthenticated) section
- Tolerance rule: 20-day gap for first fortnight; Nov 30 hard cutoff for nómina

**Status**: `[ ] pending`

---

### ST-6 — `simulator-form.vue` island component

**Intent**: Build the public Vue island that drives the multi-step simulation form, following the same island architecture as `petition-form.vue`.

**Expected Outcomes**
- `resources/js/components/loans/simulator-form.vue` — a single-root island component.
- Multi-section layout:
  1. **Monto** — numeric input (monto a simular).
  2. **Forma de pago** — toggles for Nómina / Prestación / Mixto:
     - Nómina: select de quincenas disponibles (respecting 20-day tolerance + Nov 30 cutoff), salary-share input.
     - Prestaciones: multi-select from benefit catalog passed via props (only benefits where collection_date > today).
     - Mixed: both sections shown.
  3. **Tasa de interés** — numeric input (annual rate %, no default, freely editable — this is the extra step vs. the real flow).
  4. **Simular** — submit button that posts to `/api/simulador-prestamo/simulate` and opens the returned PDF in a new tab.
- Uses `useIslandForm` composable (mandatory for islands — never `useForm` from Inertia).
- DaisyUI 5 + Tailwind v4 styling consistent with `petition-form.vue`.
- "Documento sin valor legal" disclaimer visible before submit.
- Accessible: labels, `aria-*`, focus states.

**Todo List**
- [ ] Activate `vue-islands-development` skill before writing the component.
- [ ] Create `resources/js/components/loans/simulator-form.vue`.
- [ ] Implement step 1: monto input.
- [ ] Implement step 2: payment method toggles with conditional sub-sections; compute available fortnights client-side (day 15 and last day of month, starting today + 20 days, ending Nov 30).
- [ ] Implement step 3: tasa de interés input.
- [ ] Implement submit: `useIslandForm` POST to `/api/simulador-prestamo/simulate`; on success open `pdf_url` in new tab.
- [ ] Style consistently with `petition-form.vue` (DaisyUI tokens, same section heading pattern `01 —`, `02 —`, etc.).

**Relevant Context**
- Island architecture rule: `.ai/rules/frontend-conventions.md` §1
- Reference component: `resources/js/components/petitions/petition-form.vue`
- Composable: `resources/js/composables/useIslandForm.ts`
- Fortnight generation logic: mirrors what `LoanSimulationService` expects; day 15 and `endOfMonth()` per month.
- Skills to activate: `vue-islands-development`, `inertia-vue-development`, `tailwindcss-development`, `daisyui`

**Status**: `[ ] pending`

---

### ST-7 — Blade view + island mounting + route smoke test

**Intent**: Create the Blade wrapper that mounts the Vue island, register the page route, and verify end-to-end that `/simulador-prestamo` loads correctly.

**Expected Outcomes**
- `resources/views/landing/simulador-prestamo.blade.php` — extends the landing layout, mounts `simulator-form` island via `data-vue` + `data-props`.
- Route `GET /simulador-prestamo` resolves to 200, passes benefits JSON.
- Smoke test: HTTP GET `/simulador-prestamo` returns 200.

**Todo List**
- [ ] Study an existing landing Blade view (e.g. `resources/views/landing/`) for the island mounting pattern.
- [ ] Create `resources/views/landing/simulador-prestamo.blade.php`.
- [ ] Mount `simulator-form` component with `data-vue="simulator-form"` and `data-props="{{ json_encode($benefits) }}"`.
- [ ] Verify `island.ts` already registers or add the new component path.
- [ ] Feature test: `GET /simulador-prestamo` → 200.

**Relevant Context**
- Island mounting pattern: `.ai/rules/frontend-conventions.md` §1 "Montaje en Blade"
- Existing landing view: `resources/views/landing/`
- Island entry: `resources/js/island.ts`

**Status**: `[ ] pending`

---

## Execution Order

```
ST-1 (Benefit model)
  → ST-2 (calculation service — depends on Benefit for collection dates)
    → ST-3 (PDF templates — depends on service output shape)
      → ST-4 (PDF action — wraps templates)
        → ST-5 (controller + routes — wires service + action)
          → ST-6 (Vue island — consumes the API)
            → ST-7 (Blade + mounting + smoke test)
```

Each sub-task is designed to be implemented and reviewed independently before proceeding to the next.
