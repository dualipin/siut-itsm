# Regla: Migraciones Seguras, Integridad Transaccional y Concurrencia

**Rutas cubiertas**: `database/migrations/**`, `database/factories/**`, `database/seeders/**`, `app/Models/**`

## 1. Principio de Migraciones No Destructivas (Cero Regresiones)

- **Prohibido Eliminar o Renombrar Columnas Directamente**:
  - En tablas de producción, nunca renombrar ni eliminar una columna en una sola migración.
  - Aplicar el patrón *Expand & Contract*:
    1. **Fase 1 (Expand)**: Crear la nueva columna como `nullable()`.
    2. **Fase 2 (Transition)**: Actualizar el código para escribir en ambas columnas y ejecutar un comando de backfill para poblar datos históricos.
    3. **Fase 3 (Contract)**: Actualizar la aplicación para leer exclusivamente de la nueva columna.
    4. **Fase 4 (Cleanup)**: En un despliegue posterior independiente, eliminar la columna obsoleta.
- **Nuevas Columnas en Tablas con Datos**:
  - Toda nueva columna añadida a una tabla existente que contenga registros debe ser estrictamente `nullable()` o definir un valor por defecto seguro (`default(...)`).
- **Migraciones Reversibles**:
  - Toda migración debe implementar un método `down()` funcional y simétrico que revierta exactamente lo ejecutado en `up()` (eliminando foreign keys antes de eliminar columnas: `$table->dropForeign([...])`).

## 2. Límites Transaccionales y Efectos Secundarios

- **Uso Obligatorio de Transacciones**:
  - Toda operación que involucre la creación, actualización o eliminación de más de un registro o modelo debe ejecutarse dentro de un bloque `DB::transaction(function () { ... })`:
    ```php
    DB::transaction(function () use ($dto, $user) {
        $request = UserRequest::create([...]);
        $request->history()->create([...]);
        $request->participants()->attach($user->id, [...]);
    });
    ```
- **Prohibido Disparar Efectos Secundarios dentro de la Transacción**:
  - No enviar correos, ni despachar notificaciones, ni consultar APIs externas directamente dentro de `DB::transaction`. Si la transacción hace rollback, los efectos secundarios ya se habrían disparado.
  - Usar siempre el gancho `DB::afterCommit`:
    ```php
    DB::afterCommit(function () use ($request) {
        Mail::to($request->user)->send(new RequestCreatedMail($request));
    });
    ```
  - O configurar los observadores/eventos para ejecutarse tras commit (`public bool $afterCommit = true;`).

## 3. Concurrencia y Prevención de Condiciones de Carrera (*Race Conditions*)

- **Bloqueos Pesimistas en Flujos Críticos**:
  - Cuando se modifiquen contadores, folios, números de solicitud o estados que no deben duplicarse bajo concurrencia, utilizar bloqueos pesimistas dentro de la transacción:
    ```php
    $type = RequestType::where('id', $typeId)->lockForUpdate()->firstOrFail();
    $nextFolio = $type->incrementFolio();
    ```
- **Operaciones Atómicas en Base de Datos**:
  - Para contadores y métricas simples, preferir métodos atómicos directos:
    `Post::where('id', $id)->increment('views_count');`
    en lugar de leer en memoria, sumar 1 en PHP y guardar (`$post->save()`).
