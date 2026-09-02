@php
  /** @var \Illuminate\Database\Eloquent\Collection<\App\Models\Post> $slides */
@endphp

@if ($slides && $slides->isNotEmpty())
  <div class="carousel w-full h-full flex-1">
    @foreach ($slides as $index => $slide)
      <div id="slide{{ $index + 1 }}" class="carousel-item relative w-full {{ $index === 0 ? '' : 'hidden' }}">
        <img
          alt="{{ $slide->title }}"
          src="{{ $slide->thumbnail_url }}"
          class="w-full h-100 object-cover" />
        
        <!-- Overlay with Title and Content -->
        <div class="absolute bottom-10 left-6 sm:left-10 right-6 sm:right-10 bg-black/60 backdrop-blur-xs text-white p-5 sm:p-6 rounded-3xl border border-white/10 max-w-3xl">
          <div class="flex items-center gap-2 mb-2">
            <span class="badge badge-sm font-semibold {{ $slide->type?->getBadgeClass() ?? 'badge-primary' }}">
              <i class="bi {{ $slide->type?->getIcon() ?? 'bi-file-text' }} mr-1"></i>
              {{ $slide->type?->getLabel() ?? 'Publicación' }}
            </span>
            <span class="text-xs text-white/70">{{ $slide->created_at->translatedFormat('d M, Y') }}</span>
          </div>
          <h2 class="text-xl sm:text-2xl md:text-3xl font-black mb-2 line-clamp-2 leading-snug">
            <a href="{{ route('publications.show', ['type' => $slide->type_slug, 'slug' => $slide->slug]) }}" class="hover:text-primary transition-colors">
              {{ $slide->title }}
            </a>
          </h2>
          <p class="text-xs sm:text-sm text-white/80 line-clamp-2 mb-4 leading-relaxed">{{ Str::limit(strip_tags($slide->content), 140) }}</p>
          <a href="{{ route('publications.show', ['type' => $slide->type_slug, 'slug' => $slide->slug]) }}"
             class="btn btn-sm btn-primary rounded-xl font-semibold inline-flex items-center gap-1.5 shadow-sm">
            <span>Leer publicación</span>
            <i class="bi bi-arrow-right text-xs"></i>
          </a>
        </div>

        @if ($slides->count() > 1)
          <div class="absolute left-5 right-5 top-1/2 flex -translate-y-1/2 transform justify-between">
            <button type="button" class="btn btn-circle btn-prev">❮</button>
            <button type="button" class="btn btn-circle btn-next">❯</button>
          </div>
        @endif
      </div>
    @endforeach
  </div>
@else
  <!-- Default Placeholder Slide -->
  <div class="carousel w-full">
    <div class="carousel-item relative w-full">
      <img
        alt="Default Slide"
        src="https://img.daisyui.com/images/stock/photo-1625726411847-8cbb60cc71e6.webp"
        class="w-full h-100 object-cover" />
      <div class="absolute bottom-10 left-10 right-10 bg-black/50 text-white p-5 rounded-box">
        <h2 class="text-2xl font-bold mb-2">Bienvenido a OST-SIUT-ITSM</h2>
        <p class="text-sm">No hay publicaciones disponibles en este momento.</p>
      </div>
    </div>
  </div>
@endif

<script>
  const slides = document.querySelectorAll('.carousel-item');
  let currentSlide = 0;

  function showSlide(index) {
    if (slides.length === 0) return;
    slides.forEach((slide, i) => {
      if (i === index) {
        slide.classList.remove('hidden');
        slide.classList.add('animate-fade-in');
      } else {
        slide.classList.add('hidden');
        slide.classList.remove('animate-fade-in');
      }
    });
  }

  function nextSlide() {
    if (slides.length === 0) return;
    currentSlide = (currentSlide + 1) % slides.length;
    showSlide(currentSlide);
  }

  function prevSlide() {
    if (slides.length === 0) return;
    currentSlide = (currentSlide - 1 + slides.length) % slides.length;
    showSlide(currentSlide);
  }

  document.querySelectorAll('.btn-prev').forEach(button => {
    button.addEventListener('click', prevSlide);
  });

  document.querySelectorAll('.btn-next').forEach(button => {
    button.addEventListener('click', nextSlide);
  });

  // Auto-slide every 5 seconds if there are multiple slides
  if (slides.length > 1) {
    setInterval(nextSlide, 5000);
  }

  // Initialize the carousel
  showSlide(currentSlide);
</script>