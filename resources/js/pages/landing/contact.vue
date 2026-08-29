<script setup lang="ts">
import ContactForm from '@/components/contact-form.vue'
import { Sparkles, Phone, Mail, MapPin } from '@lucide/vue'

interface SyndicateConfig {
  name: string
  acronym: string
  email: string
  phone: string
  address: string
  socialMedia: {
    facebook: string
    whatsapp: string
  }
  logo: {
    src: string
    alt: string
  }
  slogan: string
}

defineProps<{
  syndicate: SyndicateConfig
}>()
</script>

<template>
  <section class="py-20 bg-base-100 relative overflow-hidden" id="contact">
    <!-- Elemento decorativo de fondo -->
    <div class="absolute top-0 right-0 -mt-20 -mr-20 w-96 h-96 rounded-full bg-primary/5 blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-0 left-0 -mb-20 -ml-20 w-96 h-96 rounded-full bg-secondary/5 blur-3xl pointer-events-none"></div>

    <div class="container mx-auto px-4 max-w-7xl relative z-10">
      <!-- Cabecera de la sección -->
      <div class="mb-14 max-w-3xl mx-auto text-center">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold tracking-wider uppercase bg-primary/10 text-primary mb-3">
          <Sparkles class="w-3.5 h-3.5" />
          Atención y Contacto
        </span>
        <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight mb-4 text-base-content">
          Estamos para servirte y escucharte
        </h2>
        <p class="text-base-content/75 text-base md:text-lg leading-relaxed mb-0">
          Si tienes alguna pregunta, inquietud o necesitas información
          adicional, no dudes en contactarnos a través de nuestros canales
          oficiales. También te esperamos en nuestras oficinas sindicales.
        </p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
        <!-- Canales de contacto directos (Columna de la izquierda) -->
        <div class="lg:col-span-5 space-y-6">
          <div class="bg-base-200/50 rounded-3xl p-6 sm:p-8 border border-base-300/60 shadow-sm space-y-6">
            <h3 class="text-lg font-bold tracking-tight text-base-content border-b border-base-300/80 pb-3 mb-4">
              Información de contacto
            </h3>

            <!-- Teléfono / WhatsApp -->
            <div class="flex items-start gap-4 p-4 rounded-2xl bg-base-100 border border-base-200/80 hover:border-success/40 transition-all group">
              <div class="p-3 rounded-2xl bg-success/10 text-success group-hover:scale-110 transition-transform">
                <Phone class="w-6 h-6" />
              </div>
              <div>
                <span class="block text-xs uppercase tracking-wider font-semibold text-base-content/60 mb-0.5">
                  Teléfono & WhatsApp
                </span>
                <a
                  :href="syndicate.socialMedia.whatsapp"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="font-bold text-base md:text-lg hover:underline text-success inline-flex items-center gap-1"
                >
                  {{ syndicate.phone }}
                </a>
              </div>
            </div>

            <!-- Correo electrónico -->
            <div class="flex items-start gap-4 p-4 rounded-2xl bg-base-100 border border-base-200/80 hover:border-primary/40 transition-all group">
              <div class="p-3 rounded-2xl bg-primary/10 text-primary group-hover:scale-110 transition-transform">
                <Mail class="w-6 h-6" />
              </div>
              <div>
                <span class="block text-xs uppercase tracking-wider font-semibold text-base-content/60 mb-0.5">
                  Correo electrónico
                </span>
                <a
                  :href="`mailto:${syndicate.email}`"
                  class="font-bold text-base md:text-lg hover:underline text-base-content lowercase break-all"
                >
                  {{ syndicate.email }}
                </a>
              </div>
            </div>

            <!-- Dirección -->
            <div class="flex items-start gap-4 p-4 rounded-2xl bg-base-100 border border-base-200/80 hover:border-secondary/40 transition-all group">
              <div class="p-3 rounded-2xl bg-secondary/10 text-secondary group-hover:scale-110 transition-transform">
                <MapPin class="w-6 h-6" />
              </div>
              <div>
                <span class="block text-xs uppercase tracking-wider font-semibold text-base-content/60 mb-0.5">
                  Oficinas Sindicales
                </span>
                <address class="font-semibold not-italic text-sm text-base-content/90 leading-snug">
                  {{ syndicate.address }}
                </address>
              </div>
            </div>
          </div>

          <!-- Banner de atención presencial -->
          <div class="p-6 rounded-3xl bg-gradient-to-r from-primary/10 via-base-200 to-secondary/10 border border-primary/20 text-center sm:text-left">
            <span class="text-xs uppercase font-bold tracking-widest text-primary block mb-1">
              Atención Presencial
            </span>
            <p class="text-xs sm:text-sm text-base-content/80 mb-0">
              Puedes acudir a las oficinas sindicales en horario hábil para
              atención personalizada y recepción de documentos.
            </p>
          </div>
        </div>

        <!-- Formulario (Columna de la derecha) -->
        <div class="lg:col-span-7">
          <ContactForm />
        </div>
      </div>
    </div>
  </section>
</template>