# Regla: Rendimiento, Escalabilidad y Prevención N+1

**Rutas cubiertas**: `app/Http/Controllers/**`, `app/Filament/**`, `app/Models/**`, `app/Services/**`, `app/Jobs/**`, `app/Console/**`

## 1. Prevención Estricta de Consultas N+1

- **Prohibido Consultar dentro de Ciclos**:
  - Nunca ejecutar llamadas a base de datos dentro de `foreach`, `map` o ciclos:
    ```php
    // ❌ INCORRECTO: Causa N+1
    foreach ($posts as $post) {
        $author = $post->author->name;
    }

    // ✅ CORRECTO: Carga ansiosa (Eager Loading)
    $posts = Post::with('author')->get();
    ```
- **Filament Resources y Tablas**:
  - En tablas de Filament que muestren columnas de relaciones (`user.name`, `category.title`), configurar siempre la carga ansiosa en el recurso o tabla mediante:
    ```php
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['author', 'category']);
    }
    ```

## 2. Manejo de Grandes Volúmenes de Datos (Memory Safety)

- **Prohibido `all()` y `get()` en Colecciones Ilimitadas**:
  - En controladores web, APIs y paneles, utilizar siempre paginación (`paginate(15)` o `cursorPaginate(15)`).
- **Procesamiento por Lotes en Tareas y Comandos**:
  - Para comandos de consola (`app/Console/Commands/`), jobs o exportaciones masivas, utilizar exclusivamente `chunkById()` o `lazyById()`:
    ```php
    // ✅ Procesa registros en lotes de 100 sin desbordar memoria
    User::where('is_active', true)->chunkById(100, function ($users) {
        foreach ($users as $user) {
            // procesar
        }
    });
    ```
- **Proyección de Columnas Específicas**:
  - En tablas con campos extensos (`text`, `json`, `blob`), seleccionar solo las columnas necesarias en listados:
    ```php
    Post::select(['id', 'title', 'slug', 'published_at', 'author_id'])->paginate(15);
    ```

## 3. Asincronía Obligatoria para Cargas Pesadas

- **Trabajos en Cola (`ShouldQueue`)**:
  - Todo envío de correos electrónicos (`Mailable`), notificaciones externas, generación de reportes PDF, procesamiento de archivos o llamadas a APIs externas debe despacharse mediante un Job en cola (`implements ShouldQueue`).
- **Resiliencia en Jobs**:
  - Todo Job en cola debe definir propiedades de control de fallos:
    ```php
    public int $tries = 3;
    public int $timeout = 120;
    public int $backoff = 30;
    ```
  - Implementar el método `failed(?Throwable $exception)` para registrar diagnósticos claros si la tarea agota sus reintentos.

## 4. Caché e Invalidación Determinística

- Cachear consultas agregadas pesadas (ej. estadísticas del portal de transparencia, contadores de publicaciones) con tiempo de expiración (`remember`).
- Al modificar el modelo origen (`saved`, `deleted`), invalidar inmediatamente la clave o etiqueta correspondiente para evitar estados obsoletos (*stale state*).
