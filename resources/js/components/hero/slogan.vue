<script setup lang="ts">
import { ref, onMounted } from 'vue'
import gsap from 'gsap'

const text = 'Porque la razón está en la educación; luchemos unidos por ella'
// Dividimos el texto en un array para iterarlo en el template
const characters = text.split('')

// Referencias del DOM
const charsRef = ref<HTMLSpanElement[]>([])
const svgPathRef = ref<SVGPathElement | null>(null)

onMounted(() => {
  // 1. Animación del texto escalonado
  gsap.from(charsRef.value, {
    opacity: 0,
    y: 10,
    stagger: 0.03,
    duration: 0.4,
    ease: 'power2.out',
  })

  // 2. Animación de trazado del SVG
  if (svgPathRef.value) {
    const pathLength = svgPathRef.value.getTotalLength()

    // GSAP .set() es mejor que modificar styles directamente
    gsap.set(svgPathRef.value, {
      strokeDasharray: pathLength,
      strokeDashoffset: pathLength,
    })

    gsap.to(svgPathRef.value, {
      strokeDashoffset: 0,
      duration: 1.2,
      delay: 0.8,
      ease: 'power2.inOut',
    })
  }
})
</script>

<template>
  <div class="relative mb-8 lg:mb-12">
    <!-- Contenedor del texto -->
    <p class="text-xl mb-0 text-base-content/80">
      <span v-for="(char, index) in characters" :key="index" ref="charsRef" class="inline-block text-[16px]">
        {{ char === ' ' ? '&nbsp;' : char }}
      </span>
    </p>

    <!-- SVG -->
    <svg width="100%" height="32" viewBox="0 0 400 32" fill="none" xmlns="http://www.w3.org/2000/svg"
      class="absolute left-0 -bottom-3 pointer-events-none">
      <defs>
        <linearGradient id="hero-underline-gradient" x1="0" y1="0" x2="400" y2="0" gradientUnits="userSpaceOnUse">
          <!-- Usamos la clase de texto de Tailwind y heredamos con currentColor -->
          <!-- Puedes cambiar text-[#611232] por text-primary, text-red-800, etc. -->
          <stop class="text-primary" offset="0%" stop-color="currentColor" />
          <stop class="text-secondary" offset="100%" stop-color="currentColor" />
        </linearGradient>
        <filter id="hero-underline-shadow" x="-20" y="-20" width="600" height="72" filterUnits="userSpaceOnUse">
          <feDropShadow class="text-primary" dx="0" dy="2" stdDeviation="2" flood-color="currentColor"
            flood-opacity="0.4" />
        </filter>
      </defs>
      <!-- El path se queda igual, ya que apunta a los IDs del defs -->
      <path ref="svgPathRef" d="M20 24 C100 36 300 12 380 24" stroke="url(#hero-underline-gradient)" stroke-width="5"
        fill="none" filter="url(#hero-underline-shadow)" stroke-linecap="round" />
    </svg>
  </div>
</template>