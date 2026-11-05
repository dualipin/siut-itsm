<script setup lang="ts">
import { computed } from 'vue'
import { ArrowLeft, ArrowRight, User, Calendar, Phone, MapPin, Briefcase, DollarSign, Shield, Sparkles } from '@lucide/vue'

export type FieldSource = 'ine' | 'nomina' | 'manual'

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
    }
    fieldSources: Record<string, FieldSource>
}>()

const emit = defineEmits<{
    (e: 'prev'): void
    (e: 'next'): void
}>()

// Format CURP to uppercase automatically on input
function handleCurpInput(e: Event) {
    const target = e.target as HTMLInputElement
    props.form.data.curp = target.value.toUpperCase().trim()
}

const canProceed = computed(() => {
    return (
        Boolean(props.form.data.name?.trim()) &&
        Boolean(props.form.data.surnames?.trim()) &&
        Boolean(props.form.data.curp?.trim()) &&
        Boolean(props.form.data.birth_date)
    )
})
</script>

<template>
    <section aria-labelledby="step-2-title" class="space-y-6 scroll-mt-44">
        <div>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-primary/10 text-primary mb-2">
                <Sparkles class="size-3.5" />
                Paso 2 de 3
            </div>
            <h2 id="step-2-title" class="text-2xl sm:text-3xl font-display font-black text-base-content tracking-tight">
                Tus Datos Personales y Laborales
            </h2>
            <p class="text-sm text-base-content/70 mt-1 max-w-xl">
                Revisa los datos leídos por la IA y completa los campos restantes. Puedes editar cualquier información si requiere corrección.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <!-- Nombre(s) -->
            <div class="fieldset">
                <label for="reg-name" class="fieldset-label font-bold text-sm text-base-content flex items-center justify-between">
                    <span>Nombre(s) *</span>
                    <span v-if="fieldSources.name === 'ine'" class="badge badge-soft badge-primary badge-xs gap-1">
                        Leído de tu INE
                    </span>
                </label>
                <div class="relative">
                    <input
                        id="reg-name"
                        v-model="form.data.name"
                        type="text"
                        autocomplete="given-name"
                        placeholder="Ej. Juan Carlos"
                        class="input input-md w-full pl-10"
                        :class="{ 'input-error': form.errors.name }"
                        required
                    />
                    <User class="size-4.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-base-content/40 pointer-events-none" />
                </div>
                <span v-if="form.errors.name" class="fieldset-label text-error text-xs font-semibold" role="alert">
                    {{ form.errors.name[0] }}
                </span>
            </div>

            <!-- Apellidos -->
            <div class="fieldset">
                <label for="reg-surnames" class="fieldset-label font-bold text-sm text-base-content flex items-center justify-between">
                    <span>Apellidos *</span>
                    <span v-if="fieldSources.surnames === 'ine'" class="badge badge-soft badge-primary badge-xs gap-1">
                        Leído de tu INE
                    </span>
                </label>
                <div class="relative">
                    <input
                        id="reg-surnames"
                        v-model="form.data.surnames"
                        type="text"
                        autocomplete="family-name"
                        placeholder="Ej. Hernández Pérez"
                        class="input input-md w-full pl-10"
                        :class="{ 'input-error': form.errors.surnames }"
                        required
                    />
                    <User class="size-4.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-base-content/40 pointer-events-none" />
                </div>
                <span v-if="form.errors.surnames" class="fieldset-label text-error text-xs font-semibold" role="alert">
                    {{ form.errors.surnames[0] }}
                </span>
            </div>

            <!-- CURP -->
            <div class="fieldset">
                <label for="reg-curp" class="fieldset-label font-bold text-sm text-base-content flex items-center justify-between">
                    <span>CURP *</span>
                    <span v-if="fieldSources.curp === 'ine'" class="badge badge-soft badge-primary badge-xs gap-1">
                        Leído de tu INE
                    </span>
                </label>
                <div class="relative">
                    <input
                        id="reg-curp"
                        :value="form.data.curp"
                        type="text"
                        maxlength="18"
                        placeholder="18 caracteres alfanuméricos"
                        class="input input-md w-full pl-10 font-mono uppercase"
                        :class="{ 'input-error': form.errors.curp }"
                        required
                        @input="handleCurpInput"
                    />
                    <Shield class="size-4.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-base-content/40 pointer-events-none" />
                </div>
                <span class="fieldset-label text-xs text-base-content/60">
                    Será tu identificador para iniciar sesión en el portal.
                </span>
                <span v-if="form.errors.curp" class="fieldset-label text-error text-xs font-semibold" role="alert">
                    {{ form.errors.curp[0] }}
                </span>
            </div>

            <!-- Fecha de Nacimiento -->
            <div class="fieldset">
                <label for="reg-birth-date" class="fieldset-label font-bold text-sm text-base-content flex items-center justify-between">
                    <span>Fecha de Nacimiento *</span>
                    <span v-if="fieldSources.birth_date === 'ine'" class="badge badge-soft badge-primary badge-xs gap-1">
                        Leído de tu INE
                    </span>
                </label>
                <div class="relative">
                    <input
                        id="reg-birth-date"
                        v-model="form.data.birth_date"
                        type="date"
                        autocomplete="bday"
                        class="input input-md w-full pl-10"
                        :class="{ 'input-error': form.errors.birth_date }"
                        required
                    />
                    <Calendar class="size-4.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-base-content/40 pointer-events-none" />
                </div>
                <span v-if="form.errors.birth_date" class="fieldset-label text-error text-xs font-semibold" role="alert">
                    {{ form.errors.birth_date[0] }}
                </span>
            </div>

            <!-- Teléfono Móvil -->
            <div class="fieldset">
                <label for="reg-phone" class="fieldset-label font-bold text-sm text-base-content">
                    Teléfono Móvil
                </label>
                <div class="relative">
                    <input
                        id="reg-phone"
                        v-model="form.data.phone"
                        type="tel"
                        autocomplete="tel"
                        placeholder="10 dígitos (ej. 9931234567)"
                        class="input input-md w-full pl-10"
                        :class="{ 'input-error': form.errors.phone }"
                    />
                    <Phone class="size-4.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-base-content/40 pointer-events-none" />
                </div>
                <span v-if="form.errors.phone" class="fieldset-label text-error text-xs font-semibold" role="alert">
                    {{ form.errors.phone[0] }}
                </span>
            </div>

            <!-- NSS (Número de Seguridad Social) -->
            <div class="fieldset">
                <label for="reg-nss" class="fieldset-label font-bold text-sm text-base-content flex items-center justify-between">
                    <span>NSS (Seguridad Social)</span>
                    <span v-if="fieldSources.nss === 'nomina'" class="badge badge-soft badge-secondary badge-xs gap-1">
                        Leído de tu nómina
                    </span>
                </label>
                <div class="relative">
                    <input
                        id="reg-nss"
                        v-model="form.data.nss"
                        type="text"
                        maxlength="20"
                        placeholder="11 dígitos numéricos"
                        class="input input-md w-full pl-10 font-mono"
                        :class="{ 'input-error': form.errors.nss }"
                    />
                    <Shield class="size-4.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-base-content/40 pointer-events-none" />
                </div>
                <span v-if="form.errors.nss" class="fieldset-label text-error text-xs font-semibold" role="alert">
                    {{ form.errors.nss[0] }}
                </span>
            </div>

            <!-- Categoría / Puesto -->
            <div class="fieldset">
                <label for="reg-category" class="fieldset-label font-bold text-sm text-base-content flex items-center justify-between">
                    <span>Categoría o Puesto</span>
                    <span v-if="fieldSources.category === 'nomina'" class="badge badge-soft badge-secondary badge-xs gap-1">
                        Leído de tu nómina
                    </span>
                </label>
                <div class="relative">
                    <input
                        id="reg-category"
                        v-model="form.data.category"
                        type="text"
                        placeholder="Ej. Profesor de Asignatura, Administrativo..."
                        class="input input-md w-full pl-10"
                        :class="{ 'input-error': form.errors.category }"
                    />
                    <Briefcase class="size-4.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-base-content/40 pointer-events-none" />
                </div>
                <span v-if="form.errors.category" class="fieldset-label text-error text-xs font-semibold" role="alert">
                    {{ form.errors.category[0] }}
                </span>
            </div>

            <!-- Salario -->
            <div class="fieldset">
                <label for="reg-salary" class="fieldset-label font-bold text-sm text-base-content flex items-center justify-between">
                    <span>Salario Neto Estimado</span>
                    <span v-if="fieldSources.salary === 'nomina'" class="badge badge-soft badge-secondary badge-xs gap-1">
                        Leído de tu nómina
                    </span>
                </label>
                <div class="relative">
                    <input
                        id="reg-salary"
                        v-model="form.data.salary"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="Ej. 8500.00"
                        class="input input-md w-full pl-10 font-mono"
                        :class="{ 'input-error': form.errors.salary }"
                    />
                    <DollarSign class="size-4.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-base-content/40 pointer-events-none" />
                </div>
                <span v-if="form.errors.salary" class="fieldset-label text-error text-xs font-semibold" role="alert">
                    {{ form.errors.salary[0] }}
                </span>
            </div>

            <!-- Domicilio / Dirección -->
            <div class="fieldset sm:col-span-2">
                <label for="reg-address" class="fieldset-label font-bold text-sm text-base-content flex items-center justify-between">
                    <span>Domicilio Particular</span>
                    <span v-if="fieldSources.address === 'ine'" class="badge badge-soft badge-primary badge-xs gap-1">
                        Leído de tu INE
                    </span>
                </label>
                <div class="relative">
                    <textarea
                        id="reg-address"
                        v-model="form.data.address"
                        autocomplete="street-address"
                        rows="2"
                        placeholder="Calle, número exterior/interior, colonia, municipio y código postal"
                        class="textarea textarea-md w-full pl-10"
                        :class="{ 'textarea-error': form.errors.address }"
                    ></textarea>
                    <MapPin class="size-4.5 absolute left-3.5 top-3.5 text-base-content/40 pointer-events-none" />
                </div>
                <span v-if="form.errors.address" class="fieldset-label text-error text-xs font-semibold" role="alert">
                    {{ form.errors.address[0] }}
                </span>
            </div>
        </div>

        <!-- Botones de Navegación -->
        <div class="pt-4 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3 sm:gap-4">
            <button
                type="button"
                class="btn btn-ghost btn-md gap-2 font-semibold"
                @click="emit('prev')"
            >
                <ArrowLeft class="size-4.5" />
                Atrás
            </button>

            <button
                type="button"
                class="btn btn-primary btn-md sm:btn-lg gap-2 font-semibold shadow-md cursor-pointer"
                :disabled="!canProceed"
                @click="emit('next')"
            >
                <span>Continuar a tu cuenta</span>
                <ArrowRight class="size-5" />
            </button>
        </div>
    </section>
</template>
