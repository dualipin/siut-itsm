---
target: resources/views/landing/home.blade.php
total_score: 22
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 2
target_identity: "file:C:\\Users\\dualipin\\Projects\\ost-siut-itsm-laravel\\resources\\views\\landing\\home.blade.php"
target_fingerprint: "sha256:ed0233c3dd59ff888cf3d794f1cff3da94f2124f3cd52560029aa736790ee97a"
target_path: "C:\\Users\\dualipin\\Projects\\ost-siut-itsm-laravel\\resources\\views\\landing\\home.blade.php"
timestamp: 2026-11-06T17-32-21Z
slug: resources-views-landing-home-blade-php
closed: true
---
Method: dual-agent (A: 1deb94a9-c2f3-4c19-9c2b-c16e346ac14a · B: 8a64f19e-653d-4d67-a663-3e0fec6bcc94)

#### Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|:-----:|-----------|
| 1 | Visibility of System Status | 2 | Carrusel sin indicadores de diapositiva ni contador de progreso activo. |
| 2 | Match System / Real World | 2 | Copia residual de plantilla (`alt="Cliente 2"` para el sindicato nacional hermano SNES) y falta de acento en "préstamos". |
| 3 | User Control and Freedom | 2 | Carrusel con auto-slide forzado sin pausa al cursor/focus; sin controles accesibles. |
| 4 | Consistency and Standards | 2 | Jerarquía de 4 botones simultáneos en el Hero; el menú móvil omite el botón "Acceder". |
| 5 | Error Prevention | 3 | Buenos límites en formulario de contacto; sin embargo, carrusel depende de placeholders remotos. |
| 6 | Recognition Rather Than Recall | 2 | Repositorios clave y CCT enterrados en submenús anidados de 9 niveles en la navegación. |
| 7 | Flexibility and Efficiency | 2 | Sin atajos ni salto directo al contenido principal (skip link) para usuarios recurrentes. |
| 8 | Aesthetic and Minimalist Design | 2 | Congestión visual: cumpleaños con confeti antes del Hero, 4 botones concurrentes y tarjetas anidadas. |
| 9 | Error Recovery | 3 | Respuestas claras con alertas e íconos en el formulario de contacto. |
| 10 | Help and Documentation | 2 | Ausencia de guía de onboarding o resumen de "¿Nuevo en el sindicato? Comienza aquí". |
| **Total** | | **22/40** | **Acceptable** |

---

#### Design Specificity Verdict

**LLM assessment**: La interfaz actual exhibe un choque notable entre una plantilla genérica tipo SaaS/Startup y la sobriedad institucional requerida por un sindicato universitario mexicano (*Sindicato Único de Trabajadores del Instituto Tecnológico Superior de Macuspana*). La jerarquía visual se encuentra invertida: cuando hay cumpleaños, el banner festivo con confeti aparece **antes** del Hero, desplazando la identidad sindical, misión y acceso al portal. En el Hero, la acción se fragmenta en cuatro botones dispersos con distintas jerarquías en lugar de proyectar las prioridades gremiales (defensa laboral, Contrato Colectivo de Trabajo y trámites de agremiados).

**Deterministic scan**: El detector determinista renderizado sobre el DOM completo detectó **16 hallazgos** (6 advertencias, 10 advertencias leves):
- **Contraste insuficiente (WCAG AA < 4.5:1)**: enlaces `text-success` (#38b44a a 2.7:1), subtítulos `text-base-content/60` (#7a7c7f a 4.2:1) y botón `btn-secondary` ("Registrarse" a 3.5:1).
- **Estructura DOM (Slop)**: Tarjetas anidadas (`nested-cards`) en la sección de contacto.
- **Tipografía**: Interlineado apretado (`tight-leading` 1.25x en dirección del pie de página) y texto funcional diminuto (`undersized-ui-text` a 10px en badges).
- **Código y sintaxis**: Atributos `src` sin comillas (`src={{ ... }}`) en imágenes de logos y falta de dimensiones `width`/`height` causando Cumulative Layout Shift (CLS).

**Visual overlays**: El análisis se realizó mediante escaneo headless y motor de reglas. Sin overlays residuales en la vista del usuario.

---

#### Overall Impression
La plataforma cuenta con una base técnica muy sólida gracias a la arquitectura de Islas Vue 3 (`useIslandForm`), Tailwind CSS y componentes DaisyUI. Sin embargo, la presentación pública adolece de residuos de plantillas comerciales (`alt="Cliente 2"`), sobrecarga de botones sin jerarquía clara y fallas de accesibilidad y contraste que comprometen la seriedad institucional de la organización.

---

#### What's Working
1. **Arquitectura reactiva ligera (Vue Islands)**: El formulario de contacto y las interacciones dinámicas no recargan la página y responden de forma inmediata sin el peso de una SPA completa.
2. **Identidad de contacto multicanal**: Dirección física en el campus, enlace verificado a WhatsApp, correo y teléfono bien comunicados en la sección inferior.
3. **Fundación de diseño escalable**: El sistema de tokens de Tailwind con temas DaisyUI permite unificar rápidamente colores y componentes en todo el proyecto.

---

#### Priority Issues

- **[P1] Inversión de jerarquía visual (Cumpleaños sobre el Hero)**
  - *Why it matters*: Cuando hay miembros de cumpleaños, el banner festivo desplaza la identidad sindical, el lema institucional y el acceso al portal fuera del primer viewport visible.
  - *Fix*: Mover `<x-landing-home-birthday />` debajo del Hero o integrarlo en una sección de comunidad previa al pie de página.
  - *Suggested command*: `$impeccable layout`

- **[P1] Residuos de plantilla y sintaxis HTML inválida en Logos**
  - *Why it matters*: El logo del sindicato nacional confederado (SNES) tiene `alt="Cliente 2"`, y los atributos `src` están sin comillas (`src={{ asset(...) }}`), dañando la autoridad institucional y el SEO.
  - *Fix*: Corregir las comillas en los atributos `src`, agregar atributos `alt="Sindicato Nacional de Educación Superior (SNES)"`, y definir `width` y `height` para evitar Cumulative Layout Shift.
  - *Suggested command*: `$impeccable polish`

- **[P2] Parálisis de decisión y ortografía en los CTA del Hero**
  - *Why it matters*: Cuatro botones apiñados sin jerarquía evidente (`btn-primary`, `btn-outline btn-primary`, `btn-outline`) desconciertan al agremiado. Además, *"Simulador de prestamos"* omite la tilde requerida en un portal académico.
  - *Fix*: Concentrar el Hero en dos llamadas a la acción claras (1. Acceder al Portal / Trámites, 2. Transparencia y CCT). Reubicar el simulador de préstamos e informes a un bloque de accesos rápidos debajo del Hero y corregir el acento a *préstamos*.
  - *Suggested command*: `$impeccable clarify`

- **[P2] Problemas de accesibilidad y contraste (WCAG 2.1 AA)**
  - *Why it matters*: Textos `text-base-content/60` (4.2:1) y enlaces en verde `text-success` (2.7:1) dificultan la lectura a docentes y personal en pantallas móviles o exteriores. El carrusel carece de controles accesibles (`aria-label`) y rota automáticamente sin pausa.
  - *Fix*: Elevar el contraste a `text-base-content/80` o un color verde más oscuro, agregar `aria-label` descriptivos a los botones de carrusel y menú móvil, e incorporar pausa al hover/focus.
  - *Suggested command*: `$impeccable polish`

- **[P3] Asimetría en el menú móvil (Botón "Acceder" faltante)**
  - *Why it matters*: El menú de escritorio ofrece "Registrarse" y "Acceder", pero el cajón móvil (`mobile-menu.blade.php`) solo incluye "Registrarse", imposibilitando que los agremiados inicien sesión desde su celular de forma directa.
  - *Fix*: Agregar el botón de inicio de sesión al menú lateral móvil.
  - *Suggested command*: `$impeccable adapt`

---

#### Persona Red Flags

- **Profesor / Miembro Titular (Agremiado Académico)**: Al ingresar al portal para revisar su Contrato Colectivo o iniciar un trámite gremial urgente, se encuentra con confeti y globos de fiesta antes de ver la institución, seguido de cuatro botones confusos y un error ortográfico en *"prestamos"*. Si navega desde el móvil, el menú no le permite acceder al portal.
- **Nuevo Trabajador / Docente de Nuevo Ingreso**: El sitio no explica qué beneficios ofrece el sindicato ni los requisitos para agremiarse. La sección de aliados lo categoriza como "Cliente 2", proyectando una imagen de agencia comercial en lugar de un organismo de defensa de los trabajadores.

---

#### Minor Observations
- El interlineado en la dirección física del pie de página (`leading-tight`) está excesivamente apretado; cambiar a `leading-normal`.
- La tarjeta exterior del formulario de contacto anida innecesariamente tarjetas secundarias (`nested-cards`).
- Las etiquetas de cumpleaños usan texto de 10px (`badge-xs`), por debajo del límite mínimo recomendado de 11px.

---

#### Questions to Consider
- ¿Deberían los avisos de cumpleaños residir en el panel privado de agremiados en lugar de la portada pública institucional?
- ¿Podemos consolidar los 9 submenús de "Repositorios" en una sección dedicada de "Transparencia y Normatividad" en el cuerpo de la página?
