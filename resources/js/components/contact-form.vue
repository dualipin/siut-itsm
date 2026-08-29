<script setup lang="ts">
import { ref } from 'vue'
import {
    User,
    Phone,
    Mail,
    Send,
    Tag,
    MessageSquare,
    CheckCircle2,
    AlertCircle,
    Sparkles,
} from '@lucide/vue'

interface ContactState {
    enviando: boolean
    enviado: boolean
    tipo: 'exito' | 'error' | ''
    mensaje: string
}

const contactState = ref<ContactState>({
    enviando: false,
    enviado: false,
    tipo: '',
    mensaje: '',
})

const handleContactSubmit = async (e: Event) => {
    const form = e.target as HTMLFormElement
    const formData = new FormData(form)

    contactState.value.enviando = true
    contactState.value.enviado = false

    try {
        const response = await fetch('/contacto.php', {
            method: 'POST',
            body: formData,
        })
        const result = await response.json()

        if (response.ok) {
            contactState.value = {
                enviando: false,
                enviado: true,
                tipo: result.tipo ? 'exito' : 'error',
                mensaje:
                    result.message ||
                    'Mensaje enviado correctamente. Nos pondremos en contacto contigo pronto.',
            }
            form.reset()
        } else {
            contactState.value = {
                enviando: false,
                enviado: true,
                tipo: 'error',
                mensaje:
                    result.message || 'Ocurrió un inconveniente al enviar el mensaje.',
            }
        }
    } catch {
        contactState.value = {
            enviando: false,
            enviado: true,
            tipo: 'error',
            mensaje:
                'Ocurrió un error de conexión. Por favor intenta de nuevo más tarde.',
        }
    }
}
</script>

<template>
    <div class="relative group">
        <!-- Resplandor decorativo de fondo -->
        <div
            class="absolute -inset-1 bg-linear-to-r from-primary/20 to-secondary/20 rounded-3xl blur-xl opacity-50 group-hover:opacity-75 transition duration-500 pointer-events-none">
        </div>

        <div
            class="relative p-6 sm:p-8 md:p-10 rounded-3xl border border-base-300/80 bg-base-100/90 backdrop-blur-md shadow-xl">
            <div class="flex items-center gap-3 mb-6 pb-4 border-b border-base-200">
                <div class="p-2.5 rounded-2xl bg-primary/10 text-primary">
                    <Sparkles class="w-5 h-5" />
                </div>
                <div>
                    <h3 class="text-xl font-bold tracking-tight text-base-content mb-0">
                        Envíanos un mensaje
                    </h3>
                    <p class="text-xs text-base-content/60 mb-0">
                        Respuesta habitual en menos de 24 horas hábiles
                    </p>
                </div>
            </div>

            <form id="contact-form" @submit.prevent="handleContactSubmit" class="space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Nombre completo -->
                    <div class="space-y-1.5">
                        <label
                            class="text-xs font-semibold uppercase tracking-wider text-base-content/70 flex items-center gap-1.5"
                            for="name">
                            <User class="w-3.5 h-3.5 text-primary" />
                            Nombre completo
                        </label>
                        <div class="relative">
                            <input
                                class="input input-bordered w-full rounded-2xl pl-10 pr-4 py-3 bg-base-200/40 focus:bg-base-100 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all text-sm"
                                id="name" type="text" name="nombre" placeholder="Ej. María González" required />
                            <User
                                class="w-4 h-4 text-base-content/40 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                        </div>
                    </div>

                    <!-- Teléfono -->
                    <div class="space-y-1.5">
                        <label
                            class="text-xs font-semibold uppercase tracking-wider text-base-content/70 flex items-center gap-1.5"
                            for="phone">
                            <Phone class="w-3.5 h-3.5 text-primary" />
                            Teléfono
                        </label>
                        <div class="relative">
                            <input
                                class="input input-bordered w-full rounded-2xl pl-10 pr-4 py-3 bg-base-200/40 focus:bg-base-100 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all text-sm"
                                id="phone" type="tel" name="telefono" pattern="^\d{10}$"
                                title="Ingrese sólo 10 dígitos (ej: 9361066169)"
                                placeholder="10 dígitos (ej. 9361066169)" required />
                            <Phone
                                class="w-4 h-4 text-base-content/40 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                        </div>
                    </div>
                </div>

                <!-- Correo electrónico -->
                <div class="space-y-1.5">
                    <label
                        class="text-xs font-semibold uppercase tracking-wider text-base-content/70 flex items-center gap-1.5"
                        for="email">
                        <Mail class="w-3.5 h-3.5 text-primary" />
                        Correo electrónico
                    </label>
                    <div class="relative">
                        <input
                            class="input input-bordered w-full rounded-2xl pl-10 pr-4 py-3 bg-base-200/40 focus:bg-base-100 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all text-sm"
                            id="email" type="email" name="correo" placeholder="ejemplo@correo.com" required />
                        <Mail
                            class="w-4 h-4 text-base-content/40 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                    </div>
                </div>

                <!-- Asunto -->
                <div class="space-y-1.5">
                    <label
                        class="text-xs font-semibold uppercase tracking-wider text-base-content/70 flex items-center gap-1.5"
                        for="subject">
                        <Tag class="w-3.5 h-3.5 text-primary" />
                        Asunto
                    </label>
                    <div class="relative">
                        <input
                            class="input input-bordered w-full rounded-2xl pl-10 pr-4 py-3 bg-base-200/40 focus:bg-base-100 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all text-sm"
                            id="subject" type="text" name="asunto"
                            placeholder="Motivo de tu consulta (ej. Trámite, Duda, Afiliación)" />
                        <Tag
                            class="w-4 h-4 text-base-content/40 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                    </div>
                </div>

                <!-- Mensaje -->
                <div class="space-y-1.5">
                    <label
                        class="text-xs font-semibold uppercase tracking-wider text-base-content/70 flex items-center gap-1.5"
                        for="message">
                        <MessageSquare class="w-3.5 h-3.5 text-primary" />
                        Mensaje
                    </label>
                    <div class="relative">
                        <textarea
                            class="textarea textarea-bordered w-full rounded-2xl pl-10 pr-4 py-3 bg-base-200/40 focus:bg-base-100 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all text-sm min-h-32 leading-relaxed resize-y"
                            id="message" name="mensaje" placeholder="Escribe detalladamente tu consulta..."
                            required></textarea>
                        <MessageSquare
                            class="w-4 h-4 text-base-content/40 absolute left-3.5 top-4 pointer-events-none" />
                    </div>
                </div>

                <!-- Botón Submit -->
                <div class="pt-2">
                    <button
                        class="btn btn-primary w-full sm:w-auto rounded-full px-8 py-3 font-semibold shadow-lg shadow-primary/20 hover:shadow-primary/40 hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200 flex items-center justify-center gap-2"
                        type="submit" :disabled="contactState.enviando">
                        <template v-if="contactState.enviando">
                            <span class="loading loading-spinner loading-xs"></span>
                            <span>Enviando mensaje...</span>
                        </template>
                        <template v-else>
                            <span>Enviar mensaje</span>
                            <Send class="w-4 h-4" />
                        </template>
                    </button>
                </div>
            </form>

            <!-- Feedback visual de estado -->
            <div v-if="contactState.enviado"
                class="mt-6 p-4 rounded-2xl border flex items-start gap-3 animate-fade-in-up" :class="contactState.tipo === 'exito'
                        ? 'bg-success/10 border-success/30 text-success-content'
                        : 'bg-error/10 border-error/30 text-error-content'
                    ">
                <template v-if="contactState.tipo === 'exito'">
                    <CheckCircle2 class="w-5 h-5 text-success shrink-0 mt-0.5" />
                </template>
                <template v-else>
                    <AlertCircle class="w-5 h-5 text-error shrink-0 mt-0.5" />
                </template>
                <span class="text-sm font-medium">{{ contactState.mensaje }}</span>
            </div>
        </div>
    </div>
</template>