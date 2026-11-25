@use('Illuminate\Support\Str')

@if (! $post)
    <p class="text-sm text-gray-500">
        Este comunicado ya no está disponible o su vigencia ha finalizado.
    </p>
@else
    @php
        $attachments = $post->getMedia('attachments');
        $rawContent = trim((string) ($post->content ?? ''));
        $hasHtmlMarkup = (bool) preg_match('/<[a-z][\s\S]*>/i', $rawContent);
        $renderedContent = $hasHtmlMarkup
            ? Str::sanitizeHtml($rawContent)
            : collect(preg_split("/\r\n\r\n|\n\n|\r\r/", $rawContent) ?: [])
                ->filter(fn (string $paragraph): bool => trim($paragraph) !== '')
                ->map(fn (string $paragraph): string => '<p>' . nl2br(e(trim($paragraph))) . '</p>')
                ->implode('');
    @endphp

    <div class="space-y-5">
        <div class="fi-prose max-w-none text-sm leading-relaxed">
            {!! $renderedContent !!}
        </div>

        @if ($attachments->isNotEmpty())
            <div class="border-t border-gray-200 pt-4">
                <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-600">
                    <x-filament::icon icon="heroicon-m-paper-clip" class="h-4 w-4 text-primary-600" />
                    <span>Archivos adjuntos ({{ $attachments->count() }})</span>
                </div>

                <ul class="divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200 bg-gray-50/50">
                    @foreach ($attachments as $attachment)
                        <li class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-950">
                                    {{ $attachment->file_name }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $attachment->human_readable_size }}
                                </p>
                            </div>

                            <x-filament::button
                                tag="a"
                                :href="route('publications.attachment.download', ['media' => $attachment])"
                                target="_blank"
                                rel="noopener noreferrer"
                                color="primary"
                                size="xs"
                                icon="heroicon-m-arrow-down-tray"
                            >
                                Descargar
                            </x-filament::button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
