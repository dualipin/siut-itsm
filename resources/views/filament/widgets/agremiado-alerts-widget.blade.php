<x-filament-widgets::widget>
    @if ($alerts !== [])
        <div data-testid="agremiado-alerts-widget" class="space-y-3">
            @foreach ($alerts as $alert)
                <x-filament::callout
                    :key="$alert['key']"
                    :color="$alert['color']"
                    :icon="$alert['icon']"
                    :heading="$alert['heading']"
                    :description="$alert['description']"
                >
                    @if ($alert['actionUrl'] && $alert['actionLabel'])
                        <x-slot name="controls">
                            <x-filament::button
                                tag="a"
                                :href="$alert['actionUrl']"
                                :target="$alert['newTab'] ? '_blank' : null"
                                :color="$alert['color']"
                                size="sm"
                            >
                                {{ $alert['actionLabel'] }}
                            </x-filament::button>
                        </x-slot>
                    @endif
                </x-filament::callout>
            @endforeach
        </div>
    @endif
</x-filament-widgets::widget>
