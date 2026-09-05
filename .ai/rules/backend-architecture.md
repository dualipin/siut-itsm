# Regla: Arquitectura Backend, Servicios y Tipado Estricto (PHP 8.4 / Laravel 13)

**Rutas cubiertas**: `app/Http/Controllers/**`, `app/Services/**`, `app/Models/**`, `app/Enums/**`, `app/Http/Requests/**`, `app/Providers/**`

## 1. Responsabilidades y Separación de Capas (Thin Controllers, Rich Services)

- **Controladores Ligeros (*Thin Controllers*)**:
  - Un controlador solo debe:
    1. Autorizar la acción (mediante Policies o `authorize()`).
    2. Validar los datos entrantes exclusivamente mediante una clase `FormRequest` dedicada (ubicada en `app/Http/Requests/`).
    3. Delegar la lógica de negocio a una clase de servicio (`app/Services/`) o acción de dominio.
    4. Transformar el resultado y retornar una respuesta HTTP, recurso Eloquent (`JsonResource`) o página Inertia.
  - **Prohibido**: Ejecutar consultas directas complejas, mutaciones de múltiples modelos o transacciones directamente en el controlador.

- **Capa de Servicios de Dominio (`app/Services/`)**:
  - Toda lógica de negocio transaccional, cálculos de estado, orquestación de flujos y operaciones con efectos secundarios reside en clases de servicio (ej. `BirthdayService`, `RequestService`).
  - Los servicios deben ser comprobables de forma aislada, recibir modelos tipados o DTOs (Data Transfer Objects), y lanzar excepciones de dominio específicas cuando las invariantes de negocio fallen.

## 2. Tipado Estricto y Estándares PHP 8.4

- **Tipado Explícito**:
  - Declarar siempre tipos explícitos para todos los parámetros de métodos y valores de retorno:
    ```php
    public function transitionTo(UserRequest $request, RequestStatus $newStatus, ?string $reason = null): bool
    ```
  - Emplear promoción de propiedades en constructor de PHP 8:
    ```php
    public function __construct(
        protected readonly BirthdayService $birthdayService,
        protected readonly LoggerInterface $logger,
    ) {}
    ```
  - Prohibido dejar constructores vacíos sin parámetros.

- **Uso Obligatorio de Enums en lugar de Strings Mágicos**:
  - Todo estado, tipo, categoría o rol debe definirse como un `BackedEnum` (`string` o `int`) en el namespace `App\Enums`.
  - Las claves de los Enums deben usar **TitleCase** (ej. `PendingApproval`, `InProgress`, `Completed`, `Rejected`).
  - En los modelos, definir los casts usando el nuevo método `casts()` de Laravel:
    ```php
    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
        ];
    }
    ```

## 3. Resiliencia y Manejo Defensivo de Errores

- **Prohibido Silenciar Errores**:
  - Nunca utilizar bloques `catch (\Throwable $e) {}` vacíos.
  - Capturar siempre excepciones específicas antes que la genérica.
  - Al registrar errores en el log, incluir siempre contexto descriptivo:
    ```php
    Log::error('Error al procesar la solicitud del agremiado', [
        'request_id' => $request->id,
        'user_id' => $user->id,
        'exception' => $e->getMessage(),
    ]);
    ```
  - **Seguridad**: Nunca incluir en los logs credenciales, tokens de autenticación, contraseñas ni datos bancarios/personales sensibles.

## 4. Validaciones Robustas (`FormRequest`)

- Toda mutación (`POST`, `PUT`, `PATCH`, `DELETE`) debe contar con su clase `FormRequest`.
- No emplear `$request->validate([...])` en línea dentro del controlador si la lógica involucra más de dos campos o reglas condicionales.
- Centralizar mensajes de validación y nombres de atributos en `lang/es/validation.php`.
