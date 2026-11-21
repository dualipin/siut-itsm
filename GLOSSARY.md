# SIUT-ITSM — Archivo Documental y Transparencia

Dominio encargado de digitalizar, preservar históricamente y transparentar el archivo documental del Sindicato Único de Trabajadores del ITSM (SIUT-ITSM).

## Language

**Registro de Transparencia**:
Unidad del archivo documental histórico del sindicato que agrupa la ficha descriptiva y uno o más documentos digitalizados para su preservación y rendición de cuentas.
_Avoid_: Trámite, solicitud

**Documento Adjunto**:
Archivo digitalizado (habitualmente un PDF escaneado) que forma parte de un Registro de Transparencia y hereda íntegramente el nivel de visibilidad de dicho registro.
_Avoid_: Archivo independiente con visibilidad propia

**Fecha del Documento**:
Fecha original de emisión o firma del documento físico digitalizado, independiente de la fecha en que se captura o sube al sistema.
_Avoid_: Fecha de publicación, fecha de carga

**Directiva**:
Integrantes del sindicato con privilegios de gestión en el panel interno (`Líder` y `Administrador`), únicos autorizados para consultar y descargar documentos de Consulta Interna.
_Avoid_: Usuarios internos, agremiados con acceso

**Consulta Interna**:
Estado por defecto en el que la existencia del Registro de Transparencia se lista en el portal público por normativa sindical, pero sus documentos adjuntos quedan bloqueados al público y solo son accesibles para la Directiva.
_Avoid_: Privado, borrador, en revisión, archivado

**Público**:
Estado en el que el Registro de Transparencia aparece listado en el portal público y todos sus documentos adjuntos son descargables por cualquier visitante. Requiere al menos un Documento Adjunto.
_Avoid_: Publicado, abierto

**Categoría Documental**:
Clasificación única del registro dentro del archivo histórico del sindicato: `Financiero`, `Oficios y Gestiones`, `Actas y Minutas`, `Normativo y Legal` u `Otros`.
_Avoid_: Repositorio, subtipo

**Periodo**:
Subdivisión temporal cerrada (`Anual`, trimestres, semestres o meses) que aplica exclusivamente a registros de la categoría `Financiero`.
_Avoid_: Periodo libre, vigencia

**Folio**:
Identificador oficial único y opcional de un documento (por ejemplo, número de oficio `ITSM-SIUTITSM/031/04/2023`), aplicable a `Oficios y Gestiones`, `Actas y Minutas` y `Normativo y Legal`.
_Avoid_: Código interno, clave de sistema
