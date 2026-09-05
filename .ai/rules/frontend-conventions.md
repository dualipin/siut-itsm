# Regla: Fronteras de Frontend, Inertia v3, Vue Islands y DaisyUI

**Rutas cubiertas**: `resources/js/**`, `resources/views/**`

## 1. Frontera Crítica: Inertia v3 SPA vs Islas Vue (Blade)

Esta aplicación posee una arquitectura híbrida intencional. Confundir los contextos rompe la ejecución en el cliente.

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

## 2. Estándares de Componentes y UI (DaisyUI 5 / Tailwind CSS v4)

- **Elemento Raíz Único**:
  - Todo componente `.vue` debe tener exactamente **un único elemento raíz** (`<div>`, `<section>`, etc.) en su bloque `<template>`.
- **Diseño con DaisyUI 5 y Tailwind 4**:
  - Priorizar las clases semánticas de DaisyUI 5 (`btn`, `btn-primary`, `card`, `alert`, `badge`, `modal`, `input`) combinadas con utilidades de Tailwind CSS v4.
  - Deshabilitar botones y mostrar estados de carga mientras `form.processing` sea verdadero para evitar múltiples envíos accidentales.
  - Asegurar accesibilidad (etiquetas `<label>`, atributos `aria-*` y estados de foco visibles `focus-visible:ring-2`).
