@use('Illuminate\Support\Str')

@if (! $post)
    <p class="text-sm text-gray-500">
        Este comunicado ya no se encuentra vigente o no está disponible.
    </p>
@else
    @php
        $attachments = $post->getMedia('attachments');
    @endphp

    <div class="space-y-5">
        <div class="prose prose-sm max-w-none leading-relaxed text-gray-700">
            {!! Str::sanitizeHtml((string) ($post->content ?? '')) !!}
        </div>

        @if ($attachments->isNotEmpty())
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-700">
                    <x-filament::icon icon="heroicon-m-paper-clip" class="h-4 w-4 text-primary-600" />
                    <span>Archivos adjuntos ({{ $attachments->count() }})</span>
                </div>

                <ul class="space-y-2">
                    @foreach ($attachments as $attachment)
                        <li class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    {{ $attachment->file_name }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $attachment->human_readable_size }}
                                </p>
                            </div>

                            <a
                                href="{{ route('publications.attachment.download', ['media' => $attachment]) }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-500"
                            >
                                <x-filament::icon icon="heroicon-m-arrow-down-tray" class="h-3.5 w-3.5" />
                                <span>Descargar</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
