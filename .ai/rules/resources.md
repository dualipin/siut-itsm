---
paths:
  - 'app/Filament/**'
  - 'resources/views/filament/**'
---

# Resources y Paneles Filament

## 1. Aislamiento Estricto de Estilos: Prohibido DaisyUI en Filament
- **Filosofía propia**: Filament posee su propio sistema de diseño, componentes y tema Tailwind.
- **Incompatibilidad crítica**: **NUNCA** introducir clases o componentes de DaisyUI (`btn`, `card`, `badge`, `alert`, `join`, `table`, etc.) en recursos, páginas, relation managers, widgets o vistas personalizadas de Filament.
- **Colores institucionales**: Filament consume los colores del tema almacenados en base de datos (`siut`: vino `#611232` y secundario `#a57f2c`) a través de la configuración nativa de Filament Panels (`->colors([...])`). La cohesión visual se logra por tokens de color compartidos, nunca mezclando clases de DaisyUI.

## 2. RelationManager ViewAction modal needs its own infolist()
El modal de `ViewAction` en un RelationManager se construye con `$this->infolist(...)`, que solo aplica el recurso relacionado si `static::$relatedResource` está declarado. Sin ese —o sin sobrescribir `infolist()` en el RelationManager— el schema del modal queda con 0 componentes: se abre la caja "Vista de {título}" completamente vacía (sin Adjuntos ni ningún dato). Ver `PetitionRequestsRelationManager::infolist()` que delega en `PetitionRequestInfolist::configure()`. No declarar `$relatedResource` si no se quiere que `Resource::configureTable()` pise la tabla propia.
