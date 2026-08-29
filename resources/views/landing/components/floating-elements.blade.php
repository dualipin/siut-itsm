<div id="floatingElements"
    class="fixed bottom-4 inset-e-4 z-50 flex flex-col gap-2 transition-all duration-300 opacity-0 translate-y-5 invisible pointer-events-none">
    <!-- Facebook -->
    <a href="{{ $facebookUrl }}" target="_blank" rel="noopener noreferrer"
        class="btn btn-circle bg-blue-900 hover:bg-blue-900/90 text-white border-none shadow-md" aria-label="Facebook">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-5 fill-current" viewBox="0 0 24 24">
            <path
                d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z" />
        </svg>
    </a>

    <!-- WhatsApp -->
    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer"
        class="btn btn-circle bg-green-500 hover:bg-green-500/90 text-white border-none shadow-md"
        aria-label="WhatsApp">
        <svg role="img" class="size-5 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <title>WhatsApp</title>
            <path
                d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
        </svg>
    </a>

    <!-- Back to Top -->
    <button type="button" onclick="window.scrollTo({ top: 0, behavior: 'smooth' })"
        class="btn btn-circle btn-primary text-primary-content shadow-md" aria-label="Volver arriba">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
            stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" />
        </svg>
    </button>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const floatingElements = document.getElementById('floatingElements');

        const toggleVisibility = () => {
            if (window.scrollY > 200) {
                // Mostrar componente
                floatingElements.classList.remove('opacity-0', 'translate-y-5', 'invisible', 'pointer-events-none');
                floatingElements.classList.add('opacity-100', 'translate-y-0', 'visible');
            } else {
                // Ocultar componente
                floatingElements.classList.add('opacity-0', 'translate-y-5', 'invisible', 'pointer-events-none');
                floatingElements.classList.remove('opacity-100', 'translate-y-0', 'visible');
            }
        };

        window.addEventListener('scroll', toggleVisibility);
        toggleVisibility(); // Ejecutar validación inicial
    });
</script>