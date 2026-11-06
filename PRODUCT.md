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
- Entorno de producción en servidor compartido (shared hosting).

## Capabilities and Constraints

- **Público / Landing:** Página principal, identidad sindical, estatutos, buzón de dudas/contacto, registro público de agremiados con carga validada de documentos, simulador y consulta de peticiones públicas.
- **Portal de Miembros / Privado:** Gestión de préstamos (simulación, solicitud, carga de evidencias, firma digital, descarga de comprobantes), expedientes documentales y mensajería/notificaciones.
- **Panel Administrativo (Filament):** Gestión de agremiados, aprobación de préstamos, peticiones anuales, auditoría, publicaciones y transparencia.
- **Restricciones técnicas:** Laravel 13, Filament 5, Vue Islands en vistas Blade para interactividad reactiva, Tailwind CSS.

## Brand Commitments

- **Nombre oficial:** Sindicato Único de Trabajadores del Instituto Tecnológico Superior de Macuspana.
- **Siglas:** OST SIUT ITSM.
- **Lema:** *"Porque la razón está en la educación; luchemos unidos por ella"*.
- **Voz institucional:** Formal, transparente, accesible, gremial y de servicio.

## Evidence on Hand

- Configuración institucional (`config/syndicate.php`) con datos de contacto, enlaces y lema.
- Módulos operativos en código (`routes/web.php`, `routes/landing.php`, controladores de préstamos, registro e informes financieros).
- Componentes y vistas existentes en `resources/views/landing` y `app/Filament`.

## Product Principles

1. **Claridad y Confianza:** Los trámites gremiales, montos, requisitos y estados de solicitudes deben ser transparentes, comprensibles y sin ambigüedades.
2. **Accesibilidad y Eficiencia:** Simplificar la interacción de los agremiados reduciendo la fricción en cargas documentales y firmas en cualquier dispositivo.
3. **Rigurosidad Institucional:** Mantener seriedad, trazabilidad y apego a la legalidad sindical en cada documento y flujo.
