<script setup lang="ts">
import { ref } from 'vue'
import { ArrowLeft, Mail, Lock, Eye, EyeOff, ShieldCheck, CheckCircle2, UserCheck, Sparkles } from '@lucide/vue'

export interface RegistrationFormData {
    name: string
    surnames: string
    email: string
    phone: string
    birth_date: string
    curp: string
    nss: string
    category: string
    salary: string | number
    address: string
    password: string
    password_confirmation: string
    ine_token: string
    nomina_token: string
    photo_token: string
    [key: string]: any
}

const props = defineProps<{
    form: {
        data: RegistrationFormData
        errors: Record<string, string[] | undefined>
        processing: boolean
        statusMessage?: string
        statusType?: string
    }
    privacyUrl: string
    loginUrl: string
}>()

const emit = defineEmits<{
    (e: 'prev'): void
    (e: 'submit'): void
}>()

const showPassword = ref(false)
const showPasswordConfirmation = ref(false)
</script>

<template>
    <section aria-labelledby="step-3-title" class="space-y-6 scroll-mt-44">
        <div>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-primary/10 text-primary mb-2">
                <Sparkles class="size-3.5" />
                Paso 3 de 3
            </div>
            <h2 id="step-3-title" class="text-2xl sm:text-3xl font-display font-black text-base-content tracking-tight">
                Seguridad de la Cuenta y Envío
            </h2>
            <p class="text-sm text-base-content/70 mt-1 max-w-xl">
                Crea tus credenciales de acceso para entrar posteriormente a tu portal sindical. Revisa el resumen antes de enviar tu solicitud.
            </p>
        </div>

        <!-- Formulario de Cuenta -->
        <div class="space-y-5">
            <!-- Correo Electrónico -->
            <div class="fieldset">
                <label for="reg-email" class="fieldset-label font-bold text-sm text-base-content">
                    Correo Electrónico *
                </label>
                <div class="relative">
                    <input
                        id="reg-email"
                        v-model="form.data.email"
                        type="email"
                        autocomplete="email"
                        placeholder="tu-correo@ejemplo.com"
                        class="input input-md w-full pl-10"
                        :class="{ 'input-error': form.errors.email }"
                        required
                    />
                    <Mail class="size-4.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-base-content/40 pointer-events-none" />
                </div>
                <span class="fieldset-label text-xs text-base-content/60">
                    Se utilizará para notificaciones oficiales y recuperación de cuenta.
                </span>
                <span v-if="form.errors.email" class="fieldset-label text-error text-xs font-semibold" role="alert">
                    {{ form.errors.email[0] }}
                </span>
            </div>

            <!-- Contraseña y Confirmación -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <!-- Contraseña -->
                <div class="fieldset">
                    <label for="reg-password" class="fieldset-label font-bold text-sm text-base-content">
                        Contraseña *
                    </label>
                    <div class="relative">
                        <input
                            id="reg-password"
                            v-model="form.data.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            placeholder="Mínimo 8 caracteres"
                            class="input input-md w-full pl-10 pr-10"
                            :class="{ 'input-error': form.errors.password }"
                            required
                        />
                        <Lock class="size-4.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-base-content/40 pointer-events-none" />
                        <button
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-base-content/40 hover:text-base-content p-1 cursor-pointer"
                            :aria-label="showPassword ? 'Ocultar contraseña' : 'Ver contraseña'"
                            @click="showPassword = !showPassword"
                        >
                            <EyeOff v-if="showPassword" class="size-4" />
                            <Eye v-else class="size-4" />
                        </button>
                    </div>
                    <span v-if="form.errors.password" class="fieldset-label text-error text-xs font-semibold" role="alert">
                        {{ form.errors.password[0] }}
                    </span>
                </div>

                <!-- Confirmar Contraseña -->
                <div class="fieldset">
                    <label for="reg-password-confirmation" class="fieldset-label font-bold text-sm text-base-content">
                        Confirmar Contraseña *
                    </label>
                    <div class="relative">
                        <input
                            id="reg-password-confirmation"
                            v-model="form.data.password_confirmation"
                            :type="showPasswordConfirmation ? 'text' : 'password'"
                            autocomplete="new-password"
                            placeholder="Repite tu contraseña"
                            class="input input-md w-full pl-10 pr-10"
                            :class="{ 'input-error': form.errors.password_confirmation }"
                            required
                        />
                        <Lock class="size-4.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-base-content/40 pointer-events-none" />
                        <button
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-base-content/40 hover:text-base-content p-1 cursor-pointer"
                            :aria-label="showPasswordConfirmation ? 'Ocultar confirmación' : 'Ver confirmación'"
                            @click="showPasswordConfirmation = !showPasswordConfirmation"
                        >
                            <EyeOff v-if="showPasswordConfirmation" class="size-4" />
                            <Eye v-else class="size-4" />
                        </button>
                    </div>
                    <span v-if="form.errors.password_confirmation" class="fieldset-label text-error text-xs font-semibold" role="alert">
                        {{ form.errors.password_confirmation[0] }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Resumen de Validación -->
        <div class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-xs space-y-3">
            <h3 class="font-bold text-sm text-base-content flex items-center gap-2">
                <UserCheck class="size-4.5 text-primary" />
                Resumen de tu Expediente
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="p-2.5 rounded-xl bg-base-200/50">
                    <span class="text-base-content/60 block font-medium">Nombre completo:</span>
                    <strong class="text-base-content text-sm">{{ form.data.name }} {{ form.data.surnames }}</strong>
                </div>

                <div class="p-2.5 rounded-xl bg-base-200/50">
                    <span class="text-base-content/60 block font-medium">Usuario / CURP:</span>
                    <strong class="font-mono text-base-content text-sm uppercase">{{ form.data.curp }}</strong>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 pt-1 text-xs">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-success/10 text-success font-medium">
                    <CheckCircle2 class="size-3.5" /> INE cargada
                </span>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-success/10 text-success font-medium">
                    <CheckCircle2 class="size-3.5" /> Nómina cargada
                </span>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-success/10 text-success font-medium">
                    <CheckCircle2 class="size-3.5" /> Fotografía lista
                </span>
            </div>
        </div>

        <!-- Aviso de Privacidad y Consentimiento (Informativo, sin checkbox) -->
        <div class="alert alert-soft bg-base-100 border border-base-300 rounded-2xl p-4 flex items-start gap-3">
            <ShieldCheck class="size-5 text-primary shrink-0 mt-0.5" />
            <div class="text-xs text-base-content/70 leading-relaxed">
                Al enviar este formulario solicitas formalmente tu afiliación sindical y consientes el tratamiento de tus datos personales y documentos conforme a nuestro
                <a :href="privacyUrl" target="_blank" rel="noopener noreferrer" class="link link-primary font-semibold">
                    Aviso de Privacidad Oficial
                </a>.
            </div>
        </div>

        <!-- Feedback general de error del formulario si hay -->
        <div v-if="form.statusMessage && form.statusType === 'error'" class="alert alert-error text-xs rounded-2xl" role="alert">
            {{ form.statusMessage }}
        </div>

        <!-- Botones de Navegación -->
        <div class="pt-4 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3 sm:gap-4">
            <button
                type="button"
                class="btn btn-ghost btn-md gap-2 font-semibold"
                :disabled="form.processing"
                @click="emit('prev')"
            >
                <ArrowLeft class="size-4.5" />
                Atrás
            </button>

            <button
                type="button"
                class="btn btn-primary btn-md sm:btn-lg gap-2 font-semibold shadow-md cursor-pointer"
                :disabled="form.processing"
                @click="emit('submit')"
            >
                <span v-if="form.processing" class="loading loading-spinner loading-sm"></span>
                <span v-if="form.processing">Creando tu cuenta...</span>
                <span v-else>Crear mi cuenta de agremiado</span>
            </button>
        </div>

        <!-- Enlace para usuarios ya registrados -->
        <div class="text-center pt-2">
            <a :href="loginUrl" class="link link-hover text-xs text-base-content/70 hover:text-primary">
                ¿Ya tienes una cuenta registrada en el sindicato? <strong class="text-primary font-semibold">Inicia sesión aquí</strong>
            </a>
        </div>
    </section>
</template>
