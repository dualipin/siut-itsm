<section class="py-10 px-4 max-w-7xl mx-auto">
    <div class="flex flex-col items-center text-center mb-8 animate-fade-in-down animate-duration-fast">
        <span class="badge badge-soft badge-primary text-xs font-semibold tracking-wider uppercase mb-1.5">
            Comunidad Sindical
        </span>
        <h2 class="text-2xl sm:text-3xl font-bold font-display text-balance text-base-content">
            {{ $title }}
        </h2>
        @if($subtitle)
            <p class="text-base-content/70 text-xs sm:text-sm text-pretty max-w-lg mt-1">
                {{ $subtitle }}
            </p>
        @endif
    </div>

    <div class="flex flex-wrap justify-center items-stretch gap-6">
        @foreach($celebrants as $index => $persona)
            <div class="flex flex-1 min-w-65 max-w-sm">
                @include('landing.components.home-birthday-card', [
                    'name' => $persona['name'], 
                    'message' => $persona['message'],
                    'image' => $persona['image'] ?? null,
                    'tags' => $persona['tags'] ?? [],
                    'delay' => ($index + 1) * 100,
                    'birthDate' => $persona['birth_date'] ?? null,
                    'isToday' => true,
                ])
            </div>
        @endforeach
    </div>

    <div data-vue="confetti"></div>
</section>