# Rules Index

Maps file globs to rule files containing settled architectural decisions, senior engineering standards, and standing constraints.

| Glob | Rule File | Description |
| :--- | :--- | :--- |
| `app/Http/Controllers/**` | [backend-architecture.md](backend-architecture.md) | Controladores ligeros (*thin controllers*), delegación a servicios, validación vía FormRequest. |
| `app/Services/**` | [backend-architecture.md](backend-architecture.md) | Lógica de negocio transaccional, DTOs, separación de responsabilidades y tipado estricto. |
| `app/Models/**` | [backend-architecture.md](backend-architecture.md) | Casts tipados con método `casts()`, Enums en TitleCase, relaciones limpias. |
| `app/Enums/**` | [backend-architecture.md](backend-architecture.md) | Definición obligatoria de estados, roles y categorías como BackedEnums. |
| `app/Http/Requests/**` | [backend-architecture.md](backend-architecture.md) | Reglas de validación robustas, autorización de solicitudes y mensajes en español. |
| `database/migrations/**` | [database-migrations.md](database-migrations.md) | Migraciones seguras no destructivas (*expand & contract*), integridad y métodos down simétricos. |
| `app/Models/**` | [database-migrations.md](database-migrations.md) | Concurrencia, bloqueos pesimistas (`lockForUpdate`) y operaciones atómicas. |
| `app/Services/**` | [database-migrations.md](database-migrations.md) | Transacciones seguras con `DB::transaction` y eventos diferidos con `DB::afterCommit`. |
| `app/Http/Controllers/**` | [performance-scalability.md](performance-scalability.md) | Prevención de consultas N+1 con eager loading y paginación obligatoria. |
| `app/Filament/**` | [performance-scalability.md](performance-scalability.md) | Optimización de consultas en tablas Filament mediante `modifyQueryUsing` y eager loading. |
| `app/Jobs/**` | [performance-scalability.md](performance-scalability.md) | Despacho asíncrono obligatorio (`ShouldQueue`), reintentos, tiempos de espera y resiliencia. |
| `app/Console/**` | [performance-scalability.md](performance-scalability.md) | Procesamiento en lotes con `chunkById` y `lazyById` para evitar desbordes de memoria. |
| `resources/js/**` | [frontend-conventions.md](frontend-conventions.md) | Frontera estricta Inertia v3 SPA vs Islas Vue en Blade, uso obligatorio de `useIslandForm`. |
| `resources/views/**` | [frontend-conventions.md](frontend-conventions.md) | Montaje de islas Vue en Blade, utilidades DaisyUI 5 y Tailwind CSS v4. |
| `app/Filament/**` | [filament-localization.md](filament-localization.md) | Reglas obligatorias de nombres, campos, tablas, formularios y acciones en español. |
| `app/Providers/Filament/**` | [filament-localization.md](filament-localization.md) | Configuración de grupos de navegación y paneles Filament en español. |
| `lang/es/**` | [filament-localization.md](filament-localization.md) | Catálogo de traducción y atributos de validación para modelos. |
| `tests/**` | [testing-quality.md](testing-quality.md) | Disciplina de pruebas obligatorias con Pest, cobertura de 4 escenarios, factories y Pint. |
