# Plan: KYC Document Review — Completar Funcionalidad

## Overview

El recurso `UserResource` ya cuenta con una implementación avanzada del flujo de revisión KYC:
- Tabla con botón "Revisar" que navega a la página `ReviewDocuments`
- Página `ReviewDocuments` (extiende `ViewRecord`) con pestañas por tipo de documento, layout split, infolist de comparación de campos, y acciones por documento (Rechazar / Autorizado / Reemplazar / Abrir)
- Schema `DocumentReviewInfolist` con `helperText` en cada campo mostrando si coincide o no con el documento

**Lo que falta implementar:**
1. Ordenamiento alfabético por defecto en la tabla (`defaultSort`)
2. Navegación Anterior / Siguiente en el header de `ReviewDocuments`

---

## Sub-Tasks

---

### Sub-Task 1 — Ordenamiento alfabético por defecto en la tabla

**Intent:** La tabla de usuarios debe aparecer ordenada alfabéticamente por apellidos (y luego nombre) por defecto, sin que el usuario tenga que hacer clic en ninguna columna.

**Expected Outcomes:**
- Al abrir `/portal/users`, los registros están ordenados `surnames ASC`, con `name ASC` como criterio secundario.
- Las columnas `surnames` y `name` siguen siendo sortables manualmente por el usuario.

**Todo List:**
- [ ] En `app/Filament/Resources/Users/Tables/UsersTable.php`, agregar `->defaultSort('surnames')` seguido de `->defaultSort('name')` sobre el método `configure()`, encadenado en la construcción del `Table`.

**Relevant Context:**
- Archivo: [`app/Filament/Resources/Users/Tables/UsersTable.php`](app/Filament/Resources/Users/Tables/UsersTable.php)
- Filament 5 soporta múltiples `defaultSort()` encadenados o un solo `->defaultSort('column', 'asc')`. Verificar con `search-docs` la sintaxis exacta de Filament 5 antes de aplicar.
- La columna `surnames` ya está definida como `->searchable()` pero no como `->sortable()`. Confirmar si necesita `->sortable()` agregado para que `defaultSort` funcione correctamente.

**Status:** `[ ] pending`

---

### Sub-Task 2 — Navegación Anterior / Siguiente en ReviewDocuments

**Intent:** Dentro de la página `ReviewDocuments`, el administrador puede pasar al siguiente o al anterior usuario (en orden alfabético) sin regresar a la tabla. Los botones "Anterior" y "Siguiente" aparecen en `getHeaderActions()`.

**Expected Outcomes:**
- Dos acciones en el header: "← Anterior" (deshabilitado si es el primero) y "Siguiente →" (deshabilitado si es el último).
- Al hacer clic navegan a la URL `/{previousRecord}/review-documents` o `/{nextRecord}/review-documents` respectivamente.
- El orden usado es el mismo que el de la tabla: `surnames ASC`, `name ASC`.

**Todo List:**
- [ ] En `app/Filament/Resources/Users/Pages/ReviewDocuments.php`, agregar dos métodos privados:
  - `getPreviousRecord(): ?User` — consulta el User con (surnames, name) inmediatamente anterior en orden alfabético al registro actual.
  - `getNextRecord(): ?User` — consulta el User con (surnames, name) inmediatamente posterior en orden alfabético al registro actual.
- [ ] Ambos métodos deben excluir usuarios eliminados (soft-deleted) usando el mismo scope de `getRecordRouteBindingEloquentQuery` o `User::query()`.
- [ ] En `getHeaderActions()`, antes de `EditAction::make()`, agregar:
  - `Action::make('previousRecord')` con label "Anterior", ícono `heroicon-m-chevron-left`, `->url()` que apunta a `ReviewDocuments` del registro previo, `->disabled()` si `getPreviousRecord()` es null.
  - `Action::make('nextRecord')` con label "Siguiente", ícono `heroicon-m-chevron-right`, `->url()` que apunta a `ReviewDocuments` del registro siguiente, `->disabled()` si `getNextRecord()` es null.
- [ ] Verificar con `search-docs` el API correcto de Filament 5 para `Action::make()->url()->disabled()` en header actions de `ViewRecord`.

**Relevant Context:**
- Archivo a modificar: [`app/Filament/Resources/Users/Pages/ReviewDocuments.php`](app/Filament/Resources/Users/Pages/ReviewDocuments.php)
- `getHeaderActions()` ya existe con `EditAction`, `setDocumentValues`, y `approveAllDocuments`.
- `UserResource::getUrl('review-documents', ['record' => $record])` es el patrón ya usado en `UsersTable` para generar la URL.
- El modelo `User` usa soft-deletes; la query de navegación debe usar `User::query()` (que ya excluye soft-deleted por defecto via `SoftDeletingScope` en el modelo).
- La comparación de registros para "anterior/siguiente" puede implementarse con condiciones `WHERE` compuestas sobre `(surnames, name, id)` para manejar empates correctamente.

**Status:** `[ ] pending`

---

## Notes for Implementation

- Ejecutar `vendor/bin/pest` después de cada sub-task para verificar que no se rompen pruebas existentes.
- Ejecutar `vendor/bin/pint --dirty --format agent` sobre los archivos PHP modificados.
- No agregar nuevas dependencias ni modificar la estructura de directorios existente.
