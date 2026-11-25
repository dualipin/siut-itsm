<x-filament-widgets::widget>
    <x-filament::section :compact="true">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="truncate text-lg font-semibold tracking-tight text-gray-950 sm:text-xl">
                Bienvenido, {{ $userName }}
            </h2>

            <div class="flex flex-col gap-2.5 sm:flex-row sm:flex-wrap sm:items-center">
                @if ($primaryAction['unavailable'] || blank($primaryAction['url']))
                    <span
                        aria-disabled="true"
                        class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-danger-50 px-3 py-2 text-xs font-medium text-danger-700 ring-1 ring-inset ring-danger-600/20"
                    >
                        <x-filament::icon :icon="$primaryAction['icon']" class="h-4 w-4 shrink-0" />
                        <span>
                            {{ $primaryAction['label'] }}@if (filled($primaryAction['description'])) · {{ $primaryAction['description'] }}@endif
                        </span>
                    </span>
                @else
                    <x-filament::button
                        tag="a"
                        :href="$primaryAction['url']"
                        wire:navigate
                        :color="$primaryAction['color']"
                        :icon="$primaryAction['icon']"
                    >
                        {{ $primaryAction['label'] }}
                    </x-filament::button>
                @endif

                <x-filament::button
                    tag="a"
                    :href="$secondaryAction['url']"
                    wire:navigate
                    color="gray"
                    outlined
                    :icon="$secondaryAction['icon']"
                >
                    {{ $secondaryAction['label'] }}
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
