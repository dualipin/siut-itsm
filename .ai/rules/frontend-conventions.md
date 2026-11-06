# Regla: Fronteras de Frontend, Dualidad de Ecosistemas de Diseño, Inertia v3 y DaisyUI

**Rutas cubiertas**: `resources/js/**`, `resources/views/**`, `resources/views/landing/**`, `resources/views/filament/**`

## 1. Frontera de Ecosistemas de Diseño: DaisyUI Exclusivo de Landing vs Filament

Esta aplicación cuenta con dos ecosistemas de UI independientes e incompatibles:

### Ecosistema A: Landing Pública (`resources/views/landing/**` e Islas Vue)
- **Framework de componentes**: **daisyUI 5** sobre Tailwind CSS v4.
- **Uso exclusivo**: Clases semánticas de DaisyUI (`btn`, `btn-primary`, `card`, `alert`, `badge`, `modal`, `input`, `join`, etc.) están **restringidas únicamente a la Landing**.
- **Tema**: Consume variables del tema `siut` inyectadas desde base de datos (`--siut-color-*`).

### Ecosistema B: Paneles Filament (`app/Filament/**`, `resources/views/filament/**`)
- **Framework de componentes**: Filament v4 / Livewire con su propia arquitectura de diseño y componentes Blade nativos.
- **PROHIBICIÓN ESTRICTA**:
  - **NUNCA** introducir clases ni componentes de DaisyUI (`btn`, `card`, `badge`, `alert`, `join`, `table`, etc.) en vistas, recursos o páginas de Filament.
  - La filosofía de estilos de Filament es incompatible con DaisyUI; mezclar clases corrompe el diseño de modales, tablas, formularios y acciones de Filament.
- **Cohesión Cromática**: Filament utiliza su propia configuración de tema cargando la paleta de colores institucionales desde la base de datos (tema `siut`), garantizando identidad visual uniforme sin contaminar el DOM con clases externas.

---

## 2. Frontera de Framework: Inertia v3 SPA vs Islas Vue (Blade)

### Contexto A: Páginas Inertia SPA (`resources/js/pages/**`)
- Renderizadas por el servidor mediante `Inertia::render('PageName', [...])`.
- Cuentan con el contexto raíz de Inertia.
- **Permitido**: Utilizar `useForm` de `@inertiajs/vue3`, `router.visit`, `usePage()`.
- **Inertia v3**: Axios ha sido removido del core de Inertia v3; utilizar el cliente XHR integrado o `fetch`.

### Contexto B: Islas Vue en Blade (`resources/views/landing/**` y `resources/js/components/**`)
- Vistas públicas Blade para SEO y previsualizaciones sociales, que montan componentes interactivos individuales mediante `resources/js/island.ts`.
- **PROHIBICIÓN CRÍTICA**:
  - **NUNCA** importar ni invocar `useForm()` de `@inertiajs/vue3`, `router.visit()`, ni `usePage()` en componentes montados como islas.
  - Provocan inmediatamente el fallo fatal: `TypeError: Cannot read properties of undefined (reading 'url')` debido a la ausencia de contexto Inertia.
- **USO OBLIGATORIO DE `useIslandForm`**:
  - Todo formulario en una isla Vue debe utilizar el composable `useIslandForm`:
    ```ts
    import { useIslandForm } from '@/composables/useIslandForm';

    const form = useIslandForm({
        name: '',
        email: '',
        message: '',
    });

    const submit = () => {
        form.post('/contacto', {
            onSuccess: () => { /* reset */ },
        });
    };
    ```
  - `useIslandForm` extrae automáticamente el token CSRF, mapea errores 422 de Laravel a `form.errors` y expone reactividad (`form.processing`, `form.statusMessage`).
- **Montaje en Blade**:
  - `<div data-vue="nombre-componente" data-props="{{ json_encode([...]) }}"></div>`.

---

## 3. Estándares de Componentes y UI en Landing

- **Elemento Raíz Único**:
  - Todo componente `.vue` debe tener exactamente **un único elemento raíz** (`<div>`, `<section>`, etc.) en su bloque `<template>`.
- **Diseño con DaisyUI 5 y Tailwind 4**:
  - Priorizar las clases semánticas de DaisyUI 5 en la landing combinadas con utilidades de Tailwind CSS v4.
  - Deshabilitar botones y mostrar estados de carga mientras `form.processing` sea verdadero.
  - Asegurar accesibilidad (etiquetas `<label>`, atributos `aria-*` y estados de foco visibles `focus-visible:ring-2`).
- **Sin uso de emojis**: No utilizar emojis en interfaces de usuario o componentes.
