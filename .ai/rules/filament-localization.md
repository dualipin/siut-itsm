# Regla: Localización y Campos en Español en Filament

**Rutas cubiertas**: `app/Filament/**`, `app/Providers/Filament/**`, `lang/es/**`

## Contexto y Restricción Obligatoria

En todas las páginas, recursos, esquemas, tablas, formularios, filtros, infolists y mensajes del panel de Filament (`/portal`), todos los campos y nombres que interactúan con el usuario deben estar estrictamente en español.

## Directrices

1. **Recursos de Filament (`Resource`)**:
   - `protected static ?string $modelLabel = 'Nombre Singular';`
   - `protected static ?string $pluralModelLabel = 'nombres plurales';` (en minúsculas para compatibilidad con encabezados y contadores de Filament).
   - `protected static ?string $navigationLabel = 'Nombre Navegación';`
   - `protected static ?string $breadcrumb = 'Nombre Breadcrumb';`
   - Asignar grupo temático: `protected static UnitEnum|string|null $navigationGroup = '...';` (`'Comunicación'`, `'Transparencia y Finanzas'`, o `'Administración'`).

2. **Páginas (`Pages`)**:
   - Definir siempre `protected static ?string $title = '...';` en clases `ListRecords`, `CreateRecord`, `EditRecord` y `ViewRecord`.

3. **Campos de Formulario y Entradas de Infolist**:
   - Ningún campo debe carecer de `->label('...')` explícito en español (salvo campos técnicos como `Hidden`).
   - Todo nuevo atributo de modelo debe registrar su nombre traducido en `lang/es/validation.php` dentro del arreglo `'attributes'`.

4. **Columnas de Tablas y Filtros**:
   - Columnas de fecha y auditoría deben contar con etiquetas explícitas:
     - `created_at`: `'Fecha de Creación'`
     - `updated_at`: `'Fecha de Actualización'`
     - `deleted_at`: `'Fecha de Eliminación'`
   - Filtros (`SelectFilter`, `Filter`) deben definir `->label('...')` en español.

5. **Pruebas de Regresión**:
   - Cada nuevo recurso debe verificarse mediante pruebas en `tests/Feature/FilamentLocalizationTest.php`.
