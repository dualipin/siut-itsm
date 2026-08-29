<script setup lang="ts">
import { ref } from 'vue'
import { useIslandForm } from '@/composables/useIslandForm'
import {
    Plus,
    X,
    User,
    Mail,
    Send,
    Tag,
    Lock,
    Globe,
    CheckCircle2,
    AlertCircle,
} from '@lucide/vue'

const props = defineProps<{
    authUser?: {
        id: number
        name: string
        email: string
        role: string
    } | null
}>()

const showModal = ref(false)

const form = useIslandForm({
    title: '',
    category: 'General',
    body: '',
    is_public: true,
    guest_name: '',
    guest_email: '',
})

const handleInquirySubmit = async () => {
    await form.post('/dudas', {
        onSuccess: () => {
            setTimeout(() => {
                showModal.value = false
                form.reset()
                window.location.reload()
            }, 1500)
        },
    })
}
</script>

<template>
    <div>
        <!-- Botón Trigger -->
        <button
            @click="showModal = true"
            type="button"
            class="btn btn-primary rounded-full px-7 shadow-lg shadow-primary/20 hover:shadow-primary/40 hover:-translate-y-0.5 transition-all text-sm font-bold gap-2">
            <Plus class="w-4 h-4" />
            <span>Hacer una consulta o duda</span>
        </button>

        <!-- MODAL -->
        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-base-100 rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 border border-base-300 relative animate-fade-in">
                <!-- Botón cerrar -->
                <button @click="showModal = false" type="button" class="btn btn-sm btn-circle btn-ghost absolute right-4 top-4 text-base-content/60">
                    <X class="w-5 h-5" />
                </button>

                <div class="mb-6">
                    <span class="text-xs font-bold uppercase tracking-wider text-primary">Consulta Sindical</span>
                    <h3 class="text-xl font-bold text-base-content mt-0.5">Plantea tu Duda o Inquietud</h3>
                    <p class="text-xs text-base-content/60 mt-1">
                        Puedes registrar tu duda de forma pública o privada. Las respuestas oficiales incluirán documentación de apoyo si aplica.
                    </p>
                </div>

                <form @submit.prevent="handleInquirySubmit" class="space-y-4">
                    <!-- Si no está autenticado, pide nombre y correo -->
                    <template v-if="!authUser">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-base-content/70 flex items-center gap-1">
                                    <User class="w-3.5 h-3.5 text-primary" />
                                    Tu Nombre
                                </label>
                                <input
                                    v-model="form.data.guest_name"
                                    type="text"
                                    placeholder="Ej. Juan Pérez"
                                    class="input input-sm input-bordered w-full rounded-xl"
                                    :class="{ 'input-error': form.errors.guest_name }"
                                    required />
                                <span v-if="form.errors.guest_name" class="text-[10px] text-error">{{ form.errors.guest_name[0] }}</span>
                            </div>

                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-base-content/70 flex items-center gap-1">
                                    <Mail class="w-3.5 h-3.5 text-primary" />
                                    Tu Correo
                                </label>
                                <input
                                    v-model="form.data.guest_email"
                                    type="email"
                                    placeholder="ejemplo@correo.com"
                                    class="input input-sm input-bordered w-full rounded-xl"
                                    :class="{ 'input-error': form.errors.guest_email }"
                                    required />
                                <span v-if="form.errors.guest_email" class="text-[10px] text-error">{{ form.errors.guest_email[0] }}</span>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <div class="p-3 rounded-2xl bg-base-200/60 border border-base-300/80 flex items-center gap-3 text-xs">
                            <div class="w-8 h-8 rounded-full bg-primary/10 text-primary font-bold flex items-center justify-center">
                                {{ authUser.name.charAt(0) }}
                            </div>
                            <div>
                                <p class="font-bold text-base-content mb-0">{{ authUser.name }}</p>
                                <p class="text-[11px] text-base-content/60 mb-0">Agremiado registrado ({{ authUser.email }})</p>
                            </div>
                        </div>
                    </template>

                    <!-- Título -->
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-base-content/70">
                            Título o pregunta principal
                        </label>
                        <input
                            v-model="form.data.title"
                            type="text"
                            placeholder="Ej. ¿Cómo se solicita la revisión de escalafón este ciclo?"
                            class="input input-sm input-bordered w-full rounded-xl"
                            :class="{ 'input-error': form.errors.title }"
                            required />
                        <span v-if="form.errors.title" class="text-[10px] text-error">{{ form.errors.title[0] }}</span>
                    </div>

                    <!-- Categoría -->
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-base-content/70 flex items-center gap-1">
                            <Tag class="w-3.5 h-3.5 text-primary" />
                            Categoría
                        </label>
                        <select v-model="form.data.category" class="select select-sm select-bordered w-full rounded-xl text-xs">
                            <option value="Trámites">Trámites</option>
                            <option value="Escalafón">Escalafón</option>
                            <option value="Prestaciones">Prestaciones</option>
                            <option value="Cuotas y Finanzas">Cuotas y Finanzas</option>
                            <option value="Afiliación">Afiliación</option>
                            <option value="General">General</option>
                        </select>
                    </div>

                    <!-- Detalle -->
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-base-content/70">
                            Detalle de tu consulta
                        </label>
                        <textarea
                            v-model="form.data.body"
                            rows="4"
                            placeholder="Describe detalladamente tu situación o pregunta..."
                            class="textarea textarea-sm textarea-bordered w-full rounded-2xl resize-none text-xs leading-relaxed"
                            :class="{ 'textarea-error': form.errors.body }"
                            required></textarea>
                        <span v-if="form.errors.body" class="text-[10px] text-error">{{ form.errors.body[0] }}</span>
                    </div>

                    <!-- Toggle Pública / Privada -->
                    <div class="p-3 rounded-2xl bg-base-200/50 border border-base-300/80">
                        <label class="cursor-pointer flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <Globe v-if="form.data.is_public" class="w-4 h-4 text-success" />
                                <Lock v-else class="w-4 h-4 text-warning" />
                                <div>
                                    <span class="text-xs font-bold text-base-content block">
                                        {{ form.data.is_public ? 'Duda Pública' : 'Duda Privada' }}
                                    </span>
                                    <span class="text-[11px] text-base-content/60 block">
                                        {{ form.data.is_public ? 'Será visible en esta sección para ayudar a otros compañeros' : 'Solo tú y los administradores podrán verla' }}
                                    </span>
                                </div>
                            </div>
                            <input type="checkbox" v-model="form.data.is_public" class="toggle toggle-primary toggle-sm" />
                        </label>
                    </div>

                    <!-- Feedback -->
                    <div v-if="form.statusMessage" class="p-3 rounded-xl border flex items-center gap-2 text-xs"
                         :class="form.statusType === 'success' ? 'bg-success/10 border-success/30 text-success' : 'bg-error/10 border-error/30 text-error'">
                        <CheckCircle2 v-if="form.statusType === 'success'" class="w-4 h-4 shrink-0" />
                        <AlertCircle v-else class="w-4 h-4 shrink-0" />
                        <span>{{ form.statusMessage }}</span>
                    </div>

                    <!-- Botones -->
                    <div class="pt-3 flex items-center justify-end gap-2">
                        <button @click="showModal = false" type="button" class="btn btn-sm btn-ghost rounded-xl">
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="btn btn-sm btn-primary rounded-xl px-5 font-bold gap-2">
                            <span v-if="form.processing" class="loading loading-spinner loading-xs"></span>
                            <Send v-else class="w-3.5 h-3.5" />
                            <span>Enviar Consulta</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
