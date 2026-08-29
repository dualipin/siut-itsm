<script setup lang="ts">
import { ref } from 'vue'
import { useIslandForm } from '@/composables/useIslandForm'
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
    RotateCcw,
} from '@lucide/vue'

const lastSubmittedEmail = ref('')

const form = useIslandForm({
    name: '',
    phone: '',
    email: '',
    subject: '',
    message: '',
})

const handleContactSubmit = async () => {
    lastSubmittedEmail.value = form.data.email

    await form.post('/contact', {
        onSuccess: () => {
            form.reset()
        },
    })
}

const handleSendAnother = () => {
    form.clearFeedback()
    form.reset()
    lastSubmittedEmail.value = ''
}
</script>

<template>
    <div class="relative group">
        <!-- Resplandor decorativo de fondo -->
        <div
            class="absolute -inset-1 bg-linear-to-r from-primary/20 to-secondary/20 rounded-3xl blur-xl opacity-50 group-hover:opacity-75 transition duration-500 pointer-events-none">
        </div>

        <div
            class="relative p-6 sm:p-8 md:p-10 rounded-3xl border border-base-300/80 bg-base-100/90 backdrop-blur-md shadow-xl transition-all duration-300">

            <!-- ESTADO 1: PANTALLA DE ÉXITO (BUEN UX) -->
            <div v-if="form.recentlySuccessful" class="text-center py-10 px-4 animate-fade-in-up space-y-6">
                <!-- Icono de éxito con pulso -->
                <div class="relative inline-flex items-center justify-center">
                    <div class="absolute -inset-2 bg-success/20 rounded-full blur-md animate-pulse"></div>
                    <div class="relative w-20 h-20 rounded-full bg-success/15 text-success flex items-center justify-center border-2 border-success/30 shadow-inner">
                        <CheckCircle2 class="w-10 h-10 stroke-[2.5]" />
                    </div>
                </div>

                <div class="max-w-md mx-auto space-y-2">
                    <span class="inline-flex items-center gap-1 text-xs font-bold uppercase tracking-widest text-success bg-success/10 px-3 py-1 rounded-full">
                        Envío Confirmado
                    </span>
                    <h3 class="text-2xl sm:text-3xl font-extrabold text-base-content tracking-tight">
                        ¡Mensaje Enviado con Éxito!
                    </h3>
                    <p class="text-sm text-base-content/75 leading-relaxed">
                        {{ form.statusMessage || 'Tu mensaje ha sido registrado correctamente. El equipo sindical se pondrá en contacto contigo a la brevedad.' }}
                    </p>
                </div>

                <!-- Resumen de contacto para tranquilidad del usuario -->
                <div v-if="lastSubmittedEmail" class="max-w-xs mx-auto p-3.5 rounded-2xl bg-base-200/60 border border-base-300/80 text-xs flex items-center justify-center gap-2 text-base-content/80">
                    <Mail class="w-4 h-4 text-primary shrink-0" />
                    <span>Respuesta a: <strong class="text-base-content">{{ lastSubmittedEmail }}</strong></span>
                </div>

                <!-- Botón para enviar otro mensaje -->
                <div class="pt-2">
                    <button
                        @click="handleSendAnother"
                        type="button"
                        class="btn btn-outline btn-primary rounded-full px-6 text-xs font-bold gap-2 hover:scale-105 transition-transform">
                        <RotateCcw class="w-3.5 h-3.5" />
                        <span>Enviar otro mensaje</span>
                    </button>
                </div>
            </div>

            <!-- ESTADO 2: FORMULARIO NORMAL -->
            <template v-else>
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
                                    :class="{ 'input-error': form.errors.name }"
                                    id="name"
                                    type="text"
                                    v-model="form.data.name"
                                    placeholder="Ej. María González"
                                    required />
                                <User
                                    class="w-4 h-4 text-base-content/40 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                            </div>
                            <p v-if="form.errors.name" class="text-xs text-error mt-1">{{ form.errors.name[0] }}</p>
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
                                    :class="{ 'input-error': form.errors.phone }"
                                    id="phone"
                                    type="tel"
                                    v-model="form.data.phone"
                                    placeholder="10 dígitos (ej. 9361066169)" />
                                <Phone
                                    class="w-4 h-4 text-base-content/40 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                            </div>
                            <p v-if="form.errors.phone" class="text-xs text-error mt-1">{{ form.errors.phone[0] }}</p>
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
                                :class="{ 'input-error': form.errors.email }"
                                id="email"
                                type="email"
                                v-model="form.data.email"
                                placeholder="ejemplo@correo.com"
                                required />
                            <Mail
                                class="w-4 h-4 text-base-content/40 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                        </div>
                        <p v-if="form.errors.email" class="text-xs text-error mt-1">{{ form.errors.email[0] }}</p>
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
                                :class="{ 'input-error': form.errors.subject }"
                                id="subject"
                                type="text"
                                v-model="form.data.subject"
                                placeholder="Motivo de tu consulta (ej. Trámite, Duda, Afiliación)" />
                            <Tag
                                class="w-4 h-4 text-base-content/40 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                        </div>
                        <p v-if="form.errors.subject" class="text-xs text-error mt-1">{{ form.errors.subject[0] }}</p>
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
                                :class="{ 'textarea-error': form.errors.message }"
                                id="message"
                                v-model="form.data.message"
                                placeholder="Escribe detalladamente tu consulta..."
                                required></textarea>
                            <MessageSquare
                                class="w-4 h-4 text-base-content/40 absolute left-3.5 top-4 pointer-events-none" />
                        </div>
                        <p v-if="form.errors.message" class="text-xs text-error mt-1">{{ form.errors.message[0] }}</p>
                    </div>

                    <!-- Alerta si ocurre error de validación o red -->
                    <div v-if="form.statusType === 'error' && form.statusMessage"
                        class="p-4 rounded-2xl border flex items-start gap-3 bg-error/10 border-error/30 text-error-content animate-fade-in-up">
                        <AlertCircle class="w-5 h-5 text-error shrink-0 mt-0.5" />
                        <span class="text-sm font-medium">{{ form.statusMessage }}</span>
                    </div>

                    <!-- Botón Submit -->
                    <div class="pt-2">
                        <button
                            class="btn btn-primary w-full sm:w-auto rounded-full px-8 py-3 font-semibold shadow-lg shadow-primary/20 hover:shadow-primary/40 hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200 flex items-center justify-center gap-2"
                            type="submit"
                            :disabled="form.processing">
                            <template v-if="form.processing">
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
            </template>
        </div>
    </div>
</template>