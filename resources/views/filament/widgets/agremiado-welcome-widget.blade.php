<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3.5">
                    @if ($user)
                        <x-filament-panels::avatar.user
                            size="lg"
                            :user="$user"
                            loading="lazy"
                        />
                    @endif

                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase tracking-wider text-primary-600">
                            Portal del Agremiado
                        </p>
                        <h2 class="truncate text-lg font-semibold text-gray-950 sm:text-xl">
                            Bienvenido, {{ $userName }}
                        </h2>
                        <p class="text-sm text-gray-500">
                            Accesos rápidos para gestionar tus trámites y expediente sindical.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($quickActions as $action)
                    @php
                        $iconClasses = match ($action['tone']) {
                            'primary' => 'bg-primary-50 text-primary-600 ring-primary-200/70',
                            'warning' => 'bg-warning-50 text-warning-600 ring-warning-200/70',
                            'danger' => 'bg-danger-50 text-danger-600 ring-danger-200/70',
                            'info' => 'bg-info-50 text-info-600 ring-info-200/70',
                            'success' => 'bg-success-50 text-success-600 ring-success-200/70',
                            default => 'bg-gray-100 text-gray-600 ring-gray-200/70',
                        };
                    @endphp

                    @if ($action['unavailable'] || blank($action['url']))
                        <div
                            aria-disabled="true"
                            class="flex items-start gap-3 rounded-xl border border-danger-200 bg-danger-50/50 p-3.5 text-left opacity-90"
                        >
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ring-1 {{ $iconClasses }}">
                                <x-filament::icon :icon="$action['icon']" class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-sm font-semibold text-gray-900">
                                        {{ $action['label'] }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs font-medium text-danger-700">
                                    {{ $action['description'] }}
                                </p>
                            </div>
                        </div>
                    @else
                        <a
                            href="{{ $action['url'] }}"
                            wire:navigate
                            class="group flex items-start gap-3 rounded-xl border border-gray-200 bg-white p-3.5 text-left transition hover:border-primary-300 hover:bg-primary-50/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                        >
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ring-1 transition {{ $iconClasses }}">
                                <x-filament::icon :icon="$action['icon']" class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm font-semibold text-gray-950 group-hover:text-primary-700">
                                        {{ $action['label'] }}
                                    </span>
                                    <x-filament::icon
                                        :icon="\Filament\Support\Icons\Heroicon::ArrowRight"
                                        class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-primary-600"
                                    />
                                </div>
                                <p class="mt-0.5 truncate text-xs text-gray-500">
                                    {{ $action['description'] }}
                                </p>
                            </div>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
