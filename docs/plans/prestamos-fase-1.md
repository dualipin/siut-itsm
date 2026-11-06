# Plan de implementación — Préstamos, fase 1

## Objetivo

Implementar únicamente **solicitud, revisión/aprobación y desembolso** del módulo de préstamos.

Quedan fuera de esta fase el seguimiento, pagos, morosidad, abonos, liquidación, reestructuración y estado de cuenta. Tampoco se validará el límite del 30 % del salario.

## Alcance funcional

- Solicitantes: `Agremiado`, `Lider` y `Admin`.
- Revisores: `Lider` y `Admin`.
- Formas de pago: nómina, una o varias prestaciones, o combinación mixta.
- Catálogo administrable de prestaciones con fecha recurrente, disponibilidad, método de cálculo y requisitos documentales.
- Fecha límite inclusiva: 30 de noviembre; desde el 1 de diciembre se bloquean nuevas solicitudes.
- Nómina: fechas del día 15 y del último día real de cada mes; la primera fecha elegible debe respetar una tolerancia de 20 días y no superar el 30 de noviembre.
- Cada fuente de pago requiere evidencia propia.
- Firma dibujada obligatoria y privada. La e.firma queda preparada, pero no tendrá integración funcional en esta fase.
- Estados explícitos para borrador, enviada, en revisión, aprobada, rechazada, lista de espera y desembolsada.
- Un máximo de una solicitud activa por usuario por defecto; las excepciones serán auditadas.

## Validación documental con IA

La validación será **síncrona y bloqueante**, sin jobs:

1. Validar localmente extensión, MIME, tamaño máximo de 5 MB y límite de páginas.
2. Guardar el archivo de forma privada y calcular su hash.
3. Clasificarlo con una respuesta compacta: recibo de nómina, documento de prestación, ilegible o inválido.
4. Extraer únicamente los campos mínimos para el tipo clasificado.
5. Comparar localmente nombre, periodo, prestación y datos configurados.
6. Marcar el resultado como válido, dudoso, inválido o fallo técnico.

La interfaz mostrará etapas honestas —guardando, preparando, analizando, comparando y resultado—, deshabilitará el envío durante el proceso y permitirá reintentos controlados. Un resultado dudoso, timeout o fallo no rechazará definitivamente la solicitud, pero requerirá revisión manual positiva antes del envío o aprobación.

Se conservarán solo datos estructurados, confianza, hash, modelo, versión del prompt y métricas disponibles. No se registrarán archivos, prompts, tokens ni PII en logs.

Se reutilizará `App\Services\DocumentAIService` mediante un adaptador específico de préstamos, preservando los flujos existentes de registro y perfil. No se añadirá un proveedor ni dependencia nueva sin aprobación.

## Cálculos y documentos

Se reutilizará `App\Services\LoanSimulationService` y sus pruebas existentes:

- Método alemán para nómina.
- Interés compuesto para prestaciones.
- Fecha real de desembolso.
- Persistencia de filas de amortización y resumen.
- Generación y versionado privado de PDF de solicitud, pagaré, anuencia y corrida financiera.

Los fallos de generación de PDF no revertirán la aprobación o el desembolso. La solicitud quedará marcada con documentos pendientes y permitirá regeneración manual, conservando las versiones anteriores.

## Revisión y desembolso

- Policies y filtros impedirán el acceso cruzado del solicitante.
- Admin/Líder podrán aprobar, rechazar, poner en lista de espera, validar documentos, regenerar PDFs y registrar desembolso.
- La aprobación exigirá expediente documental completo y decisiones válidas para todas las fuentes.
- El revisor podrá ajustar monto por fuente, monto total, tasa y plazo según corresponda.
- El desembolso registrará una sola vez fecha, monto, método configurable y comprobante opcional.
- No habrá transferencia bancaria ni conciliación.
- Las transiciones usarán transacciones y `lockForUpdate()` para evitar decisiones concurrentes; una segunda acción recibirá un error explícito.
- Las notificaciones internas se crearán sincrónicamente después del commit. El correo será separado y no determinará el éxito de la operación.

## Plan técnico

1. Crear enums, migraciones reversibles, modelos, relaciones, estados, auditoría y configuración del catálogo.
2. Crear requests, servicios y Policies para solicitud, calendario, documentos, firma y transiciones.
3. Crear la interfaz del solicitante con carga y validación bloqueante, y la interfaz Filament para catálogo, revisión y desembolso.
4. Crear la integración compacta con IA y la revisión manual.
5. Crear la generación privada/versionada de PDFs y adaptarla al simulador existente.
6. Crear la corrida persistida y registrar el desembolso.
7. Añadir notificaciones, autorización de descargas y manejo explícito de errores.
8. Añadir pruebas Pest para flujo feliz, validaciones, 401/403, concurrencia, transacciones, IA, calendario, PDFs y desembolso.
9. Ejecutar Pint sobre los archivos PHP modificados y las pruebas afectadas.

## Restricciones

- No crear jobs por las limitaciones de hosting.
- No validar el 30 % del salario.
- No alterar `app/Enums/UserRole.php`.
- No implementar funcionalidades de fase 2.
- Mantener los documentos privados y servirlos mediante autorización.
- Invalidar la firma y exigir una nueva si cambian datos relevantes antes del envío.
