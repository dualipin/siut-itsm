<script setup lang="ts">
import ContactForm from '@/components/contact-form.vue'
import { SyndicateConfig } from '@/types/syndicate'
import { Phone, Mail, MapPin, ArrowUpRight } from '@lucide/vue'

defineProps<{
  syndicate: SyndicateConfig
}>()
</script>

<template>
  <section class="py-16 md:py-24 bg-base-200/40" id="contact">
    <div class="container mx-auto px-4 sm:px-6 max-w-6xl">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-start">
        <!-- Columna izquierda: Información de contacto -->
        <div class="lg:col-span-5">
          <h2 class="text-3xl font-bold tracking-tight text-base-content">
            Estamos para servirte y escucharte
          </h2>
          <p class="mt-3 text-base text-base-content/70 leading-relaxed">
            Escríbenos a través del formulario o comunícate directamente por nuestros canales oficiales.
          </p>

          <!-- Lista de canales con DaisyUI card + list -->
          <div class="card card-border bg-base-100 mt-8">
            <ul class="list">
              <!-- Teléfono / WhatsApp -->
              <li class="list-row items-center">
                <div class="size-10 rounded-box bg-primary/10 text-primary flex items-center justify-center shrink-0">
                  <Phone class="w-5 h-5" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                  <div class="text-xs font-medium text-base-content/60">
                    Teléfono y WhatsApp
                  </div>
                  <a
                    :href="syndicate.socialMedia.whatsapp"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="link link-hover font-semibold text-base-content inline-flex items-center gap-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 rounded"
                  >
                    <span>{{ syndicate.phone }}</span>
                    <span class="sr-only"> (abre WhatsApp en una pestaña nueva)</span>
                    <ArrowUpRight class="w-3.5 h-3.5 text-base-content/50 shrink-0" aria-hidden="true" />
                  </a>
                </div>
              </li>

              <!-- Correo electrónico -->
              <li class="list-row items-center">
                <div class="size-10 rounded-box bg-primary/10 text-primary flex items-center justify-center shrink-0">
                  <Mail class="w-5 h-5" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                  <div class="text-xs font-medium text-base-content/60">
                    Correo electrónico
                  </div>
                  <a
                    :href="`mailto:${syndicate.email}`"
                    class="link link-hover font-semibold text-base-content lowercase break-all inline-flex items-center gap-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 rounded"
                  >
                    <span>{{ syndicate.email }}</span>
                    <ArrowUpRight class="w-3.5 h-3.5 text-base-content/50 shrink-0" aria-hidden="true" />
                  </a>
                </div>
              </li>

              <!-- Oficinas sindicales -->
              <li class="list-row items-start">
                <div class="size-10 rounded-box bg-primary/10 text-primary flex items-center justify-center shrink-0">
                  <MapPin class="w-5 h-5" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                  <div class="text-xs font-medium text-base-content/60">
                    Oficinas sindicales
                  </div>
                  <address class="not-italic text-sm font-medium text-base-content/90 leading-snug mt-0.5">
                    {{ syndicate.address }}
                  </address>
                  <p class="text-xs text-base-content/60 mt-1.5">
                    Atención presencial en horario hábil para trámites y entrega de documentos.
                  </p>
                </div>
              </li>
            </ul>
          </div>
        </div>

        <!-- Columna derecha: Formulario -->
        <div class="lg:col-span-7">
          <ContactForm />
        </div>
      </div>
    </div>
  </section>
</template>