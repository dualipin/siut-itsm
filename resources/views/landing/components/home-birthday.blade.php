<section class="py-6 px-4 max-w-7xl mx-auto">
    <div class="flex flex-col items-center text-center mb-6 animate-fade-in-down animate-duration-fast">
        <span class="badge badge-soft badge-primary text-xs font-semibold tracking-wider uppercase mb-1">
            🎉 Muchas Felicidades 🎉
        </span>
        <h2 class="text-2xl sm:text-3xl font-bold font-display text-base-content">
            Cumpleañeros de Hoy
        </h2>
        <p class="text-base-content/60 text-xs sm:text-sm max-w-md mt-0.5">
            Enviamos nuestros más cálidos deseos y felicitaciones a quienes celebran hoy su día.
        </p>
    </div>

    <div class="flex flex-wrap justify-center items-stretch gap-5 sm:gap-6">
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