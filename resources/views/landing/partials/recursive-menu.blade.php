@foreach($links as $link)
    <li>
        @if(isset($link['items']) && count($link['items']) > 0)
            <details>
                <summary>{{ $link['label'] }}</summary>
                <ul>
                    @include('landing.partials.recursive-menu', ['links' => $link['items']])
                </ul>
            </details>
        @else
            <a href="{{ $link['href'] ?? '#' }}">{{ $link['label'] }}</a>
        @endif
    </li>
@endforeach