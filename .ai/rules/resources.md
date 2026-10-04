---
paths:
  - 'app/Filament/Resources/**'
---

# Resources

## RelationManager ViewAction modal needs its own infolist()
El modal de `ViewAction` en un RelationManager se construye con `$this->infolist(...)`, que solo aplica el recurso relacionado si `static::$relatedResource` está declarado. Sin ese —o sin sobrescribir `infolist()` en el RelationManager— el schema del modal queda con 0 componentes: se abre la caja "Vista de {título}" completamente vacía (sin Adjuntos ni ningún dato). Ver `PetitionRequestsRelationManager::infolist()` que delega en `PetitionRequestInfolist::configure()`. No declarar `$relatedResource` si no se quiere que `Resource::configureTable()` pise la tabla propia.
