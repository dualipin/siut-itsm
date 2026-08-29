@props([
    'component',
    'props' => [],
    'client' => 'load', // 'load' | 'idle' | 'visible' | 'media'
    'media' => null,
    'tag' => 'div',
])

@php
    $serializedProps = !empty($props) ? json_encode($props, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) : null;
@endphp

<{{ $tag }}
    {{ $attributes->merge([
        'data-vue' => $component,
        'data-client' => $client,
        'data-client-media' => $media,
    ]) }}
>
    @if ($serializedProps)
        <script type="application/json" class="vue-island-props">{!! $serializedProps !!}</script>
    @endif

    @if ($slot->isNotEmpty())
        <div class="vue-island-fallback">
            {{ $slot }}
        </div>
    @endif
</{{ $tag }}>
