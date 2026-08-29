@if ($theme)
    <style>
        :root {
            @foreach ($theme->getAttributes() as $key => $value)
                @php
                    // Obtenemos la primera palabra antes del guión bajo (color, radius, size, etc.)
                    $prefijo = explode('_', $key)[0];
                @endphp

                @if (in_array($prefijo, ['color', 'radius', 'size']))
                    --siut-{{ str_replace('_', '-', $key) }}: {{ $value }}{{ in_array($prefijo, ['radius', 'size']) ? 'rem' : '' }};
                @endif
            @endforeach 
            
            --siut-border-width: {{ $theme->border_width }}px;
        }
    </style>
@endif