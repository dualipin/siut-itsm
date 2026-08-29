<footer class="mt-auto bg-primary text-primary-content py-12 border-t-8 border-secondary">
    <div class="container mx-auto px-4">
        <!-- Logo y descripción -->
        <div class="text-center mb-12 pb-8 border-b border-secondary/30">
            <img src="{{ asset($syndicate['logo']['src']) }}" alt="{{ $syndicate['logo']['alt'] }}"
                class="rounded-full mb-6 shadow-md mx-auto object-cover size-48" />
            <p class="text-2xl mb-0">{{ $syndicate['name'] }}</p>
        </div>

        <!-- Grid de 3 columnas para la información -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-10 text-left">

            <!-- Contacto -->
            <div>
                <h3 class="text-lg font-semibold mb-6 pb-2 border-b border-secondary/30 uppercase tracking-widest">
                    Contacto
                </h3>
                <ul class="space-y-4">
                    <li class="flex items-start gap-3">
                        <!-- Icono Phone -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-secondary shrink-0 mt-0.5"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path
                                d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                        </svg>
                        <a href="tel:{{ $syndicate['phone'] }}" class="hover:underline">
                            {{ $syndicate['phone'] }}
                        </a>
                    </li>
                    <li class="flex items-start gap-3">
                        <!-- Icono Mail -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-secondary shrink-0 mt-0.5"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <rect width="20" height="16" x="2" y="4" rx="2" />
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                        </svg>
                        <a href="mailto:{{ $syndicate['email'] }}" class="hover:underline lowercase">
                            {{ $syndicate['email'] }}
                        </a>
                    </li>
                    <li class="flex items-start gap-3">
                        <!-- Icono MapPin -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-secondary shrink-0 mt-0.5"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z" />
                            <circle cx="12" cy="10" r="3" />
                        </svg>
                        <span class="leading-tight">{{ $syndicate['address'] }}</span>
                    </li>
                </ul>
            </div>

            <!-- Enlaces rápidos -->
            <div>
                <h3 class="text-lg font-semibold mb-6 pb-2 border-b border-secondary/30 uppercase tracking-widest">
                    Enlaces Rápidos
                </h3>
                <ul class="space-y-3">
                    @php
                        $quickLinks = [
                            ['label' => 'Inicio', 'url' => '/'],
                            ['label' => 'Gestiones', 'url' => '/gestiones'],
                            ['label' => 'Transparencia', 'url' => '/transparencia'],
                            ['label' => 'Noticias', 'url' => '/avisos'],
                        ];
                    @endphp

                    @foreach($quickLinks as $link)
                        <li>
                            <a href="{{ url($link['url']) }}" class="flex items-center hover:underline group">
                                <!-- Icono ChevronRight -->
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="w-4 h-4 mr-2 group-hover:translate-x-1 transition-transform" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path d="m9 18 6-6-6-6" />
                                </svg>
                                {{ $link['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <!-- Redes sociales -->
            <div>
                <h3 class="text-lg font-semibold mb-6 pb-2 border-b border-secondary/30 uppercase tracking-widest">
                    Síguenos
                </h3>
                <div class="flex gap-4">
                    <a href="{{ $syndicate['socialMedia']['facebook'] }}" target="_blank" rel="noopener noreferrer"
                        class="btn btn-circle btn-outline text-primary-content hover:bg-primary-content hover:text-primary border-primary-content/30"
                        aria-label="Facebook">
                        <!-- Icono Facebook -->
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="none"
                            class="size-6 fill-current">
                            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />
                        </svg>
                    </a>
                    <a href="{{ $syndicate['socialMedia']['whatsapp'] }}" target="_blank" rel="noopener noreferrer"
                        class="btn btn-circle btn-outline text-primary-content hover:bg-primary-content hover:text-primary border-primary-content/30"
                        aria-label="WhatsApp">
                        <!-- Icono WhatsApp -->
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="none"
                            class="size-6 fill-current">
                            <path
                                d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
                            <path
                                d="M12 0C5.373 0 0 5.373 0 12c0 2.123.553 4.116 1.546 5.862L.261 23.739l6.02-1.579A11.94 11.94 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.954c-1.802 0-3.52-.464-5.029-1.314l-.36-.204-3.733.979.996-3.642-.224-.356A9.897 9.897 0 0 1 2.046 12c0-5.49 4.464-9.954 9.954-9.954S21.954 6.51 21.954 12s-4.464 9.954-9.954 9.954z" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- Copyright y legal -->
        <div class="text-center pt-8 border-t border-secondary/30">
            <p class="mb-3 text-sm opacity-80">
                &copy; {{ $currentYear }} {{ $syndicate['name'] }}
                <br />
                Todos los derechos reservados.
            </p>
            <div class="flex justify-center gap-4 text-sm opacity-80">
                <a href="{{ url('/privacidad') }}" class="hover:opacity-100 hover:underline transition-opacity">
                    Política de Privacidad
                </a>
                <span>|</span>
                <a href="{{ url('/terminos') }}" class="hover:opacity-100 hover:underline transition-opacity">
                    Términos y Condiciones
                </a>
            </div>
        </div>
    </div>
</footer>