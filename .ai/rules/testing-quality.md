# Regla: Disciplina de Pruebas (Pest), Calidad y Prevención de Regresiones

**Rutas cubiertas**: `tests/**`, `phpunit.xml`

## 1. Obligatoriedad de Pruebas y Cero Regresiones

- **Ningún Cambio sin Prueba**:
  - Toda nueva funcionalidad, modificación de comportamiento o corrección de bug debe incluir o actualizar su prueba con **Pest**.
  - **Prohibido**: Eliminar o comentar pruebas existentes para "hacer pasar" un cambio sin aprobación explícita.
- **Ejecución y Verificación**:
  - Ejecutar siempre las pruebas afectadas durante el desarrollo:
    `php artisan test --compact --filter=NombreDeLaPrueba`
  - Ejecutar la suite completa para asegurar cero regresiones antes de entregar una tarea:
    `php artisan test --compact`

## 2. Cobertura Exhaustiva por Característica (*Feature Tests*)

Cada endpoint, flujo de servicio o recurso Filament debe probar como mínimo cuatro escenarios:

1. **Ruta Feliz (*Happy Path*)**:
   - Envío con datos válidos, comprobando código de respuesta (200, 201 o 302), redirección y persistencia en base de datos (`assertDatabaseHas`).
2. **Validación Fallida (*Validation Errors*)**:
   - Envío con campos vacíos, tipos incorrectos o reglas violadas (`assertSessionHasErrors(['campo'])` o 422 JSON).
3. **Control de Acceso y Seguridad**:
   - Solicitud sin autenticación (redirección a login o 401).
   - Solicitud por un usuario sin permisos/rol correspondiente (verificar 403 Forbidden).
4. **Casos Límite y Transacciones**:
   - Asegurar que si una operación intermedia falla, la base de datos no queda en estado inconsistente (rollback completo).

## 3. Uso Exclusivo de Model Factories y Estados

- **Prohibido Acoplamiento a Datos Fijos**:
  - No asumir IDs estáticos ni depender de seeders manuales en los tests.
  - Generar datos usando fábricas con estados semánticos:
    ```php
    $admin = User::factory()->admin()->create();
    $post = Post::factory()->published()->create();
    ```
- **Aserciones Claras**:
  - Utilizar aserciones de Pest/Laravel expresivas:
    `$response->assertOk()`, `$response->assertRedirect()`, `assertDatabaseHas(...)`.

## 4. Formateo y Estilo de Código (Pint)

- Al editar o crear archivos PHP, ejecutar siempre:
  ```sh
  vendor/bin/pint --dirty --format agent
  ```
  para garantizar que el código se apegue automáticamente a las normas de estilo del proyecto sin fricciones.
