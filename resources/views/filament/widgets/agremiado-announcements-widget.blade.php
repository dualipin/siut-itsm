@use('App\Enums\PostType')
@use('Illuminate\Support\Str')

<x-filament-widgets::widget>
    <x-filament::section
        heading="Comunicados recientes"
        description="Últimos avisos y noticias vigentes del sindicato."
        icon="heroicon-o-megaphone"
        icon-color="primary"
    >
        @if ($announcements->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 bg-gray-50/60 px-6 py-8 text-center">
                <x-filament::icon
                    icon="heroicon-o-megaphone"
                    class="mb-2 h-8 w-8 text-gray-400"
                />
                <p class="text-sm font-medium text-gray-700">
                    No hay comunicados vigentes por el momento.
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    Cuando el sindicato publique nuevos avisos o noticias aparecerán aquí.
                </p>
            </div>
        @else
            <div class="divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 bg-white">
                @foreach ($announcements as $announcement)
                    @php
                        $attachmentsCount = $announcement->getMedia('attachments')->count();
                        $excerpt = Str::limit(
                            trim((string) preg_replace(
                                '/\s+/',
                                ' ',
                                html_entity_decode(strip_tags((string) ($announcement->content ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                            )),
                            150,
                        );
                    @endphp

                    <button
                        type="button"
                        wire:key="announcement-{{ $announcement->id }}"
                        wire:click="mountAction('readAnnouncement', { post: {{ $announcement->id }} })"
                        class="group flex w-full cursor-pointer items-start justify-between gap-4 px-4 py-3.5 text-left transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    >
                        <div class="min-w-0 flex-1 space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-filament::badge
                                    :color="$announcement->type === PostType::Aviso ? 'warning' : 'info'"
                                    size="sm"
                                >
                                    {{ $announcement->type?->getLabel() }}
                                </x-filament::badge>

                                <span class="text-xs text-gray-500">
                                    {{ $announcement->created_at->translatedFormat('d M Y') }}
                                </span>

                                @if ($attachmentsCount > 0)
                                    <x-filament::badge
                                        color="gray"
                                        size="sm"
                                        icon="heroicon-m-paper-clip"
                                    >
                                        {{ $attachmentsCount }} {{ $attachmentsCount === 1 ? 'adjunto' : 'adjuntos' }}
                                    </x-filament::badge>
                                @endif
                            </div>

                            <h3 class="text-sm font-semibold text-gray-900 transition-colors group-hover:text-primary-600">
                                {{ $announcement->title }}
                            </h3>

                            @if ($excerpt !== '')
                                <p class="line-clamp-2 text-xs leading-relaxed text-gray-600">
                                    {{ $excerpt }}
                                </p>
                            @endif
                        </div>

                        <div class="flex shrink-0 items-center gap-1 self-center text-xs font-medium text-primary-600">
                            <span class="hidden sm:inline">Leer</span>
                            <x-filament::icon
                                icon="heroicon-m-chevron-right"
                                class="h-4 w-4 transition-transform group-hover:translate-x-0.5"
                            />
                        </div>
                    </button>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
