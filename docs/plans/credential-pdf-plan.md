# Plan: Credencial PDF + Credencial Digital En Vivo

## Resumen

Implementar dos cosas relacionadas con la credencial de afiliado SIUT-ITSM:

1. **PDF de credencial** — Blade view + Action usando DomPDF, con el diseño proporcionado (anverso y reverso en tarjeta 54mm×85.6mm). Descargable desde "Mi Perfil" (agremiado) y desde la página de revisión de documentos del admin.
2. **Credencial Digital En Vivo** — Actualizar el componente Vue `credential-preview.vue` para que refleje visualmente el anverso del PDF (tarjeta PVC, arcos, foto circular, logos), usando daisyUI. Sigue recibiendo los mismos props que hoy.

**Decisiones de diseño fijadas:**
- `imss` en el HTML del diseño = campo `nss` del User
- `vigencia` = calculado en el Action: `hiring_date->addYear()` o año actual si no hay `hiring_date`
- QR del reverso = URL del sitio (`config('app.url')`), sin ruta de verificación real (ponytail)
- Imagen de fondo de arcos: `public/assets/images/background-id.png`
- Logos disponibles: `public/assets/img/logo/logo-tecnm.png`, `logo-itsm.jpg`, `logo-siutitsm.jpg`
- Logos de organización: `public/assets/images/logo.webp` (SIUT principal)
- QR se genera en el Action usando el paquete `endroid/qr-code` ya disponible o `simple-qrcode` — verificar; si no hay, el QR es un placeholder vacío con `ponytail:` comment

---

## Sub-tarea 1 — Blade View `pdf/credential.blade.php`

**Intent:** Crear la vista Blade que renderiza el HTML de la credencial (anverso y reverso) traduciendo el diseño Smarty al sistema Blade+PHP del proyecto.

**Expected Outcomes:**
- `resources/views/pdf/credential.blade.php` existe con el diseño fiel al HTML proporcionado
- Variables Smarty (`{$holder['fullName']}` etc.) convertidas a variables Blade (`{{ $holder['fullName'] }}`)
- Condicionales `{if}` convertidos a `@if`
- CSS idéntico al diseño original (sin Tailwind — DomPDF no lo soporta)
- `background-image` de arcos usa `public_path()` convertido a data URI o path absoluto (requerido por DomPDF para imágenes locales)
- Los logos TecNM, ITSM y SIUT se pasan como paths absolutos desde el Action

**Todo:**
- [x] Crear `resources/views/pdf/credential.blade.php`
- [x] Convertir sintaxis Smarty → Blade (variables, condicionales)
- [x] Resolver paths de imágenes: usar `$backgroundImagePath` (path absoluto) y convertir a base64 data URI inline para compatibilidad DomPDF
- [x] Mantener el CSS original tal cual — no usar Tailwind ni clases externas

**Relevant Context:**
- Diseño fuente: el HTML Smarty provisto por el usuario en el chat
- Patrón existente: [`resources/views/pdf/petition-voucher.blade.php`](resources/views/pdf/petition-voucher.blade.php) — usa `@extends('document.layout')` pero la credencial NO debe usar ese layout (tiene su propio layout de tarjeta)
- DomPDF requiere imágenes como paths absolutos o base64 embebido — ver cómo lo hace [`GenerateLoanSimulationPdf`](app/Actions/GenerateLoanSimulationPdf.php:33) con `public_path('assets/images/logo.webp')`

**Status:** `[x] completed`

---

## Sub-tarea 2 — Action `GenerateUserCredentialPdf`

**Intent:** Crear el Action que recopila todos los datos del User y los pasa a la vista Blade, generando la respuesta de descarga.

**Expected Outcomes:**
- `app/Actions/GenerateUserCredentialPdf.php` existe
- El método `execute(User $user): Response` devuelve un PDF descargable nombrado `credencial-{curp}.pdf`
- Todos los datos del holder se mapean correctamente desde el modelo User
- `vigencia` = `$user->hiring_date?->addYear()->year ?? now()->year`
- `imss` = `$user->nss`
- `signatory` = datos hardcodeados del sindicato (secretario general) — no hay modelo de firmante
- `organization` = `config('syndicate.name')`
- `website` = `config('app.url')`
- `rfc` = vacío o config (no hay RFC en el modelo User ni en syndicate config)
- `address` = `config('syndicate.address')`
- `issuedAt` = `now()->format('d/m/Y')`
- Logos pasados como paths absolutos: `public_path('assets/img/logo/logo-tecnm.png')`, etc.
- QR = imagen del QR de `config('app.url')` generada inline, o placeholder si no hay paquete QR
- `$theme` = mapeado desde `DocumentThemePalette::resolve()` a los keys que usa el diseño (`primary`, `secondary`, `text`, `muted`, `border`, `surface`, `background`, `onPrimary`, `onSecondary`)

**Todo:**
- [x] Crear `app/Actions/GenerateUserCredentialPdf.php`
- [x] Inyectar `DocumentThemePalette` en el constructor (patrón existente)
- [x] Mapear `DocumentThemePalette::resolve()` → array `$theme` con los keys del diseño HTML
- [x] Resolver imagen de foto del usuario: `Storage::disk('public')->path($user->photo_path)` o placeholder
- [x] Verificar si hay paquete QR instalado (`composer show --direct` / `package.json`); si no, usar placeholder vacío con `ponytail:` comment
- [x] Usar `DomPdf::loadView('pdf.credential', [...])→download("credencial-{$user->curp}.pdf")`

**Relevant Context:**
- Patrón: [`app/Actions/GenerateLoanSimulationPdf.php`](app/Actions/GenerateLoanSimulationPdf.php) — inyecta `DocumentThemePalette`, usa `DomPdf::loadView`
- [`app/Support/DocumentThemePalette.php`](app/Support/DocumentThemePalette.php:26) — keys devueltos: `primary`, `primary_content`, `secondary`, `base_100`, `base_200`, `base_300`, `base_content`
- [`config/syndicate.php`](config/syndicate.php) — `name`, `address`, `email`, `phone`
- [`app/Models/User.php`](app/Models/User.php) — campos: `name`, `surnames`, `curp`, `nss`, `category`, `photo_path`, `hiring_date`, `address`

**Status:** `[x] completed`

---

## Sub-tarea 3 — Botón de descarga en "Mi Perfil" (Filament — agremiado)

**Intent:** Agregar un botón "Descargar Credencial" en la página de perfil del agremiado (`Profile.php`) que invoque el Action y descargue el PDF.

**Expected Outcomes:**
- El botón aparece en `getHeaderActions()` o como acción destacada en la sección de perfil
- Solo visible si el usuario tiene documentos válidos (`$user->areAllDocumentsValid()`)
- Al hacer clic descarga directamente `credencial-{curp}.pdf`

**Todo:**
- [x] Añadir `Action::make('downloadCredential')` en `app/Filament/Pages/Profile.php`
- [x] Usar `->action()` con `return app(GenerateUserCredentialPdf::class)->execute($user)`
- [x] Condicionar visibilidad con `->visible(fn () => $this->getUser()->areAllDocumentsValid())`
- [x] Ícono `Heroicon::IdentificationCard` o similar

**Relevant Context:**
- [`app/Filament/Pages/Profile.php`](app/Filament/Pages/Profile.php:28) — extiende `BaseEditProfile`; no tiene `getHeaderActions()` todavía
- Patrón de actions en Filament pages: ver [`app/Filament/Resources/Users/Pages/ReviewDocuments.php:56`](app/Filament/Resources/Users/Pages/ReviewDocuments.php:56)

**Status:** `[x] completed`

---

## Sub-tarea 4 — Botón de descarga en ReviewDocuments (Filament — admin)

**Intent:** Agregar un botón "Descargar Credencial" en los header actions de `ReviewDocuments.php` para que el admin pueda descargar la credencial de cualquier usuario.

**Expected Outcomes:**
- Botón "Descargar Credencial" en `getHeaderActions()` de `ReviewDocuments`
- Visible siempre (el admin puede querer una preview incluso sin docs validados)
- Descarga `credencial-{curp}.pdf`

**Todo:**
- [x] Añadir `Action::make('downloadCredential')` en [`app/Filament/Resources/Users/Pages/ReviewDocuments.php`](app/Filament/Resources/Users/Pages/ReviewDocuments.php)
- [x] Invocar `app(GenerateUserCredentialPdf::class)->execute($this->getRecord())`
- [x] Colocar después del botón "Editar perfil" en el array de actions

**Relevant Context:**
- [`app/Filament/Resources/Users/Pages/ReviewDocuments.php:56`](app/Filament/Resources/Users/Pages/ReviewDocuments.php:56) — array de `getHeaderActions()` existente

**Status:** `[x] completed`

---

## Sub-tarea 5 — Actualizar `credential-preview.vue` (Credencial Digital En Vivo)

**Intent:** Rediseñar el componente Vue de la credencial en vivo para que visualmente imite el anverso del PDF — tarjeta con proporciones PVC (aspect ratio ~0.63), foto circular con borde de color primario, arcos superior e inferior usando la imagen `background-id.png`, logos al pie, datos del agremiado — usando daisyUI v5.

**Expected Outcomes:**
- `credential-preview.vue` renderiza una tarjeta visualmente idéntica al anverso del PDF
- Props idénticos a los actuales (no rompe la integración con `step-documents.vue` y `register-form.vue`)
- Versión desktop (sticky, `lg:block`) y versión móvil colapsable (`lg:hidden`) ambas actualizadas
- Usa clases daisyUI v5 para contenedores, badges, y tipografía
- Arcos como `<img>` absolutas con la URL de `background-id.png` (desde Vite asset o ruta pública)
- Logos pasados como props opcionales (con defaults a las rutas públicas)
- Scanning effect (`isScanning`) conservado

**Todo:**
- [x] Leer guías daisyUI relevantes: `card`, `badge`, `avatar`
- [x] Rediseñar `credential-preview.vue` con la estructura del anverso del PDF
- [x] Arcos: `<img src="/assets/images/background-id.png" />` con `class="absolute inset-x-0 top-0"` y versión invertida para el pie
- [x] Foto circular: `size-24 rounded-full border-[3px] border-primary`
- [x] Logos al pie como `<img>` con dimensiones fijas, pasados como props opcionales con defaults
- [x] Conservar lógica de `isExpandedMobile` para móvil
- [x] Conservar efecto `isScanning`

**Relevant Context:**
- [`resources/js/components/registro/credential-preview.vue`](resources/js/components/registro/credential-preview.vue:1) — componente actual a reemplazar
- Props actuales: `name`, `surnames`, `curp`, `nss`, `category`, `photoUrl`, `isScanning`
- [`resources/js/components/registro/step-documents.vue`](resources/js/components/registro/step-documents.vue) — usa `<credential-preview>`, no debe cambiar su API

**Status:** `[x] completed`

---

## Sub-tarea 6 — Tests

**Intent:** Verificar que el Action genera un PDF sin errores y que el botón de descarga existe en ambas páginas Filament.

**Expected Outcomes:**
- Test que verifica que `GenerateUserCredentialPdf::execute()` devuelve una respuesta con `Content-Type: application/pdf`
- Test que verifica que el botón aparece en el perfil del agremiado
- Test que verifica que el botón aparece en `ReviewDocuments`

**Todo:**
- [x] Crear `tests/Feature/GenerateUserCredentialPdfTest.php`
- [x] Test: action genera PDF para user con datos completos
- [x] Test: botón "Descargar Credencial" visible en perfil si docs válidos
- [x] Test: botón visible en ReviewDocuments

**Relevant Context:**
- Patrón de tests Filament en este proyecto: buscar tests existentes en `tests/Feature/`
- Usar `User::factory()` para crear usuarios de prueba
- Activar skill `testing-best-practices` al implementar

**Status:** `[x] completed`

---

## Notas de implementación

### Mapping de `$theme` (DocumentThemePalette → diseño HTML)

```
$palette = DocumentThemePalette::resolve()

$theme = [
    'primary'      => $palette['primary'],
    'secondary'    => $palette['secondary'],
    'text'         => $palette['base_content'],
    'muted'        => $palette['base_300'],
    'border'       => $palette['base_300'],
    'surface'      => $palette['base_100'],
    'background'   => $palette['base_200'],
    'onPrimary'    => $palette['primary_content'],
    'onSecondary'  => $palette['secondary_content'],
]
```

### Datos del firmante (hardcoded, ponytail)

```php
// ponytail: firmante hardcodeado, crear modelo Signatory cuando haya requisito real
$signatory = [
    'name' => 'Secretario General',
    'role' => 'SIUT-ITSM',
    'signatureHash' => substr(hash('sha256', $user->curp . $user->id), 0, 32),
]
```

### QR placeholder

```php
// ponytail: QR apunta al sitio; ruta /verificar/{token} es scope separado
$qrSrc = ''; // o generar QR de config('app.url') si hay paquete instalado
```

### Imágenes en DomPDF — base64

DomPDF carga mejor imágenes locales como base64 embebidas. Usar helper:

```php
function imageToBase64(string $path): string {
    $type = pathinfo($path, PATHINFO_EXTENSION);
    $data = base64_encode(file_get_contents($path));
    return "data:image/{$type};base64,{$data}";
}
```
