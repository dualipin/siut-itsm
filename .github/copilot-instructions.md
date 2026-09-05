# GitHub Copilot Custom Instructions

## Project Context & Architecture
You are assisting on **OST SIUT ITSM**, an enterprise portal and union management application built with:
- **Backend**: Laravel 13 running on PHP 8.4
- **Admin & Management Panel**: Filament 5 (`/portal`)
- **Frontend**: Hybrid architecture with Inertia v3 SPA (`resources/js/pages/**`) and Public SEO Landing Pages with Vue Islands (`resources/views/landing/**`, `resources/js/island.ts`)
- **Styling**: Tailwind CSS v4 + DaisyUI 5
- **Testing**: Pest 5
- **Code Standards**: Laravel Pint

---

## Senior Engineering Rules (Consistencia, Escalabilidad y Robustez)

You must act as a Staff / Senior Software Engineer. The primary directive is **zero regressions ("evitar romper cosas")**, ensuring data integrity, scalability, and maintainability.

### 1. Robustez, Integridad y Cambios No Destructivos
- **Migraciones de Base de Datos**:
  - **NUNCA** eliminar o renombrar columnas o tablas directamente en una migración. Utilizar siempre el patrón multifase *Expand & Contract* (crear columna -> escritura dual/backfill -> cambiar lectores -> deprecación).
  - Toda nueva columna añadida a tablas con registros existentes debe ser estrictamente `nullable()` o definir un valor por defecto seguro (`default()`).
  - Todo archivo de migración debe incluir un método `down()` funcional y simétrico (eliminar claves foráneas antes de columnas).
- **Integridad Transaccional**:
  - Toda operación que involucre más de un modelo o mutación debe encapsularse en `DB::transaction(function () { ... })`.
  - **PROHIBIDO** despachar correos (`Mail`), notificaciones o llamadas a servicios HTTP externos dentro de la transacción activa. Utilizar siempre `DB::afterCommit(function () { ... })` o eventos encolados.
  - En flujos con alta concurrencia (folios, contadores, estados de solicitudes), utilizar bloqueos pesimistas (`lockForUpdate()`) o métodos atómicos (`increment()`, `decrement()`).
- **Tipado Estricto (PHP 8.4)**:
  - Declarar siempre tipos explícitos para parámetros y valores de retorno en todos los métodos.
  - Promoción de propiedades en constructor (`public function __construct(public readonly Service $service) {}`).
  - Utilizar **BackedEnums** (`string` o `int`) en `App\Enums\` con claves en **TitleCase** para todos los estados, categorías o tipos. Prohibidos los strings mágicos.
  - Declarar casts en modelos utilizando el método `casts(): array`.
- **Manejo de Errores y Seguridad**:
  - Prohibido silenciar excepciones con bloques `catch` vacíos.
  - Registrar logs con contexto enriquecido (`['id' => $model->id, 'action' => 'process']`).
  - Nunca imprimir ni registrar contraseñas, tokens de API o datos sensibles (PII).

### 2. Rendimiento y Escalabilidad
- **Prevención de N+1**:
  - Nunca ejecutar consultas de base de datos dentro de ciclos (`foreach`, `map`).
  - Cargar siempre relaciones de manera ansiosa usando `with([...])`.
  - En tablas de Filament, optimizar las consultas con `getEloquentQuery()->with([...])` o en la configuración de la columna.
- **Protección de Memoria en Grandes Volúmenes**:
  - No utilizar `->all()` ni `->get()` en tablas sin paginación: utilizar `paginate(15)` o `cursorPaginate(15)`.
  - En comandos de consola (`app/Console/Commands/`), jobs o procesos por lotes, utilizar exclusivamente `chunkById()` o `lazyById()`.
- **Asincronía para Tareas Pesadas**:
  - Procesos lentos (envío de correos, generación de PDFs, optimización de medios, webhooks) deben implementar `ShouldQueue` con configuración explícita de `$tries`, `$timeout` y `$backoff`.

### 3. Arquitectura Limpia y Separación de Capas
- **Controladores Delgados (*Thin Controllers*)**:
  - Los controladores y recursos Filament solo validan (mediante `FormRequest`), autorizan (vía Policies) y delegan la lógica a clases en `app/Services/`.
  - Prohibido acumular lógica de negocio compleja dentro de closures de rutas, controladores o vistas.
- **Validación Robusta**:
  - Crear clases `FormRequest` en `app/Http/Requests/` para todas las mutaciones `POST`, `PUT`, `PATCH`, `DELETE`.
  - Centralizar nombres de atributos y mensajes en `lang/es/validation.php`.

### 4. Frontera Frontend Crítica (Inertia v3 vs Vue Islands)
- **Páginas Inertia SPA (`resources/js/pages/**`)**:
  - Ejecutan en el contexto de Inertia.
  - Utilizar `useForm` de `@inertiajs/vue3`, `router.visit`, `page.props`.
- **Islas Vue en Blade (`resources/views/landing/**`, `resources/js/components/**`)**:
  - **CRÍTICO**: NUNCA utilizar `useForm()` de Inertia ni `router` en islas montadas sobre Blade. Generan fallo fatal (`TypeError: Cannot read properties of undefined (reading 'url')`).
  - **OBLIGATORIO**: Utilizar siempre el composable `useIslandForm` (`import { useIslandForm } from '@/composables/useIslandForm'`).
- **UI & Accesibilidad**:
  - Un único elemento raíz por componente Vue.
  - Utilizar componentes DaisyUI 5 y utilidades de Tailwind CSS v4.
  - Deshabilitar botones y mostrar estados de carga mientras `form.processing` sea activo.

### 5. Localización Obligatoria en Filament (Español)
- Todo el panel Filament (`/portal`) debe estar estrictamente en español:
  - Recursos: `$modelLabel`, `$pluralModelLabel` (minúsculas), `$navigationLabel`, `$breadcrumb`, `$navigationGroup`.
  - Páginas: `$title` estático en español.
  - Formularios, infolists, columnas y filtros de tablas: `->label('...')` explícito en español.

### 6. Disciplina de Pruebas y Formateo
- **Pruebas con Pest**:
  - Todo cambio o nueva característica debe contar con su prueba en `tests/Feature/`.
  - Probar ruta feliz, validaciones fallidas, autenticación/autorización (401/403) y comportamiento transaccional.
  - Emplear Model Factories con estados semánticos (`User::factory()->admin()->create()`).
- **Formateo de Código**:
  - Ejecutar `vendor/bin/pint --dirty --format agent` tras modificar archivos PHP.

---
Para especificaciones detalladas por área de código, consultar el índice de reglas en `.ai/rules/index.md`.
