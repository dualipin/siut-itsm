<x-filament-panels::page>
    @php
        $diag = $this->getDiagnostics();
        $storage = $diag['storage'];
        $db = $diag['database'];
        $caches = $diag['caches'];
        $env = $diag['environment'];
        $perms = $diag['permissions'];
    @endphp

    <div class="space-y-6">

        {{-- BANNER DE ACCIÓN PRINCIPAL (DESPLIEGUE COMPLETO) --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-gray-900 via-primary-950 to-gray-900 border border-primary-500/20 p-6 text-white shadow-lg">
            <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div class="space-y-2 max-w-3xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary-500/20 text-primary-300 text-xs font-semibold border border-primary-500/30">
                        <x-filament::icon icon="heroicon-m-sparkles" class="w-3.5 h-3.5" />
                        <span>Hosting Compartido & Producción</span>
                    </div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        Mantenimiento del Sistema y Despliegue
                    </h2>
                    <p class="text-sm text-gray-300 leading-relaxed">
                        En servidores compartidos sin acceso a terminal SSH (como cPanel o Plesk), esta herramienta te permite ejecutar de forma segura todos los comandos necesarios tras subir cambios o corregir problemas como fotos de perfil que devuelven error 404.
                    </p>
                </div>

                <div class="shrink-0 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    <x-filament::button
                        wire:click="runFullDeploy"
                        wire:loading.attr="disabled"
                        color="primary"
                        size="lg"
                        icon="heroicon-m-rocket-launch"
                        class="shadow-md"
                    >
                        <span wire:loading.remove wire:target="runFullDeploy">Ejecutar Despliegue Completo</span>
                        <span wire:loading wire:target="runFullDeploy">Ejecutando secuencia...</span>
                    </x-filament::button>
                </div>
            </div>

            {{-- Resumen de acciones del botón maestro --}}
            <div class="relative z-10 mt-5 pt-4 border-t border-white/10 grid grid-cols-2 md:grid-cols-4 gap-3 text-xs text-gray-300">
                <div class="flex items-center gap-1.5">
                    <x-filament::icon icon="heroicon-m-check-circle" class="w-4 h-4 text-emerald-400 shrink-0" />
                    <span>Reparar enlaces simbólicos (fotos)</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <x-filament::icon icon="heroicon-m-check-circle" class="w-4 h-4 text-emerald-400 shrink-0" />
                    <span>Migraciones de BD (<code class="text-primary-300">--force</code>)</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <x-filament::icon icon="heroicon-m-check-circle" class="w-4 h-4 text-emerald-400 shrink-0" />
                    <span>Optimizar config, rutas y vistas</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <x-filament::icon icon="heroicon-m-check-circle" class="w-4 h-4 text-emerald-400 shrink-0" />
                    <span>Reiniciar colas y verificar acceso</span>
                </div>
            </div>
        </div>

        {{-- TABLERO DE DIAGNÓSTICO EN TIEMPO REAL --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">

            {{-- CARD 1: ALMACENAMIENTO Y ENLACE SIMBÓLICO --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800">
                        <div class="flex items-center gap-2">
                            <div class="p-2 rounded-lg bg-primary-50 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400">
                                <x-filament::icon icon="heroicon-m-photo" class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Almacenamiento</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Fotos y Documentos</p>
                            </div>
                        </div>
                        @if ($storage['link_is_valid'])
                            <x-filament::badge color="success" size="sm">Conectado</x-filament::badge>
                        @elseif ($storage['link_exists'])
                            <x-filament::badge color="danger" size="sm">Enlace Roto</x-filament::badge>
                        @else
                            <x-filament::badge color="warning" size="sm">Sin Enlace</x-filament::badge>
                        @endif
                    </div>

                    <div class="mt-3 space-y-2 text-xs">
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-gray-500 dark:text-gray-400">Enlace web:</span>
                            <span class="font-mono text-right {{ $storage['link_is_valid'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 font-semibold' }}">
                                {{ $storage['link_is_valid'] ? 'storage -> app/public' : ($storage['link_exists'] ? 'Inválido / Error' : 'No existe (404)') }}
                            </span>
                        </div>
                        @if ($storage['public_html_detected'])
                            <div class="flex justify-between items-start gap-2">
                                <span class="text-gray-500 dark:text-gray-400">public_html:</span>
                                <span class="font-mono text-right text-gray-700 dark:text-gray-300">Detectado</span>
                            </div>
                        @endif
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-gray-500 dark:text-gray-400">PHP symlink():</span>
                            <span class="{{ $storage['symlink_function_enabled'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-500 font-bold' }}">
                                {{ $storage['symlink_function_enabled'] ? 'Habilitado' : 'Deshabilitado en php.ini' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 flex gap-2">
                    <x-filament::button
                        wire:click="repairStorageLink"
                        wire:loading.attr="disabled"
                        size="xs"
                        color="primary"
                        icon="heroicon-m-wrench"
                        class="flex-1"
                    >
                        Reparar Enlace
                    </x-filament::button>
                    <x-filament::button
                        wire:click="testStorageAccess"
                        wire:loading.attr="disabled"
                        size="xs"
                        color="gray"
                        icon="heroicon-m-magnifying-glass"
                        outlined
                    >
                        Probar
                    </x-filament::button>
                </div>
            </div>

            {{-- CARD 2: BASE DE DATOS Y MIGRACIONES --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800">
                        <div class="flex items-center gap-2">
                            <div class="p-2 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                                <x-filament::icon icon="heroicon-m-circle-stack" class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Base de Datos</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ strtoupper($db['driver']) }}</p>
                            </div>
                        </div>
                        @if (! $db['connected'])
                            <x-filament::badge color="danger" size="sm">Sin Conexión</x-filament::badge>
                        @elseif ($db['pending_count'] > 0)
                            <x-filament::badge color="warning" size="sm">{{ $db['pending_count'] }} Pendientes</x-filament::badge>
                        @else
                            <x-filament::badge color="success" size="sm">Al Día</x-filament::badge>
                        @endif
                    </div>

                    <div class="mt-3 space-y-2 text-xs">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">Estado de conexión:</span>
                            <span class="{{ $db['connected'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 font-bold' }}">
                                {{ $db['connected'] ? 'Conectado' : 'Fallo' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">Base de datos:</span>
                            <span class="font-mono text-gray-700 dark:text-gray-300 truncate max-w-[130px]">{{ $db['name'] ?: 'N/D' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">Migraciones por aplicar:</span>
                            <span class="font-bold {{ $db['pending_count'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                {{ $db['pending_count'] }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 flex gap-2">
                    <x-filament::button
                        wire:click="runMigrate"
                        wire:loading.attr="disabled"
                        size="xs"
                        color="warning"
                        icon="heroicon-m-arrow-path"
                        class="flex-1"
                    >
                        Migrar (--force)
                    </x-filament::button>
                    <x-filament::button
                        wire:click="runMigrateStatus"
                        wire:loading.attr="disabled"
                        size="xs"
                        color="gray"
                        icon="heroicon-m-list-bullet"
                        outlined
                    >
                        Estado
                    </x-filament::button>
                </div>
            </div>

            {{-- CARD 3: CACHÉS Y OPTIMIZACIÓN --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800">
                        <div class="flex items-center gap-2">
                            <div class="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                                <x-filament::icon icon="heroicon-m-bolt" class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Cachés & Speed</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Rendimiento</p>
                            </div>
                        </div>
                        @if ($caches['config_cached'] && $caches['routes_cached'])
                            <x-filament::badge color="success" size="sm">Optimizado</x-filament::badge>
                        @else
                            <x-filament::badge color="gray" size="sm">Parcial</x-filament::badge>
                        @endif
                    </div>

                    <div class="mt-3 space-y-2 text-xs">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">Configuración:</span>
                            <span class="{{ $caches['config_cached'] ? 'text-emerald-600 dark:text-emerald-400 font-medium' : 'text-gray-400' }}">
                                {{ $caches['config_cached'] ? '✓ Cacheada' : 'No cacheada' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">Rutas:</span>
                            <span class="{{ $caches['routes_cached'] ? 'text-emerald-600 dark:text-emerald-400 font-medium' : 'text-gray-400' }}">
                                {{ $caches['routes_cached'] ? '✓ Cacheadas' : 'No cacheadas' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">Vistas compiladas:</span>
                            <span class="font-mono text-gray-700 dark:text-gray-300">{{ $caches['views_count'] }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 flex gap-2">
                    <x-filament::button
                        wire:click="runOptimize"
                        wire:loading.attr="disabled"
                        size="xs"
                        color="success"
                        icon="heroicon-m-sparkles"
                        class="flex-1"
                    >
                        Optimizar
                    </x-filament::button>
                    <x-filament::button
                        wire:click="runOptimizeClear"
                        wire:loading.attr="disabled"
                        size="xs"
                        color="danger"
                        icon="heroicon-m-trash"
                        outlined
                    >
                        Limpiar
                    </x-filament::button>
                </div>
            </div>

            {{-- CARD 4: ENTORNO Y SERVIDOR --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800">
                        <div class="flex items-center gap-2">
                            <div class="p-2 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400">
                                <x-filament::icon icon="heroicon-m-server-stack" class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Servidor & .env</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">PHP {{ $env['php_version'] }}</p>
                            </div>
                        </div>
                        @if ($env['is_down'])
                            <x-filament::badge color="danger" size="sm">Mantenimiento</x-filament::badge>
                        @elseif ($env['app_env'] === 'production')
                            <x-filament::badge color="success" size="sm">Producción</x-filament::badge>
                        @else
                            <x-filament::badge color="info" size="sm">{{ ucfirst($env['app_env']) }}</x-filament::badge>
                        @endif
                    </div>

                    <div class="mt-3 space-y-2 text-xs">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">APP_URL:</span>
                            <span class="font-mono text-gray-700 dark:text-gray-300 truncate max-w-[140px]" title="{{ $env['app_url'] }}">
                                {{ $env['app_url'] }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">APP_DEBUG:</span>
                            <span class="{{ $env['app_debug'] ? 'text-amber-500 font-bold' : 'text-emerald-600 dark:text-emerald-400 font-medium' }}">
                                {{ $env['app_debug'] ? 'Activado (Peligro)' : 'Desactivado' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">Permisos storage/:</span>
                            <span class="{{ $perms['storage'] && $perms['storage_logs'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 font-bold' }}">
                                {{ $perms['storage'] && $perms['storage_logs'] ? '✓ Escribible' : '✕ Bloqueado' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 flex gap-2">
                    <x-filament::button
                        wire:click="toggleMaintenance"
                        wire:confirm="¿Estás seguro de alternar el modo mantenimiento del sitio?"
                        wire:loading.attr="disabled"
                        size="xs"
                        color="{{ $env['is_down'] ? 'success' : 'gray' }}"
                        icon="{{ $env['is_down'] ? 'heroicon-m-check' : 'heroicon-m-pause' }}"
                        class="flex-1"
                        outlined
                    >
                        {{ $env['is_down'] ? 'Activar Sitio (up)' : 'Modo Mantenimiento (down)' }}
                    </x-filament::button>
                </div>
            </div>

        </div>

        {{-- MATRIZ DE COMANDOS ARTISAN AGRUPADOS --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm space-y-6">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-filament::icon icon="heroicon-m-command-line" class="w-5 h-5 text-primary-500" />
                    <span>Comandos Post-Despliegue por Módulo</span>
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Ejecuta comandos específicos de forma individual según el tipo de cambio que acabas de desplegar.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- GRUPO A: ALMACENAMIENTO & FOTOS DE PERFIL (ESPECIAL HOSTING) --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4 space-y-3 bg-gray-50/50 dark:bg-gray-800/20">
                    <div class="flex items-center gap-2 text-primary-600 dark:text-primary-400 font-semibold text-sm">
                        <x-filament::icon icon="heroicon-m-link" class="w-4 h-4" />
                        <h4>1. Almacenamiento & Symlinks (Fotos de Perfil)</h4>
                    </div>
                    <p class="text-xs text-gray-600 dark:text-gray-300">
                        Corrige las imágenes y avatares que dan error 404 al vincular <code class="text-primary-600 dark:text-primary-300 bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">public/storage</code> con <code class="text-primary-600 dark:text-primary-300 bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">storage/app/public</code>.
                    </p>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <x-filament::button
                            wire:click="repairStorageLink"
                            wire:loading.attr="disabled"
                            size="sm"
                            color="primary"
                            icon="heroicon-m-wrench-screwdriver"
                        >
                            Reparar Symlink (cPanel / public_html)
                        </x-filament::button>

                        <x-filament::button
                            wire:click="testStorageAccess"
                            wire:loading.attr="disabled"
                            size="sm"
                            color="gray"
                            icon="heroicon-m-document-check"
                            outlined
                        >
                            Verificar Lectura / Escritura
                        </x-filament::button>

                        <x-filament::button
                            wire:click="runStorageLinkStandard"
                            wire:loading.attr="disabled"
                            size="sm"
                            color="gray"
                            icon="heroicon-m-link"
                            outlined
                        >
                            php artisan storage:link
                        </x-filament::button>

                        <x-filament::button
                            wire:click="runStorageUnlink"
                            wire:confirm="¿Seguro que deseas eliminar los enlaces simbólicos de almacenamiento?"
                            wire:loading.attr="disabled"
                            size="sm"
                            color="danger"
                            icon="heroicon-m-link-slash"
                            outlined
                        >
                            storage:unlink
                        </x-filament::button>
                    </div>
                </div>

                {{-- GRUPO B: BASE DE DATOS & MIGRACIONES --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4 space-y-3 bg-gray-50/50 dark:bg-gray-800/20">
                    <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400 font-semibold text-sm">
                        <x-filament::icon icon="heroicon-m-circle-stack" class="w-4 h-4" />
                        <h4>2. Base de Datos & Migraciones</h4>
                    </div>
                    <p class="text-xs text-gray-600 dark:text-gray-300">
                        Aplica cambios en esquemas de tablas, índices o columnas agregadas en el código desplegado.
                    </p>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <x-filament::button
                            wire:click="runMigrate"
                            wire:confirm="¿Deseas ejecutar las migraciones pendientes en producción con --force?"
                            wire:loading.attr="disabled"
                            size="sm"
                            color="warning"
                            icon="heroicon-m-arrow-path"
                        >
                            php artisan migrate --force
                        </x-filament::button>

                        <x-filament::button
                            wire:click="runMigrateStatus"
                            wire:loading.attr="disabled"
                            size="sm"
                            color="gray"
                            icon="heroicon-m-table-cells"
                            outlined
                        >
                            php artisan migrate:status
                        </x-filament::button>
                    </div>
                </div>

                {{-- GRUPO C: OPTIMIZACIÓN Y RENDIMIENTO --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4 space-y-3 bg-gray-50/50 dark:bg-gray-800/20">
                    <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400 font-semibold text-sm">
                        <x-filament::icon icon="heroicon-m-bolt" class="w-4 h-4" />
                        <h4>3. Optimización para Producción</h4>
                    </div>
                    <p class="text-xs text-gray-600 dark:text-gray-300">
                        Precompila configuraciones, rutas y plantillas Blade para que Laravel responda a máxima velocidad.
                    </p>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <x-filament::button
                            wire:click="runOptimize"
                            wire:loading.attr="disabled"
                            size="sm"
                            color="success"
                            icon="heroicon-m-sparkles"
                        >
                            php artisan optimize
                        </x-filament::button>

                        <x-filament::button
                            wire:click="runViewCache"
                            wire:loading.attr="disabled"
                            size="sm"
                            color="gray"
                            icon="heroicon-m-eye"
                            outlined
                        >
                            view:cache
                        </x-filament::button>

                        <x-filament::button
                            wire:click="runIconsCache"
                            wire:loading.attr="disabled"
                            size="sm"
                            color="gray"
                            icon="heroicon-m-photo"
                            outlined
                        >
                            icons:cache
                        </x-filament::button>
                    </div>
                </div>

                {{-- GRUPO D: LIMPIEZA DE CACHÉ --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4 space-y-3 bg-gray-50/50 dark:bg-gray-800/20">
                    <div class="flex items-center gap-2 text-red-600 dark:text-red-400 font-semibold text-sm">
                        <x-filament::icon icon="heroicon-m-trash" class="w-4 h-4" />
                        <h4>4. Limpieza de Cachés (Cuando cambias .env o vistas)</h4>
                    </div>
                    <p class="text-xs text-gray-600 dark:text-gray-300">
                        Útil si modificaste variables en <code class="text-red-500 bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">.env</code> o plantillas y los cambios no se ven reflejados.
                    </p>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <x-filament::button
                            wire:click="runOptimizeClear"
                            wire:loading.attr="disabled"
                            size="sm"
                            color="danger"
                            icon="heroicon-m-trash"
                        >
                            optimize:clear (Todo)
                        </x-filament::button>

                        <x-filament::button
                            wire:click="runConfigClear"
                            wire:loading.attr="disabled"
                            size="sm"
                            color="gray"
                            icon="heroicon-m-cog-6-tooth"
                            outlined
                        >
                            config:clear
                        </x-filament::button>

                        <x-filament::button
                            wire:click="runRouteClear"
                            wire:loading.attr="disabled"
                            size="sm"
                            color="gray"
                            icon="heroicon-m-map"
                            outlined
                        >
                            route:clear
                        </x-filament::button>

                        <x-filament::button
                            wire:click="runViewClear"
                            wire:loading.attr="disabled"
                            size="sm"
                            color="gray"
                            icon="heroicon-m-eye-slash"
                            outlined
                        >
                            view:clear
                        </x-filament::button>

                        <x-filament::button
                            wire:click="runCacheClear"
                            wire:loading.attr="disabled"
                            size="sm"
                            color="gray"
                            icon="heroicon-m-circle-stack"
                            outlined
                        >
                            cache:clear
                        </x-filament::button>

                        <x-filament::button
                            wire:click="runIconsClear"
                            wire:loading.attr="disabled"
                            size="sm"
                            color="gray"
                            icon="heroicon-m-photo"
                            outlined
                        >
                            icons:clear
                        </x-filament::button>
                    </div>
                </div>

                {{-- GRUPO E: PROCESOS EN SEGUNDO PLANO Y COMPOSER --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4 space-y-3 bg-gray-50/50 dark:bg-gray-800/20 lg:col-span-2">
                    <div class="flex items-center gap-2 text-indigo-600 dark:text-indigo-400 font-semibold text-sm">
                        <x-filament::icon icon="heroicon-m-arrow-path-rounded-square" class="w-4 h-4" />
                        <h4>5. Procesos, Colas y Descubrimiento de Paquetes</h4>
                    </div>
                    <p class="text-xs text-gray-600 dark:text-gray-300">
                        Si tu hosting tiene tareas programadas o workers ejecutando jobs en cola, es indispensable reiniciarlos para que carguen el nuevo código PHP.
                    </p>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <x-filament::button
                            wire:click="runQueueRestart"
                            wire:loading.attr="disabled"
                            size="sm"
                            color="primary"
                            icon="heroicon-m-arrow-path"
                        >
                            php artisan queue:restart
                        </x-filament::button>

                        <x-filament::button
                            wire:click="runPackageDiscover"
                            wire:loading.attr="disabled"
                            size="sm"
                            color="gray"
                            icon="heroicon-m-archive-box"
                            outlined
                        >
                            php artisan package:discover
                        </x-filament::button>
                    </div>
                </div>

            </div>
        </div>

        {{-- CONSOLA / TERMINAL DE REGISTRO DE SALIDA --}}
        <div class="bg-gray-950 border border-gray-800 rounded-2xl overflow-hidden shadow-2xl font-mono">
            {{-- Barra superior de la consola --}}
            <div class="bg-gray-900/90 border-b border-gray-800 px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-red-500/80 inline-block"></span>
                        <span class="w-3 h-3 rounded-full bg-yellow-500/80 inline-block"></span>
                        <span class="w-3 h-3 rounded-full bg-emerald-500/80 inline-block"></span>
                    </div>
                    <span class="text-xs font-semibold text-gray-300 flex items-center gap-2">
                        <x-filament::icon icon="heroicon-m-command-line" class="w-4 h-4 text-primary-400" />
                        <span>Terminal de Salida Artisan (Hosting Output)</span>
                    </span>
                    @if (count($terminalLogs) > 0)
                        <span class="px-2 py-0.5 text-[10px] rounded-full bg-primary-500/20 text-primary-300 border border-primary-500/30">
                            {{ count($terminalLogs) }} registro(s)
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="clearTerminalLogs"
                        class="text-xs text-gray-400 hover:text-white transition-colors flex items-center gap-1 px-2.5 py-1 rounded bg-gray-800/80 hover:bg-gray-800"
                        title="Limpiar pantalla"
                    >
                        <x-filament::icon icon="heroicon-m-trash" class="w-3.5 h-3.5" />
                        <span>Limpiar consola</span>
                    </button>
                </div>
            </div>

            {{-- Contenido de la consola --}}
            <div class="p-4 max-h-[500px] overflow-y-auto space-y-4 text-xs leading-relaxed">
                @forelse ($terminalLogs as $index => $log)
                    <div wire:key="terminal-log-{{ $index }}" class="rounded-lg p-3 border {{ $log['status'] === 'success' ? 'bg-emerald-950/20 border-emerald-900/40' : ($log['status'] === 'error' ? 'bg-red-950/20 border-red-900/40' : 'bg-gray-900/50 border-gray-800') }}">
                        <div class="flex items-center justify-between pb-2 border-b border-white/5 text-[11px]">
                            <div class="flex items-center gap-2 text-gray-400">
                                <span class="text-gray-500">[{{ $log['time'] }}]</span>
                                <span class="font-bold text-gray-200">$ {{ $log['command'] }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($log['status'] === 'success')
                                    <span class="text-emerald-400 font-bold flex items-center gap-1">
                                        <x-filament::icon icon="heroicon-m-check" class="w-3.5 h-3.5" />
                                        <span>Éxito</span>
                                    </span>
                                @elseif ($log['status'] === 'error')
                                    <span class="text-red-400 font-bold flex items-center gap-1">
                                        <x-filament::icon icon="heroicon-m-x-mark" class="w-3.5 h-3.5" />
                                        <span>Error</span>
                                    </span>
                                @else
                                    <span class="text-blue-400 font-bold">Info</span>
                                @endif
                                <span class="text-gray-500">({{ $log['duration'] }} ms)</span>
                            </div>
                        </div>

                        <div class="mt-2 text-gray-300 font-mono whitespace-pre-wrap break-words overflow-x-auto text-[11px]">
                            {{ $log['output'] }}
                        </div>
                    </div>
                @empty
                    <div wire:key="terminal-empty-log" class="py-12 text-center text-gray-500">
                        <x-filament::icon icon="heroicon-o-command-line" class="w-10 h-10 mx-auto mb-2 opacity-30 text-gray-400" />
                        <p class="font-medium text-gray-400">Ningún comando ejecutado en esta sesión</p>
                        <p class="text-[11px] text-gray-600 mt-1">
                            Haz clic en "Ejecutar Despliegue Completo" o en cualquiera de los botones de comandos arriba para ver la salida aquí.
                        </p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</x-filament-panels::page>
