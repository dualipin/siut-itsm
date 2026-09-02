<script setup lang="ts">
import { ref, computed } from 'vue'
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
        isAdmin?: boolean
    } | null
}>()

const isAdmin = computed(() => !!props.authUser?.isAdmin)
const modalRef = ref<HTMLDialogElement | null>(null)

const form = useIslandForm({
    title: '',
    category: 'General',
    body: '',
    is_public: false,
    guest_name: '',
    guest_email: '',
})

const openModal = () => {
    modalRef.value?.showModal()
}

const closeModal = () => {
    modalRef.value?.close()
}

const onModalClose = () => {
    form.clearFeedback()
}

const handleInquirySubmit = async () => {
    await form.post('/dudas', {
        onSuccess: () => {
            setTimeout(() => {
                closeModal()
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
            @click="openModal"
            type="button"
            class="btn btn-primary rounded-full px-7 shadow-lg shadow-primary/20 hover:shadow-primary/40 hover:-translate-y-0.5 transition-all text-sm font-bold gap-2">
            <Plus class="w-4 h-4" />
            <span>Hacer una consulta o duda</span>
        </button>

        <!-- Modal Nativo DaisyUI (Top Layer con showModal, sin Teleport) -->
        <dialog
            ref="modalRef"
            class="modal modal-bottom sm:modal-middle"
            @close="onModalClose">
            <div class="modal-box max-w-lg w-11/12 sm:w-full p-6 sm:p-8 rounded-3xl border border-base-300 shadow-2xl relative text-left">
                <!-- Botón cerrar -->
                <button
                    @click="closeModal"
                    type="button"
                    class="btn btn-sm btn-circle btn-ghost absolute right-4 top-4 text-base-content/60 hover:text-base-content"
                    aria-label="Cerrar modal">
                    <X class="w-5 h-5" />
                </button>

                <div class="mb-6">
                    <span class="text-xs font-bold uppercase tracking-wider text-primary">Consulta Sindical</span>
                    <h3 class="text-xl font-bold text-base-content mt-0.5">Plantea tu Duda o Inquietud</h3>
                    <p class="text-xs text-base-content/60 mt-1">
                        {{ isAdmin 
                            ? 'Como administrador puedes definir la categoría y la visibilidad pública o privada de la duda.'
                            : 'Envía tu duda al equipo sindical. La atenderemos de forma confidencial a la brevedad.' }}
                    </p>
                </div>

                <form @submit.prevent="handleInquirySubmit" class="space-y-4">
                    <!-- Si no está autenticado, pide nombre y correo -->
                    <template v-if="!authUser">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-base-content/70 flex items-center gap-1.5">
                                    <User class="w-3.5 h-3.5 text-primary" />
                                    Tu Nombre
                                </label>
                                <input
                                    v-model="form.data.guest_name"
                                    type="text"
                                    placeholder="Ej. Juan Pérez"
                                    class="input input-sm w-full rounded-xl"
                                    :class="{ 'input-error': form.errors.guest_name }"
                                    required />
                                <span v-if="form.errors.guest_name" class="text-[10px] text-error">{{ form.errors.guest_name[0] }}</span>
                            </div>

                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-base-content/70 flex items-center gap-1.5">
                                    <Mail class="w-3.5 h-3.5 text-primary" />
                                    Tu Correo
                                </label>
                                <input
                                    v-model="form.data.guest_email"
                                    type="email"
                                    placeholder="ejemplo@correo.com"
                                    class="input input-sm w-full rounded-xl"
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
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="font-bold text-base-content mb-0 truncate">{{ authUser.name }}</p>
                                    <span v-if="isAdmin" class="badge badge-xs badge-primary font-semibold text-[10px]">Admin</span>
                                </div>
                                <p class="text-[11px] text-base-content/60 mb-0 truncate">
                                    {{ authUser.role ? authUser.role + ' · ' : '' }}{{ authUser.email }}
                                </p>
                            </div>
                        </div>
                    </template>

                    <!-- Título -->
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-base-content/70 flex items-center gap-1.5">
                            Título o pregunta principal
                        </label>
                        <input
                            v-model="form.data.title"
                            type="text"
                            placeholder="Ej. ¿Cómo se solicita la revisión de escalafón este ciclo?"
                            class="input input-sm w-full rounded-xl"
                            :class="{ 'input-error': form.errors.title }"
                            required />
                        <span v-if="form.errors.title" class="text-[10px] text-error">{{ form.errors.title[0] }}</span>
                    </div>

                    <!-- Categoría (Solo editable por administradores) -->
                    <div v-if="isAdmin" class="space-y-1">
                        <label class="text-xs font-semibold text-base-content/70 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <Tag class="w-3.5 h-3.5 text-primary" />
                                Categoría
                            </span>
                            <span class="badge badge-xs badge-ghost text-[10px]">Solo Administrador</span>
                        </label>
                        <select v-model="form.data.category" class="select select-sm w-full rounded-xl text-xs">
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
                        <label class="text-xs font-semibold text-base-content/70 flex items-center gap-1.5">
                            Detalle de tu consulta
                        </label>
                        <textarea
                            v-model="form.data.body"
                            rows="4"
                            placeholder="Describe detalladamente tu situación o pregunta..."
                            class="textarea textarea-sm w-full rounded-2xl resize-none text-xs leading-relaxed"
                            :class="{ 'textarea-error': form.errors.body }"
                            required></textarea>
                        <span v-if="form.errors.body" class="text-[10px] text-error">{{ form.errors.body[0] }}</span>
                    </div>

                    <!-- Toggle Pública / Privada (Solo editable por administradores) -->
                    <div v-if="isAdmin" class="p-3.5 rounded-2xl bg-base-200/50 border border-base-300/80">
                        <label class="cursor-pointer flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <Globe v-if="form.data.is_public" class="w-4 h-4 text-success shrink-0" />
                                <Lock v-else class="w-4 h-4 text-warning shrink-0" />
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-bold text-base-content">
                                            {{ form.data.is_public ? 'Duda Pública' : 'Duda Privada' }}
                                        </span>
                                        <span class="badge badge-xs badge-ghost text-[10px]">Solo Administrador</span>
                                    </div>
                                    <span class="text-[11px] text-base-content/60 block">
                                        {{ form.data.is_public ? 'Será visible en esta sección para ayudar a otros compañeros' : 'Solo los administradores podrán verla' }}
                                    </span>
                                </div>
                            </div>
                            <input type="checkbox" v-model="form.data.is_public" class="toggle toggle-primary toggle-sm" />
                        </label>
                    </div>

                    <!-- Para agremiados y visitantes: Aviso informativo de confidencialidad -->
                    <div v-else class="p-3.5 rounded-2xl bg-base-200/40 border border-base-300/60 flex items-start gap-2.5 text-xs text-base-content/70">
                        <Lock class="w-4 h-4 text-primary shrink-0 mt-0.5" />
                        <div>
                            <span class="font-bold text-base-content block">Consulta Confidencial</span>
                            <span class="text-[11px] text-base-content/60 block">
                                Tu consulta se registrará de forma privada. La administración sindical la revisará, clasificará y responderá directamente.
                            </span>
                        </div>
                    </div>

                    <!-- Feedback con DaisyUI Alert -->
                    <div v-if="form.statusMessage" role="alert" class="alert alert-soft text-xs py-2.5 px-3.5 rounded-xl flex items-center gap-2"
                         :class="form.statusType === 'success' ? 'alert-success' : 'alert-error'">
                        <CheckCircle2 v-if="form.statusType === 'success'" class="w-4 h-4 shrink-0" />
                        <AlertCircle v-else class="w-4 h-4 shrink-0" />
                        <span>{{ form.statusMessage }}</span>
                    </div>

                    <!-- Botones (DaisyUI modal-action) -->
                    <div class="modal-action pt-2">
                        <button @click="closeModal" type="button" class="btn btn-sm btn-ghost rounded-xl">
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

            <!-- Backdrop DaisyUI nativo para cerrar al hacer clic afuera -->
            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>
    </div>
</template>
