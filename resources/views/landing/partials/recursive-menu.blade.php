@foreach($links as $link)
    <li>
        @if(isset($link['items']) && count($link['items']) > 0)
            <details class="group">
                <summary class="font-bold text-xs uppercase tracking-wider text-base-content/60 hover:text-primary py-2 px-3 rounded-lg hover:bg-base-200/50 transition-colors">
                    <span class="inline-flex items-center gap-2">
                        @if(!empty($link['icon']))
                            <i class="bi {{ $link['icon'] }} text-primary text-xs"></i>
                        @endif
                        {{ $link['label'] }}
                    </span>
                </summary>
                <ul class="my-0.5 space-y-0.5 pl-2">
                    @include('landing.partials.recursive-menu', ['links' => $link['items']])
                </ul>
            </details>
        @else
            @php
                $isCurrent = (request()->url() === url($link['href'] ?? ''));
            @endphp
            <a href="{{ $link['href'] ?? '#' }}"
               class="flex items-center justify-between gap-2.5 py-2 px-3 rounded-xl transition-all {{ $isCurrent ? 'bg-primary/10 text-primary font-bold shadow-2xs' : 'hover:bg-base-200 text-base-content/80 hover:text-base-content' }}">
                <div class="flex items-center gap-2.5 min-w-0">
                    @if(!empty($link['icon']))
                        <div class="w-7 h-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                            <i class="bi {{ $link['icon'] }} text-xs"></i>
                        </div>
                    @endif
                    <div class="min-w-0">
                        <div class="text-sm {{ $isCurrent ? 'font-bold text-primary' : 'font-medium text-base-content' }} truncate">
                            {{ $link['label'] }}
                        </div>
                        @if(!empty($link['description']))
                            <div class="text-[10px] text-base-content/50 truncate leading-tight">
                                {{ $link['description'] }}
                            </div>
                        @endif
                    </div>
                </div>
            </a>
        @endif
    </li>
@endforeach