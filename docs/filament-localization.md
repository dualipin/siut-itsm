# Estándar y Convenciones de Localización al Español en Filament

Este documento describe la arquitectura, configuración y convenciones obligatorias para mantener todas las interfaces, campos, formularios, tablas, páginas y mensajes del panel administrativo de Filament (`/portal`) 100% en español en el portal SIUT.

---

## 1. Configuración Global de Laravel

En [config/app.php](file:///c:/Users/dualipin/Projects/ost-siut-itsm-laravel/config/app.php) se definen los valores de idioma y fallback predeterminados en español:

```php
'locale' => env('APP_LOCALE', 'es'),
'fallback_locale' => env('APP_FALLBACK_LOCALE', 'es'),
'faker_locale' => env('APP_FAKER_LOCALE', 'es_MX'),
```

Archivos de idioma base en `lang/es/`:
- **`lang/es/validation.php`**: Reglas de validación traducidas y diccionario `attributes` para que los errores de formularios se muestren con nombres legibles en español (ej. *«El campo título es obligatorio.»*).
- **`lang/es/auth.php`**: Mensajes de credenciales y límite de intentos.
- **`lang/es/pagination.php`**: Etiquetas de paginación (`« Anterior`, `Siguiente »`).
- **`lang/es/passwords.php`**: Mensajes de restablecimiento de contraseña.

---

## 2. Grupos de Navegación en la Barra Lateral

En [PortalPanelProvider.php](file:///c:/Users/dualipin/Projects/ost-siut-itsm-laravel/app/Providers/Filament/PortalPanelProvider.php), la barra lateral se agrupa temáticamente en español mediante `navigationGroups`:

| Grupo de Navegación | Recursos / Páginas incluidos |
| :--- | :--- |
| **Comunicación** | Mensajería (`Messages`), Buzón de Contacto (`ContactSubmissionResource`), Dudas y Consultas (`InquiryResource`), Publicaciones (`PostResource`) |
| **Transparencia y Finanzas** | Transparencia (`TransparencyRecordResource`), Reportes Financieros (`FinancialReportResource`) |
| **Administración** | Padrón de Usuarios (`UserResource`), Apariencia (`ThemeResource`) |

---

## 3. Convenciones para Recursos Filament (`Resource`)

Todo recurso debe definir explícitamente sus propiedades de etiqueta en español:

```php
use Filament\Resources\Resource;
use UnitEnum;

class EjemploResource extends Resource
{
    // Grupo en la barra lateral
    protected static UnitEnum|string|null $navigationGroup = 'Transparencia y Finanzas';

    // Etiqueta en el menú de navegación
    protected static ?string $navigationLabel = 'Ejemplos';

    // Miga de pan (Breadcrumb)
    protected static ?string $breadcrumb = 'Ejemplos';

    // Nombre en singular (usado en acciones automáticas como "Crear Ejemplo")
    protected static ?string $modelLabel = 'Ejemplo';

    // Nombre en plural en minúsculas (usado por Filament para encabezados y conteos)
    protected static ?string $pluralModelLabel = 'ejemplos';
}
```

---

## 4. Convenciones para Páginas de Recursos (`Pages`)

Cada página generada dentro de un recurso debe definir su `$title` en español:

- **ListRecords**:
  ```php
  protected static ?string $title = 'Reportes Financieros';
  ```
- **CreateRecord**:
  ```php
  protected static ?string $title = 'Nuevo Reporte Financiero';
  ```
- **EditRecord**:
  ```php
  protected static ?string $title = 'Editar Reporte Financiero';
  ```
- **ViewRecord**:
  ```php
  protected static ?string $title = 'Detalle del Reporte Financiero';
  ```

---

## 5. Convenciones para Columnas de Tablas y Entradas de Infolists

Todas las columnas, incluidas las de auditoría (`created_at`, `updated_at`, `deleted_at`), deben tener su `->label()` explícito en español:

```php
TextColumn::make('created_at')
    ->label('Fecha de Creación')
    ->dateTime('d/m/Y H:i')
    ->sortable(),

TextColumn::make('updated_at')
    ->label('Fecha de Actualización')
    ->dateTime('d/m/Y H:i')
    ->sortable(),

TextColumn::make('deleted_at')
    ->label('Fecha de Eliminación')
    ->dateTime('d/m/Y H:i')
    ->sortable(),
```

---

## 6. Convenciones para Campos de Formularios (`Schemas` / `Forms`)

Ningún campo de formulario debe omitir su `->label()`, salvo que sea un campo oculto (`Hidden`):

```php
TextInput::make('slug')
    ->label('Identificador URL (Slug)')
    ->required(),

RichEditor::make('content')
    ->label('Contenido de la Publicación')
    ->required(),
```

Cuando se agregue un nuevo campo a un modelo, también debe agregarse su traducción al arreglo `'attributes'` en [lang/es/validation.php](file:///c:/Users/dualipin/Projects/ost-siut-itsm-laravel/lang/es/validation.php).

---

## 7. Pruebas Automatizadas

El archivo [tests/Feature/FilamentLocalizationTest.php](file:///c:/Users/dualipin/Projects/ost-siut-itsm-laravel/tests/Feature/FilamentLocalizationTest.php) asegura de forma automatizada que:
1. Las configuraciones de locale apunten a `'es'`.
2. Todos los recursos tengan sus etiquetas en singular y plural en español.
3. Las páginas conserven sus títulos en español.
4. Los mensajes de validación traduzcan sus atributos correctamente.
