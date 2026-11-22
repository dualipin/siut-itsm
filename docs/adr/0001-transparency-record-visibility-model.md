# Modelo de visibilidad en dos estados para registros de transparencia

Por normativa sindical, el portal público debe listar la existencia de todos los registros documentales de cada categoría, pero restringir la descarga de archivos en los expedientes que contienen datos personales o correspondencia interna. Por eso `PublicTransparencyController::index()` incluye intencionalmente tanto registros `Público` como `Consulta Interna` (mostrando estos últimos sin enlaces de descarga y con un motivo opcional), y la visibilidad se controla en un único nivel sobre `TransparencyRecord` (eliminando `is_public` por documento y estados sin flujo real como `En Revisión`, `Borrador` y `Archivado`) para minimizar la carga cognitiva de quienes digitalizan el archivo.

## Consequences

- No agregar `->published()` al listado público de `PublicTransparencyController::index()` — mostrar registros de `Consulta Interna` sin descarga es un requisito normativo, no una fuga de datos.
- Los archivos se adjuntan directamente a `TransparencyRecord` vía Spatie MediaLibrary (sin tabla/modelo `transparency_documents`); el control de acceso vive en `PublicTransparencyController::download()` (solo `Público`) y el endpoint autenticado del panel (solo Directiva vía `TransparencyRecordPolicy`).
- Un registro en estado `Público` debe contar con al menos un archivo adjunto; `Consulta Interna` puede guardarse sin archivos cuando el documento físico aún está pendiente de escaneo.
