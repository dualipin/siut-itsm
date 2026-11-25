@use('App\Enums\PostType')
@use('Illuminate\Support\Str')

<x-filament-widgets::widget>
    <x-filament::section
        heading="Comunicados recientes"
        icon="heroicon-o-megaphone"
        icon-color="primary"
        :has-content-el="false"
    >
        @if ($announcements->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-10 text-center">
                <x-filament::icon
                    icon="heroicon-o-megaphone"
                    class="mb-2.5 h-8 w-8 text-gray-400"
                />
                <p class="text-sm font-medium text-gray-700">
                    No hay comunicados vigentes por el momento.
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    Los nuevos avisos y noticias del sindicato se mostrarán en esta sección.
                </p>
            </div>
        @else
            <div class="divide-y divide-gray-200 overflow-hidden rounded-b-xl">
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
                        aria-label="Leer comunicado: {{ $announcement->title }}"
                        class="group flex w-full cursor-pointer items-center justify-between gap-4 px-6 py-3.5 text-left transition hover:bg-gray-50/80 focus:outline-none focus-visible:bg-gray-50 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-600"
                    >
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
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
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-500">
                                        <x-filament::icon icon="heroicon-m-paper-clip" class="h-3.5 w-3.5" aria-hidden="true" />
                                        <span>{{ trans_choice('{1} :count adjunto|[2,*] :count adjuntos', $attachmentsCount) }}</span>
                                    </span>
                                @endif
                            </div>

                            <h3 class="text-sm font-semibold leading-snug text-gray-950 transition-colors group-hover:text-primary-600">
                                {{ $announcement->title }}
                            </h3>

                            @if ($excerpt !== '')
                                <p class="truncate text-xs text-gray-500">
                                    {{ $excerpt }}
                                </p>
                            @endif
                        </div>

                        <x-filament::icon
                            icon="heroicon-m-chevron-right"
                            class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-primary-600"
                            aria-hidden="true"
                        />
                    </button>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
