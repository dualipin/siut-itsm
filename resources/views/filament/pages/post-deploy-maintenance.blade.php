<x-filament-panels::page>
    @php
        $diag = $this->getDiagnostics();
        $storage = $diag['storage'];
        $db = $diag['database'];
        $caches = $diag['caches'];
        $env = $diag['environment'];
        $perms = $diag['permissions'];
        $logsCount = count($terminalLogs);
        $hasStoragePermissions = $perms['storage'] && $perms['storage_logs'];
    @endphp

    <div class="space-y-6">

        {{-- ESTADO DEL ENTORNO Y ACCIÓN PRINCIPAL --}}
        <section class="overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-xs">
            <div class="flex flex-col gap-4 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-gray-950">Estado del entorno</h2>
                    <p class="mt-0.5 truncate text-xs text-gray-500 tabular-nums" title="{{ $env['app_url'] }}">
                        PHP {{ $env['php_version'] }} &middot; {{ $env['app_url'] }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <x-filament::button
                        wire:click="toggleMaintenance"
                        wire:confirm="{{ $env['is_down'] ? 'El sitio volverá a estar disponible para todos los usuarios. ¿Deseas reactivarlo?' : 'El sitio público dejará de estar disponible para los agremiados mientras el modo mantenimiento esté activo. ¿Deseas activarlo?' }}"
                        wire:loading.attr="disabled"
                        size="sm"
                        :color="$env['is_down'] ? 'success' : 'gray'"
                        :icon="$env['is_down'] ? 'heroicon-m-play' : 'heroicon-m-pause'"
                        outlined
                    >
                        {{ $env['is_down'] ? 'Reactivar sitio' : 'Poner en mantenimiento' }}
                    </x-filament::button>

                    <x-filament::button
                        wire:click="runFullDeploy"
                        wire:loading.attr="disabled"
                        size="sm"
                        color="primary"
                        icon="heroicon-m-rocket-launch"
                    >
                        <span wire:loading.remove wire:target="runFullDeploy">Ejecutar despliegue completo</span>
                        <span wire:loading wire:target="runFullDeploy">Ejecutando despliegue...</span>
                    </x-filament::button>
                </div>
            </div>

            <div class="grid grid-cols-1 divide-y divide-gray-100 lg:grid-cols-4 lg:divide-x lg:divide-y-0">

                {{-- Almacenamiento --}}
                <div class="px-5 py-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-medium text-gray-900">Almacenamiento</h3>
                        @if ($storage['link_is_valid'])
                            <x-filament::badge color="success" size="sm">Enlazado</x-filament::badge>
                        @elseif ($storage['link_exists'])
                            <x-filament::badge color="danger" size="sm">Enlace dañado</x-filament::badge>
                        @else
                            <x-filament::badge color="info" size="sm">Vía Laravel</x-filament::badge>
                        @endif
                    </div>

                    <dl class="mt-3 space-y-1.5 text-xs">
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-gray-500">Entrega</dt>
                            <dd class="font-mono text-gray-700 truncate">
                                {{ $storage['link_is_valid'] ? 'storage -> app/public' : ($storage['link_exists'] ? 'Enlace inválido' : 'Ruta /storage') }}
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-gray-500">symlink()</dt>
                            <dd class="font-medium {{ $storage['symlink_function_enabled'] ? 'text-gray-700' : 'text-amber-700' }}">
                                {{ $storage['symlink_function_enabled'] ? 'Habilitado' : 'Deshabilitado' }}
                            </dd>
                        </div>
                        @if ($storage['public_html_detected'])
                            <div class="flex items-baseline justify-between gap-2">
                                <dt class="text-gray-500">public_html</dt>
                                <dd class="font-mono text-gray-700">Detectado</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Base de Datos --}}
                <div class="px-5 py-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-medium text-gray-900">Base de Datos</h3>
                        @if (! $db['connected'])
                            <x-filament::badge color="danger" size="sm">Sin conexión</x-filament::badge>
                        @elseif ($db['pending_count'] > 0)
                            <x-filament::badge color="warning" size="sm">
                                {{ $db['pending_count'] === 1 ? '1 pendiente' : $db['pending_count'].' pendientes' }}
                            </x-filament::badge>
                        @else
                            <x-filament::badge color="success" size="sm">Al día</x-filament::badge>
                        @endif
                    </div>

                    <dl class="mt-3 space-y-1.5 text-xs">
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-gray-500">Base</dt>
                            <dd class="font-mono text-gray-700 truncate max-w-[160px]" title="{{ $db['name'] }}">
                                {{ strtoupper($db['driver']) }}{{ $db['name'] ? ' · '.$db['name'] : '' }}
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-gray-500">Migraciones</dt>
                            <dd class="tabular-nums font-medium {{ $db['pending_count'] > 0 ? 'text-amber-700' : 'text-gray-700' }}">
                                @if ($db['pending_count'] === 0)
                                    Sin pendientes
                                @elseif ($db['pending_count'] === 1)
                                    1 pendiente
                                @else
                                    {{ $db['pending_count'] }} pendientes
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                {{-- Cachés --}}
                <div class="px-5 py-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-medium text-gray-900">Cachés</h3>
                        @if ($caches['config_cached'] && $caches['routes_cached'])
                            <x-filament::badge color="success" size="sm">Activas</x-filament::badge>
                        @elseif ($caches['config_cached'] || $caches['routes_cached'])
                            <x-filament::badge color="warning" size="sm">Parcial</x-filament::badge>
                        @else
                            <x-filament::badge color="gray" size="sm">Sin cachear</x-filament::badge>
                        @endif
                    </div>

                    <dl class="mt-3 space-y-1.5 text-xs">
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-gray-500">Configuración</dt>
                            <dd class="text-gray-700">{{ $caches['config_cached'] ? 'Cacheada' : 'Sin cachear' }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-gray-500">Rutas</dt>
                            <dd class="text-gray-700">{{ $caches['routes_cached'] ? 'Cacheadas' : 'Sin cachear' }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-gray-500">Vistas</dt>
                            <dd class="tabular-nums font-mono text-gray-700">
                                {{ $caches['views_count'] === 1 ? '1 compilada' : $caches['views_count'].' compiladas' }}
                            </dd>
                        </div>
                    </dl>
                </div>

                {{-- Servidor --}}
                <div class="px-5 py-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-medium text-gray-900">Servidor</h3>
                        @if ($env['is_down'])
                            <x-filament::badge color="danger" size="sm">En mantenimiento</x-filament::badge>
                        @elseif ($env['app_env'] === 'production')
                            <x-filament::badge color="success" size="sm">Producción</x-filament::badge>
                        @else
                            <x-filament::badge color="info" size="sm">{{ ucfirst($env['app_env']) }}</x-filament::badge>
                        @endif
                    </div>

                    <dl class="mt-3 space-y-1.5 text-xs">
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-gray-500">Depuración</dt>
                            <dd class="font-medium {{ $env['app_debug'] ? 'text-amber-700' : 'text-gray-700' }}">
                                {{ $env['app_debug'] ? 'Activada' : 'Desactivada' }}
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-gray-500">Carpeta storage</dt>
                            <dd class="font-medium {{ $hasStoragePermissions ? 'text-gray-700' : 'text-red-700' }}">
                                {{ $hasStoragePermissions ? 'Con escritura' : 'Sin escritura' }}
                            </dd>
                        </div>
                    </dl>
                </div>

            </div>
        </section>

        {{-- ACCIONES INDIVIDUALES --}}
        <section
            x-data="{ showAdvanced: false }"
            class="overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-xs divide-y divide-gray-100"
        >
            <div class="bg-gray-50/60 px-5 py-3">
                <h2 class="text-sm font-semibold text-gray-950">Acciones individuales</h2>
            </div>

            <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h3 class="text-sm font-medium text-gray-900">Almacenamiento de archivos</h3>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Úsalo si las fotos o documentos devuelven error 404 tras subir archivos al servidor.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <x-filament::button
                        wire:click="repairStorageLink"
                        wire:loading.attr="disabled"
                        size="xs"
                        color="gray"
                        icon="heroicon-m-wrench"
                        outlined
                    >
                        Reparar enlace
                    </x-filament::button>
                    <x-filament::button
                        wire:click="testStorageAccess"
                        wire:loading.attr="disabled"
                        size="xs"
                        color="gray"
                        icon="heroicon-m-magnifying-glass"
                        outlined
                    >
                        Probar acceso
                    </x-filament::button>
                </div>
            </div>

            <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h3 class="text-sm font-medium text-gray-900">Migraciones de base de datos</h3>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Aplica los cambios pendientes en las tablas o consulta cuáles faltan por ejecutarse.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <x-filament::button
                        wire:click="runMigrate"
                        wire:confirm="Se aplicarán las migraciones pendientes sobre la base de datos activa. ¿Deseas continuar?"
                        wire:loading.attr="disabled"
                        size="xs"
                        color="gray"
                        icon="heroicon-m-arrow-path"
                        outlined
                    >
                        Aplicar migraciones
                    </x-filament::button>
                    <x-filament::button
                        wire:click="runMigrateStatus"
                        wire:loading.attr="disabled"
                        size="xs"
                        color="gray"
                        icon="heroicon-m-list-bullet"
                        outlined
                    >
                        Consultar estado
                    </x-filament::button>
                </div>
            </div>

            <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h3 class="text-sm font-medium text-gray-900">Cachés de la aplicación</h3>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Regenera la caché de configuración y rutas para mayor rapidez, o límpiala si editaste el archivo <code class="font-mono text-gray-700">.env</code>.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <x-filament::button
                        wire:click="runOptimize"
                        wire:loading.attr="disabled"
                        size="xs"
                        color="gray"
                        icon="heroicon-m-bolt"
                        outlined
                    >
                        Regenerar cachés
                    </x-filament::button>
                    <x-filament::button
                        wire:click="runOptimizeClear"
                        wire:loading.attr="disabled"
                        size="xs"
                        color="danger"
                        icon="heroicon-m-trash"
                        outlined
                    >
                        Limpiar cachés
                    </x-filament::button>
                </div>
            </div>

            <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h3 class="text-sm font-medium text-gray-900">Colas en segundo plano</h3>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Reinicia los procesos en segundo plano para que tomen el código nuevo después de actualizar.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <x-filament::button
                        wire:click="runQueueRestart"
                        wire:loading.attr="disabled"
                        size="xs"
                        color="gray"
                        icon="heroicon-m-arrow-path-rounded-square"
                        outlined
                    >
                        Reiniciar colas
                    </x-filament::button>
                </div>
            </div>

            <div>
                <button
                    type="button"
                    @click="showAdvanced = !showAdvanced"
                    :aria-expanded="showAdvanced.toString()"
                    class="flex w-full items-center justify-between gap-3 px-5 py-3.5 text-left transition-colors hover:bg-gray-50/70 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-primary-500"
                >
                    <div class="min-w-0">
                        <span class="text-xs font-medium text-gray-700">Comandos específicos de Artisan</span>
                        <span class="ml-2 hidden text-xs text-gray-400 sm:inline">Ejecución individual por subsistema</span>
                    </div>
                    <x-filament::icon
                        icon="heroicon-m-chevron-down"
                        class="h-4 w-4 shrink-0 text-gray-400 transition-transform duration-150"
                        ::class="showAdvanced && 'rotate-180'"
                    />
                </button>

                <div
                    x-show="showAdvanced"
                    x-cloak
                    x-transition.opacity.duration.150ms
                    class="border-t border-gray-100 bg-gray-50/40 px-5 py-4"
                >
                    <div class="flex flex-wrap gap-2">
                        <x-filament::button wire:click="runStorageLinkStandard" wire:loading.attr="disabled" size="xs" color="gray" outlined class="font-mono">
                            storage:link
                        </x-filament::button>
                        <x-filament::button wire:click="runViewCache" wire:loading.attr="disabled" size="xs" color="gray" outlined class="font-mono">
                            view:cache
                        </x-filament::button>
                        <x-filament::button wire:click="runIconsCache" wire:loading.attr="disabled" size="xs" color="gray" outlined class="font-mono">
                            icons:cache
                        </x-filament::button>
                        <x-filament::button wire:click="runConfigClear" wire:loading.attr="disabled" size="xs" color="gray" outlined class="font-mono">
                            config:clear
                        </x-filament::button>
                        <x-filament::button wire:click="runRouteClear" wire:loading.attr="disabled" size="xs" color="gray" outlined class="font-mono">
                            route:clear
                        </x-filament::button>
                        <x-filament::button wire:click="runViewClear" wire:loading.attr="disabled" size="xs" color="gray" outlined class="font-mono">
                            view:clear
                        </x-filament::button>
                        <x-filament::button wire:click="runCacheClear" wire:loading.attr="disabled" size="xs" color="gray" outlined class="font-mono">
                            cache:clear
                        </x-filament::button>
                        <x-filament::button wire:click="runIconsClear" wire:loading.attr="disabled" size="xs" color="gray" outlined class="font-mono">
                            icons:clear
                        </x-filament::button>
                        <x-filament::button wire:click="runPackageDiscover" wire:loading.attr="disabled" size="xs" color="gray" outlined class="font-mono">
                            package:discover
                        </x-filament::button>
                        <x-filament::button
                            wire:click="runStorageUnlink"
                            wire:confirm="Se eliminará el enlace simbólico de almacenamiento público y los archivos enlazados dejarán de servirse directamente. ¿Deseas eliminarlo?"
                            wire:loading.attr="disabled"
                            size="xs"
                            color="danger"
                            outlined
                            class="font-mono"
                        >
                            storage:unlink
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </section>

        {{-- CONSOLA DE SALIDA --}}
        <section class="overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-xs">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 bg-gray-50/60 px-5 py-3">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold text-gray-950">Consola de salida</h2>
                    @if ($logsCount > 0)
                        <span class="tabular-nums text-xs text-gray-500">
                            {{ $logsCount === 1 ? '1 registro' : $logsCount.' registros' }}
                        </span>
                    @endif
                </div>

                @if ($logsCount > 0)
                    <x-filament::button
                        wire:click="clearTerminalLogs"
                        size="xs"
                        color="gray"
                        icon="heroicon-m-trash"
                        outlined
                    >
                        Limpiar historial
                    </x-filament::button>
                @endif
            </div>

            @if ($logsCount > 0)
                <div class="max-h-[420px] overflow-y-auto divide-y divide-gray-800/80 bg-gray-950 font-mono text-xs text-gray-200">
                    @foreach ($terminalLogs as $index => $log)
                        <div wire:key="terminal-log-{{ $index }}" class="px-5 py-3.5">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-[11px]">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="tabular-nums text-gray-500">{{ $log['time'] }}</span>
                                    <span class="truncate font-medium text-gray-100">$ {{ $log['command'] }}</span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    @if ($log['status'] === 'success')
                                        <span class="inline-flex items-center gap-1 font-medium text-emerald-400">
                                            <x-filament::icon icon="heroicon-m-check" class="h-3.5 w-3.5" />
                                            <span>Éxito</span>
                                        </span>
                                    @elseif ($log['status'] === 'error')
                                        <span class="inline-flex items-center gap-1 font-medium text-red-400">
                                            <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                                            <span>Error</span>
                                        </span>
                                    @else
                                        <span class="font-medium text-sky-400">Info</span>
                                    @endif
                                    <span class="tabular-nums text-gray-500">{{ $log['duration'] }} ms</span>
                                </div>
                            </div>

                            <pre class="mt-2 whitespace-pre-wrap break-words font-mono text-[11px] leading-relaxed text-gray-300">{{ $log['output'] }}</pre>
                        </div>
                    @endforeach
                </div>
            @else
                <div wire:key="terminal-empty-log" class="px-5 py-8 text-center">
                    <p class="text-sm text-gray-500">Ningún comando ejecutado en esta sesión.</p>
                    <p class="mt-1 text-xs text-gray-400">
                        Ejecuta el despliegue completo o una acción individual para consultar su salida aquí.
                    </p>
                </div>
            @endif
        </section>

    </div>
</x-filament-panels::page>
