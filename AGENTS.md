<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Test every code change by adding or updating a test.
- Run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

=== vue-islands rules ===

# Vue Islands Architecture (Blade + Vue Islands)

Public landing pages (`resources/views/landing/**`) are rendered as server-side Blade views for SEO and social media previews, mounting Vue components as interactive islands (`resources/js/island.ts`).

- **CRITICAL**: Never use Inertia's `useForm()` or Inertia routing helpers (`router.visit`, `page.props`) in Vue components mounted inside Blade islands. They will fail with `TypeError: Cannot read properties of undefined (reading 'url')` because there is no Inertia root context.
- **Form Composable**: Always use `useIslandForm` from `resources/js/composables/useIslandForm.ts` (`import { useIslandForm } from '@/composables/useIslandForm'`) for any form in a Vue island.
- `useIslandForm` handles CSRF token extraction, Laravel 422 error mapping to `form.errors`, and reactive submission state (`form.processing`, `form.statusMessage`).
- When creating a new island, mount it in Blade using: `<div data-vue="component-name" data-props="{{ json_encode([...]) }}"></div>`.

=== filament-localization rules ===

# Filament Localization (Español)

Todos los campos, formularios, tablas, filtros, acciones, títulos y nombres en `app/Filament/**` y `app/Providers/Filament/**` deben estar obligatoriamente en español.

- **Recursos (`Resource`)**:
  - Definir siempre `protected static ?string $modelLabel = 'Nombre Singular';`
  - Definir siempre `protected static ?string $pluralModelLabel = 'nombres plurales';` (en minúsculas)
  - Definir siempre `protected static ?string $navigationLabel = 'Nombre en Navegación';`
  - Definir siempre `protected static ?string $breadcrumb = 'Nombre en Breadcrumb';`
  - Asignar un grupo de navegación en español (`'Comunicación'`, `'Transparencia y Finanzas'`, o `'Administración'`).
- **Páginas de Recursos (`Pages`)**:
  - Definir siempre la propiedad estática `protected static ?string $title = '...';` en español en clases `ListRecords`, `CreateRecord`, `EditRecord` y `ViewRecord`.
- **Campos de Formulario y Entradas de Infolist**:
  - Nunca omitir `->label('...')` en campos (`TextInput`, `Select`, `Textarea`, `RichEditor`, `DatePicker`, `FileUpload`, `Toggle`, etc.) a menos que sea un campo oculto (`Hidden`).
  - Al registrar nuevos atributos en modelos, agregar su traducción al arreglo `'attributes'` en `lang/es/validation.php`.
- **Columnas y Filtros de Tablas**:
  - Asignar siempre `->label('...')` explícito en español en todas las columnas, incluidas las de auditoría (`created_at` -> `'Fecha de Creación'`, `updated_at` -> `'Fecha de Actualización'`, `deleted_at` -> `'Fecha de Eliminación'`).
- **Navegación del Panel**:
  - Mantener los grupos de navegación organizados en español en `PortalPanelProvider::navigationGroups([...])`.

=== senior-engineering-rules ===

# Senior Engineering Rules (Consistencia, Escalabilidad y Robustez)

Estas reglas son de estricto cumplimiento para cualquier agente de IA o desarrollador en este proyecto para garantizar cero regresiones, alta escalabilidad y código robusto. Consulta `.ai/rules/index.md` para las reglas detalladas por dominio.

## 1. Cero Regresiones y Protección de Datos ("Evitar Romper Cosas")
- **Transacciones e Integridad**:
  - Toda mutación que involucre más de un registro debe estar envuelta en `DB::transaction(fn () => ...)`.
  - **Prohibido** disparar correos, notificaciones o llamadas a servicios externos dentro de la transacción activa: usar siempre `DB::afterCommit(fn () => ...)` o eventos encolados.
  - Usar bloqueos pesimistas (`lockForUpdate()`) o métodos atómicos (`increment()`, `decrement()`) en operaciones concurrentes críticas (folios, estados, contadores).
- **Migraciones No Destructivas**:
  - Prohibido eliminar o renombrar columnas directamente en una sola migración. Usar el patrón *Expand & Contract*.
  - Toda nueva columna en tablas existentes con datos debe ser `nullable()` o definir un `default()` seguro.
  - Implementar siempre el método `down()` reversible y simétrico (descartar foreign keys antes de columnas).

## 2. Escalabilidad y Rendimiento de Consultas
- **Prevención Estricta de Consultas N+1**:
  - Prohibido ejecutar consultas dentro de ciclos (`foreach`, `map`). Usar siempre carga ansiosa con `with(['relacion'])`.
  - En tablas de Filament, optimizar consultas usando `getEloquentQuery()->with([...])` o en las columnas de relación.
- **Manejo Seguro de Memoria en Grandes Volúmenes**:
  - Prohibido el uso de `->get()` o `->all()` en colecciones ilimitadas: usar paginación (`paginate(15)` o `cursorPaginate(15)`).
  - En comandos CLI o tareas en segundo plano, procesar registros en lotes usando exclusivamente `chunkById()` o `lazyById()`.
- **Desacoplamiento y Tareas en Cola**:
  - Cualquier operación lenta (emails, PDFs, procesamiento de medios, llamadas HTTP externas) debe implementar `ShouldQueue` con configuración explícita de `$tries`, `$timeout` y `$backoff`.

## 3. Arquitectura Limpia y Tipado Estricto (PHP 8.4)
- **Separación de Responsabilidades**:
  - Controladores y recursos Filament delgados (*thin controllers*): solo validan, autorizan, delegan la lógica a clases en `app/Services/` y retornan respuestas.
  - La validación de mutaciones debe realizarse en clases `FormRequest` dedicadas en `app/Http/Requests/`.
- **Tipado Fuerte y Enums**:
  - Tipos explícitos obligatorios en parámetros y valores de retorno de todos los métodos.
  - Prohibido el uso de strings mágicos para estados, tipos o roles: usar `BackedEnum` en `app/Enums/` con claves en `TitleCase`.
  - Casts de modelos definidos mediante el método `casts(): array`.
- **Manejo de Errores**:
  - Prohibido silenciar excepciones con bloques `catch` vacíos. Registrar errores con contexto descriptivo sin exponer datos sensibles ni credenciales.

## 4. Frontend: Frontera Inertia v3 vs Islas Vue en Blade
- **Páginas Inertia SPA (`resources/js/pages/**`)**:
  - Usar contexto de Inertia (`useForm` de `@inertiajs/vue3`, `router.visit`, `page.props`).
- **Islas Vue en Blade (`resources/views/landing/**` y `resources/js/components/**`)**:
  - **CRÍTICO**: NUNCA usar `useForm()` de Inertia ni `router` en islas Vue montadas sobre Blade. Causa error fatal de ejecución.
  - **OBLIGATORIO**: Usar siempre `useIslandForm` de `@/composables/useIslandForm`.
- **UI**:
  - Un único elemento raíz por componente Vue.
  - Emplear componentes y temas de DaisyUI 5 con utilidades de Tailwind CSS v4.
  - Deshabilitar botones de envío durante `form.processing`.

## 5. Disciplina de Pruebas y Calidad de Código
- **Pruebas Obligatorias con Pest**:
  - Todo cambio, nueva característica o corrección de bug debe acompañarse de su prueba en `tests/Feature/`.
  - Probar siempre los 4 escenarios esenciales: ruta feliz (*happy path*), fallos de validación, control de acceso/autorización (401/403) y transaccionalidad.
  - Utilizar Model Factories con estados en lugar de datos estáticos o seeders en pruebas.
- **Formateo Automático con Pint**:
  - Todo archivo PHP modificado o creado debe pasar por `vendor/bin/pint --dirty --format agent`.

</laravel-boost-guidelines>

