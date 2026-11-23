# Escaneo multipágina y ensamblado de PDF 100% en el navegador

La Directiva necesita digitalizar oficios y actas de varias páginas desde el celular (principalmente Android moderno en HTTPS) sin depender de apps externas con marca de agua ni salir del formulario de Registros de Transparencia. Como producción corre en shared hosting (sin SSH y con límites estrictos de RAM/tiempo de ejecución en PHP), el escáner procesa la captura continua (`getUserMedia` con respaldo en galería y cámara nativa), la detección ligera de bordes con corrección de perspectiva en `<canvas>`, los filtros de mejora y el ensamblado del PDF multipágina de imágenes (PDF 1.4 `DCTDecode` sin OCR) **100% en el navegador**, entregando al servidor únicamente el archivo PDF final optimizado (<= 15 MB) directamente al campo `documents` de Spatie MediaLibrary.

## Considered Options

- **Ensamblar el PDF en PHP con `barryvdh/laravel-dompdf` (descartada):** Subir 10+ fotos crudas de cámara móvil y embebirlas en DomPDF satura la memoria y el tiempo de ejecución en shared hosting, además de desperdiciar datos móviles.
- **Detección de bordes con OpenCV.js / WASM (descartada):** Agrega ~8-10 MB de carga inicial y complejidad de empaquetado para un problema que se resuelve con detección ligera en `<canvas>` + ajuste manual de 4 esquinas bajo demanda.
- **Dependencia externa npm para armar el PDF (`pdf-lib` / `jspdf`) (descartada):** Para empaquetar imágenes JPEG sin OCR ni texto vectorial basta un escritor mínimo de PDF 1.4 estándar, evitando sumar dependencias innecesarias.

## Consequences

- No mover el procesamiento de imágenes ni el armado de PDF del escáner a PHP/DomPDF: el shared hosting solo recibe el PDF final validado contra el límite existente de 15 MB por archivo.
- El visor continuo `getUserMedia` requiere contexto seguro (HTTPS en producción o `localhost` en desarrollo); ante falta de permisos o contexto no seguro debe degradar a selección múltiple desde galería o `capture="environment"`.
- Las páginas capturadas viven solo en memoria durante la sesión del escáner (sin IndexedDB ni borradores en servidor); cerrar con páginas sin adjuntar solicita confirmación al usuario.
- El PDF generado se trata como cualquier otro Documento Adjunto de `TransparencyRecord` (hereda visibilidad `Consulta Interna`/`Público` y las reglas de `TransparencyRecordForm`).
