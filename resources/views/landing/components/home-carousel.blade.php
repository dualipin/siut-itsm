@php
  /** @var \Illuminate\Database\Eloquent\Collection<\App\Models\Post> $slides */
@endphp

@if ($slides && $slides->isNotEmpty())
  <div class="carousel w-full">
    @foreach ($slides as $index => $slide)
      <div id="slide{{ $index + 1 }}" class="carousel-item relative w-full {{ $index === 0 ? '' : 'hidden' }}">
        <img
          alt="{{ $slide->title }}"
          src="{{ $slide->thumbnail ?? 'https://img.daisyui.com/images/stock/photo-1625726411847-8cbb60cc71e6.webp' }}"
          class="w-full h-100 object-cover" />
        
        <!-- Overlay with Title and Content -->
        <div class="absolute bottom-10 left-10 right-10 bg-black/50 text-white p-5 rounded-box">
          <h2 class="text-2xl font-bold mb-2">{{ $slide->title }}</h2>
          <p class="text-sm line-clamp-2">{{ Str::limit($slide->content, 150) }}</p>
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