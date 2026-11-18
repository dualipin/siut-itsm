<script setup lang="ts">
import { ref } from 'vue'
import { useIslandForm } from '@/composables/useIslandForm'
import {
    Send,
    CheckCircle2,
    AlertCircle,
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
    <div class="card card-border bg-base-100">
        <div class="card-body p-6 sm:p-8">
            <!-- Estado de éxito -->
            <div v-if="form.recentlySuccessful" class="text-center py-8 px-2 space-y-4">
                <div class="size-14 rounded-full bg-success/10 text-success flex items-center justify-center mx-auto">
                    <CheckCircle2 class="w-8 h-8" aria-hidden="true" />
                </div>

                <div class="max-w-md mx-auto space-y-2">
                    <h3 class="text-xl font-bold text-base-content tracking-tight">
                        Mensaje enviado
                    </h3>
                    <p class="text-sm text-base-content/70 leading-relaxed">
                        {{ form.statusMessage || 'Recibimos tu consulta. El equipo sindical te responderá en menos de 24 horas hábiles.' }}
                    </p>
                </div>

                <p v-if="lastSubmittedEmail" class="inline-flex flex-wrap items-center justify-center gap-1.5 px-3 py-1.5 rounded-box bg-base-200 text-xs text-base-content/75 max-w-full">
                    <span>Enviaremos la respuesta a</span>
                    <strong class="text-base-content break-all">{{ lastSubmittedEmail }}</strong>
                </p>

                <div class="card-actions justify-center pt-2">
                    <button
                        @click="handleSendAnother"
                        type="button"
                        class="btn btn-outline btn-sm gap-2">
                        <RotateCcw class="w-3.5 h-3.5" aria-hidden="true" />
                        <span>Enviar otro mensaje</span>
                    </button>
                </div>
            </div>

            <!-- Formulario -->
            <template v-else>
                <div class="mb-4">
                    <h3 class="card-title text-xl">Envíanos un mensaje</h3>
                    <p class="text-sm text-base-content/60 mt-1">
                        Te responderemos por correo electrónico en menos de 24 horas hábiles.
                    </p>
                </div>

                <form id="contact-form" @submit.prevent="handleContactSubmit" class="space-y-2">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Nombre completo -->
                        <fieldset class="fieldset" :disabled="form.processing">
                            <label for="name" class="fieldset-legend">Nombre completo</label>
                            <input
                                class="input w-full"
                                :class="{ 'input-error': form.errors.name }"
                                :aria-invalid="Boolean(form.errors.name)"
                                :aria-describedby="form.errors.name ? 'name-error' : undefined"
                                id="name"
                                type="text"
                                autocomplete="name"
                                v-model="form.data.name"
                                placeholder="Ej. María González"
                                required />
                            <p v-if="form.errors.name" id="name-error" role="alert" class="label text-xs text-error whitespace-normal">
                                {{ form.errors.name[0] }}
                            </p>
                        </fieldset>

                        <!-- Teléfono -->
                        <fieldset class="fieldset" :disabled="form.processing">
                            <label for="phone" class="fieldset-legend">
                                Teléfono
                                <span class="text-xs font-normal text-base-content/50">(opcional)</span>
                            </label>
                            <input
                                class="input w-full"
                                :class="{ 'input-error': form.errors.phone }"
                                :aria-invalid="Boolean(form.errors.phone)"
                                :aria-describedby="form.errors.phone ? 'phone-error' : undefined"
                                id="phone"
                                type="tel"
                                autocomplete="tel"
                                v-model="form.data.phone"
                                placeholder="Ej. 936 106 6169 (10 dígitos)" />
                            <p v-if="form.errors.phone" id="phone-error" role="alert" class="label text-xs text-error whitespace-normal">
                                {{ form.errors.phone[0] }}
                            </p>
                        </fieldset>
                    </div>

                    <!-- Correo electrónico -->
                    <fieldset class="fieldset" :disabled="form.processing">
                        <label for="email" class="fieldset-legend">Correo electrónico</label>
                        <input
                            class="input w-full"
                            :class="{ 'input-error': form.errors.email }"
                            :aria-invalid="Boolean(form.errors.email)"
                            :aria-describedby="form.errors.email ? 'email-error' : undefined"
                            id="email"
                            type="email"
                            autocomplete="email"
                            v-model="form.data.email"
                            placeholder="nombre@correo.com"
                            required />
                        <p v-if="form.errors.email" id="email-error" role="alert" class="label text-xs text-error whitespace-normal">
                            {{ form.errors.email[0] }}
                        </p>
                    </fieldset>

                    <!-- Asunto -->
                    <fieldset class="fieldset" :disabled="form.processing">
                        <label for="subject" class="fieldset-legend">
                            Asunto
                            <span class="text-xs font-normal text-base-content/50">(opcional)</span>
                        </label>
                        <input
                            class="input w-full"
                            :class="{ 'input-error': form.errors.subject }"
                            :aria-invalid="Boolean(form.errors.subject)"
                            :aria-describedby="form.errors.subject ? 'subject-error' : undefined"
                            id="subject"
                            type="text"
                            v-model="form.data.subject"
                            placeholder="Ej. Afiliación, préstamo o duda general" />
                        <p v-if="form.errors.subject" id="subject-error" role="alert" class="label text-xs text-error whitespace-normal">
                            {{ form.errors.subject[0] }}
                        </p>
                    </fieldset>

                    <!-- Mensaje -->
                    <fieldset class="fieldset" :disabled="form.processing">
                        <label for="message" class="fieldset-legend">Mensaje</label>
                        <textarea
                            class="textarea w-full min-h-28 leading-relaxed"
                            :class="{ 'textarea-error': form.errors.message }"
                            :aria-invalid="Boolean(form.errors.message)"
                            :aria-describedby="form.errors.message ? 'message-error' : undefined"
                            id="message"
                            v-model="form.data.message"
                            placeholder="Describe tu consulta o solicitud..."
                            required></textarea>
                        <p v-if="form.errors.message" id="message-error" role="alert" class="label text-xs text-error whitespace-normal">
                            {{ form.errors.message[0] }}
                        </p>
                    </fieldset>

                    <!-- Alerta de error -->
                    <div v-if="form.statusType === 'error' && form.statusMessage"
                        role="alert"
                        class="alert alert-error alert-soft mt-2">
                        <AlertCircle class="w-5 h-5 shrink-0" aria-hidden="true" />
                        <span>{{ form.statusMessage }}</span>
                    </div>

                    <!-- Botón Submit -->
                    <div class="card-actions justify-end pt-3">
                        <button
                            class="btn btn-primary w-full sm:w-auto"
                            type="submit"
                            :disabled="form.processing">
                            <template v-if="form.processing">
                                <span class="loading loading-spinner loading-xs"></span>
                                <span>Enviando mensaje...</span>
                            </template>
                            <template v-else>
                                <span>Enviar mensaje</span>
                                <Send class="w-4 h-4" aria-hidden="true" />
                            </template>
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>
</template>