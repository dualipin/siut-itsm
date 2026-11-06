# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Primary users:
- Agremiados y miembros del sindicato (docentes, investigadores y personal administrativo del Instituto Tecnológico Superior de Macuspana - ITSM).
- Aspirantes a agremiados (nuevos trabajadores del instituto que inician su proceso de registro sindical).
- Comité ejecutivo y administradores del sindicato (gestores de préstamos, peticiones anuales, trámites, transparencia y comunicación).

## Product Purpose

Plataforma digital integral para el Sindicato Único de Trabajadores del Instituto Tecnológico Superior de Macuspana (OST SIUT ITSM). Su propósito es centralizar la gestión sindical, agilizar trámites y servicios para los agremiados (solicitud y firma de préstamos, pliegos petitorios, consultas, registro de agremiados), y garantizar el acceso transparente a informes financieros, actas y publicaciones oficiales.

## Positioning

Portal gremial institucional y autoservicio para la comunidad sindical del ITSM, sustituyendo procesos manuales en papel por flujos digitales seguros (firma digital, validación documental, seguimiento de trámites en tiempo real y transparencia pública).

## Operating Context

- Dispositivos de escritorio y móviles utilizados por agremiados tanto en el campus universitario como fuera de él.
- Trámites formales: solicitud de préstamos con firmas y carga de evidencias, consulta de estados de cuenta, descarga de comprobantes y peticiones anuales.
- Cumplimiento normativo y sindical: transparencia pública, descarga de informes financieros en PDF y consulta de comunicados oficiales.
- Entorno de producción en servidor compartido (shared hosting, sin acceso SSH ni shell interactivo).

## Capabilities and Constraints

- **Frontera Arquitectónica Crítica: Dos Ecosistemas Incompatibles de UI:**
  - **Ecosistema 1: Landing y Portal Público (`resources/views/landing/**`, componentes e islas Vue):**
    Construido exclusivamente sobre **daisyUI 5** y Tailwind CSS v4. El uso de las clases y componentes semánticos de DaisyUI (`btn`, `card`, `badge`, `alert`, `join`, `input`, etc.) está **estrictamente restringido a esta capa**.
  - **Ecosistema 2: Paneles Administrativos y de Agremiados (Filament Admin y Filament Portal en `app/Filament/**`, `resources/views/filament/**`):**
    Construido sobre el framework y componentes nativos de **Filament** (Livewire, componentes Blade de Filament, Tailwind base del panel).
    **PROHIBICIÓN ESTRICTA:** Nunca introducir clases, estilos ni componentes de DaisyUI dentro de Filament. Sus filosofías de diseño, jerarquías de clase y comportamientos son incompatibles; mezclarlos rompe los estilos, modales, tablas y componentes de Filament.
  - **Puente Cromático Compartido (Colores del Theme en BD):**
    Ambos ecosistemas comparten y sincronizan la misma paleta institucional configurada en la base de datos (tema `siut`: color primario vino `#611232`, secundario dorado/bronce `#a57f2c`, colores base y semánticos).
    En la landing, los colores se inyectan mediante `<x-theme-style />` como variables CSS (`--siut-color-*`) consumidas por DaisyUI y Tailwind.
    En Filament, el panel se configura con los mismos colores institucionales provenientes del tema en base de datos para preservar la identidad de marca sin alterar la estructura nativa de sus componentes.
- **Frontera de Framework Frontend: Inertia v3 SPA vs Islas Vue en Blade:**
  - Páginas públicas (`resources/views/landing/**`): Renderizadas por Blade con islas interactivas de Vue (`island.ts`). Uso obligatorio del composable `useIslandForm`. Prohibido importar o invocar `useForm` de `@inertiajs/vue3`, `router.visit` o `usePage()` en componentes montados como islas.
  - Páginas SPA Inertia (`resources/js/pages/**`): Operan dentro del contexto raíz de Inertia v3.
- **Sin uso de emojis:** No utilizar emojis en interfaces de usuario, componentes ni mensajes; usar iconos SVG o la librería tipográfica aprobada.

## Brand Commitments

- **Nombre oficial:** Sindicato Único de Trabajadores del Instituto Tecnológico Superior de Macuspana.
- **Siglas:** OST SIUT ITSM.
- **Lema:** *"Porque la razón está en la educación; luchemos unidos por ella"*.
- **Voz institucional:** Formal, transparente, accesible, gremial y de servicio.
- **Colores de marca:** Primario vino institucional (`#611232`), Secundario dorado/bronce (`#a57f2c`).

## Evidence on Hand

- Configuración institucional (`config/syndicate.php`) con datos de contacto, enlaces y lema.
- Módulos operativos en código (`routes/web.php`, `routes/landing.php`, controladores de préstamos, registro e informes financieros).
- Componentes y vistas existentes en `resources/views/landing` y `app/Filament`.
- Inyección dinámica de variables de color desde base de datos (`resources/views/components/theme-style.blade.php`).

## Product Principles

1. **Claridad y Confianza:** Los trámites gremiales, montos, requisitos y estados de solicitudes deben ser transparentes, comprensibles y sin ambigüedades.
2. **Aislamiento de Ecosistemas UI:** daisyUI pertenece exclusivamente a la landing pública; Filament se rige por su propia filosofía de componentes. La coherencia entre ambos se da a través de los colores del tema en BD, nunca por contaminación de clases.
3. **Accesibilidad y Eficiencia:** Simplificar la interacción de los agremiados reduciendo la fricción en cargas documentales y firmas en cualquier dispositivo con contraste WCAG AA y foco visible.
4. **Rigurosidad Institucional:** Mantener seriedad, trazabilidad y apego a la legalidad sindical en cada documento y flujo.
