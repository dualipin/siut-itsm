@props([
    'name',
    'message',
    'image' => null,
    'tags' => [],
    'delay' => 100,
    'birthDate' => null,
    'isToday' => true,
])

<div
    class="card card-sm card-border bg-base-100 shadow-md hover:shadow-xl hover:border-primary/40 transition-all duration-300 w-full overflow-hidden group animate-fade-in-up animate-duration-normal animate-delay-{{ $delay }}">
    <!-- Encabezado festivo compacto con DaisyUI -->
    <div
        class="relative bg-linear-to-r from-primary/85 min-h-14 via-primary to-secondary/85 py-3 px-4 flex items-center justify-center overflow-hidden">
    </div>

    <!-- Avatar Central con Indicator y Avatar de DaisyUI -->
    <div class="flex justify-center -mt-8 relative z-10">
        <div class="avatar {{ empty($image) ? 'avatar-placeholder' : '' }}">
            <div
                class="w-16 h-16 rounded-full ring-3 ring-primary ring-offset-2 ring-offset-base-100 shadow-md bg-base-200 transition-transform duration-300 group-hover:scale-105">
                @if(!empty($image))
                    <img src="{{ $image }}" alt="{{ $name }}" class="object-cover" loading="lazy" />
                @else
                    <span class="text-base font-bold text-primary">
                        {{ strtoupper(mb_substr($name, 0, 2)) }}
                    </span>
                @endif
            </div>
        </div>

    </div>

    <!-- Cuerpo de la tarjeta con clases DaisyUI -->
    <div class="card-body items-center text-center pt-2 pb-5">
        <h3 class="card-title text-base sm:text-lg font-bold">
            {{ $name }}
        </h3>

        <span class="badge badge-xs badge-soft badge-primary font-medium tracking-wide -mt-1">
            ¡Cumpleaños Hoy! 🎈
        </span>

        <div class="divider divider-primary my-1 w-16 mx-auto opacity-30"></div>

        <p class="text-xs italic text-base-content/80 leading-relaxed font-serif max-w-xs">
            “{{ $message }}”
        </p>

        <!-- Etiquetas de deseos con card-actions y badges DaisyUI -->
        @if(!empty($tags))
            <div class="card-actions justify-center gap-1.5 mt-2">
                @foreach($tags as $index => $tag)
                    @php
                        $badgeStyles = [
                            'badge-soft badge-primary',
                            'badge-soft badge-secondary',
                            'badge-soft badge-accent',
                            'badge-soft badge-info',
                        ];
                        $badgeClass = $badgeStyles[$index % count($badgeStyles)];
                    @endphp
                    <span
                        class="badge badge-sm {{ $badgeClass }} text-[11px] font-medium py-1 px-2.5 transition-transform hover:scale-105">
                        {{ $tag }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>
</div>