<x-filament-panels::page>
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden flex flex-col md:flex-row h-[78vh] min-h-150" x-data="{ mobileChatOpen: false }">
        
        <!-- SIDEBAR: Lista de conversaciones -->
        <div class="w-full md:w-80 lg:w-96 shrink-0 border-r border-gray-200 dark:border-gray-800 flex flex-col bg-gray-50/50 dark:bg-gray-900/50"
             :class="{ 'hidden md:flex': mobileChatOpen && {{ $activeConversationId ? 'true' : 'false' }} }">
            
            <!-- Barra superior del sidebar -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between bg-white dark:bg-gray-900">
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Buzón de Mensajes</h2>
                </div>
                <button type="button"
                        wire:click="openNewConversationModal"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-primary-600 text-white hover:bg-primary-500 shadow-sm transition-all">
                    <x-filament::icon icon="heroicon-m-plus" class="w-4 h-4" />
                    <span>Nuevo</span>
                </button>
            </div>

            <!-- Buscador -->
            <div class="p-3 border-b border-gray-200 dark:border-gray-800 bg-white/70 dark:bg-gray-900/70">
                <div class="relative">
                    <x-filament::icon icon="heroicon-m-magnifying-glass" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="Buscar por asunto o usuario..."
                           class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-1 focus:ring-primary-500 focus:border-primary-500 transition-colors" />
                </div>
            </div>

            <!-- Lista de hilos -->
            <div class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800/60">
                @forelse($this->conversations as $conv)
                    @php
                        $other = $conv->getOtherParticipant(auth()->user());
                        $unreadCount = $conv->unreadMessagesCountFor(auth()->user());
                        $isActive = $activeConversationId === $conv->id;
                    @endphp
                    <div wire:key="conv-{{ $conv->id }}"
                         wire:click="selectConversation({{ $conv->id }})"
                         @click="mobileChatOpen = true"
                         class="p-3.5 cursor-pointer transition-all flex items-start gap-3 hover:bg-gray-100/80 dark:hover:bg-gray-800/50 {{ $isActive ? 'bg-primary-50/70 dark:bg-primary-950/30 border-l-4 border-primary-600' : '' }}">
                        
                        <!-- Avatar -->
                        <div class="relative shrink-0">
                            @if($other?->getFilamentAvatarUrl())
                                <img src="{{ $other->getFilamentAvatarUrl() }}" class="w-10 h-10 rounded-full object-cover border border-gray-200 dark:border-gray-700" alt="{{ $other->name }}">
                            @else
                                <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/60 text-primary-700 dark:text-primary-300 font-bold text-xs flex items-center justify-center border border-primary-200/50">
                                    {{ strtoupper(substr($other?->name ?? '?', 0, 2)) }}
                                </div>
                            @endif
                            @if($conv->status->value === 'closed')
                                <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-gray-400 border-2 border-white dark:border-gray-900 rounded-full" title="Cerrada"></span>
                            @else
                                <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-emerald-500 border-2 border-white dark:border-gray-900 rounded-full" title="Activa"></span>
                            @endif
                        </div>

                        <!-- Información -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1 mb-0.5">
                                <span class="text-xs font-bold text-gray-900 dark:text-white truncate">
                                    {{ $other?->full_name ?: $other?->name ?: 'Usuario desconocido' }}
                                </span>
                                <span class="text-[10px] text-gray-400 whitespace-nowrap">
                                    {{ $conv->last_message_at?->diffForHumans(null, true, true) ?? $conv->created_at->diffForHumans(null, true, true) }}
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-medium
                                    {{ $other?->isAdmin() ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' :
                                       ($other?->isLider() ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' :
                                       'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300') }}">
                                    {{ $other?->role?->getLabel() ?? 'Usuario' }}
                                </span>
                            </div>

                            <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate mb-0.5">
                                {{ $conv->subject }}
                            </p>

                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                {{ $conv->latestMessage?->body ?: ($conv->latestMessage?->hasMedia('attachments') ? '📎 Archivo adjunto' : 'Sin mensajes') }}
                            </p>
                        </div>

                        <!-- Badge no leídos -->
                        @if($unreadCount > 0)
                            <div class="shrink-0">
                                <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-[11px] font-bold rounded-full bg-primary-600 text-white">
                                    {{ $unreadCount }}
                                </span>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-400">
                        <x-filament::icon icon="heroicon-o-chat-bubble-bottom-center-text" class="w-10 h-10 mx-auto mb-2 opacity-50" />
                        <p class="text-xs font-medium">No hay conversaciones</p>
                        <button type="button" wire:click="openNewConversationModal" class="mt-3 text-xs text-primary-600 dark:text-primary-400 font-semibold hover:underline">
                            + Iniciar una nueva
                        </button>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- MAIN CHAT: Área de conversación -->
        <div class="flex-1 flex flex-col bg-white dark:bg-gray-900"
             :class="{ 'hidden md:flex': !mobileChatOpen && !{{ $activeConversationId ? 'true' : 'false' }} }">
            
            @if($this->activeConversation)
                @php
                    $otherUser = $this->activeConversation->getOtherParticipant(auth()->user());
                @endphp

                <!-- Encabezado del chat -->
                <div class="p-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between bg-white dark:bg-gray-900">
                    <div class="flex items-center gap-3">
                        <button type="button"
                                @click="mobileChatOpen = false"
                                class="md:hidden p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800">
                            <x-filament::icon icon="heroicon-m-arrow-left" class="w-5 h-5" />
                        </button>

                        <div class="relative">
                            @if($otherUser?->getFilamentAvatarUrl())
                                <img src="{{ $otherUser->getFilamentAvatarUrl() }}" class="w-10 h-10 rounded-full object-cover border border-gray-200 dark:border-gray-700" alt="{{ $otherUser->name }}">
                            @else
                                <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/60 text-primary-700 dark:text-primary-300 font-bold text-xs flex items-center justify-center">
                                    {{ strtoupper(substr($otherUser?->name ?? '?', 0, 2)) }}
                                </div>
                            @endif
                        </div>

                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                                    {{ $otherUser?->full_name ?: $otherUser?->name ?: 'Usuario desconocido' }}
                                </h3>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold
                                    {{ $otherUser?->isAdmin() ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' :
                                       ($otherUser?->isLider() ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' :
                                       'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300') }}">
                                    {{ $otherUser?->role?->getLabel() ?? 'Usuario' }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $this->activeConversation->subject }}</span>
                                <span>&bull;</span>
                                <span class="{{ $this->activeConversation->status->value === 'active' ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                    {{ $this->activeConversation->status->getLabel() }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones del encabezado -->
                    <div class="flex items-center gap-2">
                        <button type="button"
                                wire:click="toggleConversationStatus"
                                class="px-2.5 py-1 text-xs font-medium rounded-lg border transition-colors
                                    {{ $this->activeConversation->status->value === 'active'
                                        ? 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800'
                                        : 'border-emerald-300 text-emerald-700 bg-emerald-50 hover:bg-emerald-100 dark:border-emerald-800 dark:text-emerald-300 dark:bg-emerald-950' }}">
                            {{ $this->activeConversation->status->value === 'active' ? 'Cerrar conversación' : 'Reabrir conversación' }}
                        </button>
                    </div>
                </div>

                <!-- Stream de Mensajes (Burbujas) -->
                <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4 bg-gray-50/40 dark:bg-gray-950/20" id="messages-container">
                    @forelse($this->activeConversation->messages as $msg)
                        @php
                            $isMe = $msg->sender_id === auth()->id();
                        @endphp
                        <div wire:key="msg-{{ $msg->id }}" class="flex flex-col {{ $isMe ? 'items-end' : 'items-start' }}">
                            
                            <!-- Nombre del remitente (si es el otro usuario) -->
                            @if(!$isMe)
                                <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 ml-2 mb-1">
                                    {{ $msg->sender?->name }}
                                </span>
                            @endif

                            <!-- Burbuja principal -->
                            <div class="max-w-[85%] sm:max-w-[70%] rounded-2xl p-3.5 shadow-xs
                                {{ $isMe
                                    ? 'bg-primary-600 text-white rounded-br-xs'
                                    : 'bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-200/80 dark:border-gray-700 rounded-bl-xs' }}">
                                
                                @if($msg->body)
                                    <p class="text-sm whitespace-pre-wrap leading-relaxed">{{ $msg->body }}</p>
                                @endif

                                <!-- Adjuntos -->
                                @if($msg->hasMedia('attachments'))
                                    <div class="mt-2 space-y-1.5 pt-2 border-t {{ $isMe ? 'border-primary-500/50' : 'border-gray-200 dark:border-gray-700' }}">
                                        @foreach($msg->getMedia('attachments') as $media)
                                            <a href="{{ route('portal.messages.attachment.download', $media) }}"
                                               target="_blank"
                                               class="flex items-center gap-2.5 p-2 rounded-lg text-xs font-medium transition-colors
                                                {{ $isMe
                                                    ? 'bg-primary-700/60 hover:bg-primary-700 text-white'
                                                    : 'bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-900 dark:text-white' }}">
                                                <x-filament::icon icon="heroicon-o-paper-clip" class="w-4 h-4 shrink-0" />
                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate font-semibold">{{ $media->file_name }}</p>
                                                    <p class="text-[10px] opacity-75">{{ $media->human_readable_size }}</p>
                                                </div>
                                                <x-filament::icon icon="heroicon-m-arrow-down-tray" class="w-4 h-4 shrink-0 opacity-80" />
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="mt-1 flex items-center justify-end gap-1 text-[10px] {{ $isMe ? 'text-primary-100' : 'text-gray-400' }}">
                                    <span>{{ $msg->created_at->format('H:i') }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center text-gray-400 text-xs">
                            No hay mensajes registrados aún en este hilo.
                        </div>
                    @endforelse
                </div>

                <!-- Input Footer para redactar -->
                <div class="p-3 sm:p-4 border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
                    @if($this->activeConversation->status->value === 'closed')
                        <div class="p-3 rounded-xl bg-gray-100 dark:bg-gray-800 text-center text-xs text-gray-500 flex items-center justify-center gap-2">
                            <x-filament::icon icon="heroicon-m-lock-closed" class="w-4 h-4" />
                            <span>Esta conversación está marcada como cerrada.</span>
                            <button type="button" wire:click="toggleConversationStatus" class="font-semibold text-primary-600 hover:underline">
                                Reabrir
                            </button>
                        </div>
                    @else
                        <!-- Vista previa de archivo adjunto seleccionado -->
                        @if($attachment)
                            <div class="mb-2 p-2 px-3 rounded-lg bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2 text-primary-700 dark:text-primary-300 truncate">
                                    <x-filament::icon icon="heroicon-o-paper-clip" class="w-4 h-4 shrink-0" />
                                    <span class="truncate font-medium">{{ $attachment->getClientOriginalName() }}</span>
                                </div>
                                <button type="button" wire:click="removeAttachment" class="text-gray-400 hover:text-red-500 ml-2">
                                    <x-filament::icon icon="heroicon-m-x-mark" class="w-4 h-4" />
                                </button>
                            </div>
                        @endif

                        <form wire:submit.prevent="sendMessage" class="flex gap-2 items-end">
                            <!-- Botón Adjuntar Archivo -->
                            <label class="p-2.5 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors relative shrink-0">
                                <x-filament::icon icon="heroicon-o-paper-clip" class="w-5 h-5" />
                                <input type="file" wire:model="attachment" class="sr-only" />
                            </label>

                            <!-- Campo de texto autoexpandible tipo WhatsApp -->
                            <div class="flex-1 relative min-w-0"
                                 x-data="{
                                     resize() {
                                         const el = $refs.messageInput;
                                         if (!el) return;
                                         el.style.height = 'auto';
                                         const maxHeight = 140;
                                         const newHeight = Math.min(el.scrollHeight, maxHeight);
                                         el.style.height = `${newHeight}px`;
                                         el.style.overflowY = el.scrollHeight > maxHeight ? 'auto' : 'hidden';
                                     }
                                 }"
                                 x-init="
                                     $nextTick(() => resize());
                                     $watch('$wire.newMessageBody', () => $nextTick(() => resize()));
                                 ">
                                <textarea x-ref="messageInput"
                                          wire:model="newMessageBody"
                                          rows="1"
                                          placeholder="Escribe un mensaje aquí..."
                                          title="Enter para enviar, Shift + Enter para salto de línea"
                                          @input="resize()"
                                          @keydown.enter.exact.prevent="if ($el.value.trim().length > 0 || $wire.attachment) $el.form.requestSubmit()"
                                          class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 resize-none transition-none leading-relaxed block overflow-hidden"
                                          style="min-height: 42px; max-height: 140px;"></textarea>
                            </div>

                            <!-- Botón Enviar -->
                            <button type="submit"
                                    class="p-2.5 rounded-xl bg-primary-600 hover:bg-primary-500 text-white shadow-sm transition-all shrink-0 flex items-center justify-center"
                                    wire:loading.attr="disabled"
                                    title="Enviar mensaje">
                                <x-filament::icon icon="heroicon-m-paper-airplane" class="w-5 h-5" />
                            </button>
                        </form>
                    @endif
                </div>

            @else
                <!-- Estado vacío cuando no hay conversación activa seleccionada -->
                <div class="flex-1 flex flex-col items-center justify-center p-8 text-center text-gray-400">
                    <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-3">
                        <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="w-8 h-8 opacity-50" />
                    </div>
                    <h3 class="text-base font-bold text-gray-700 dark:text-gray-200 mb-1">Selecciona una conversación</h3>
                    <p class="text-xs text-gray-500 max-w-sm mb-4">
                        Elige una conversación de la lista de la izquierda para ver el historial de mensajes o inicia una nueva con un líder o agremiado.
                    </p>
                    <button type="button" wire:click="openNewConversationModal"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-xl bg-primary-600 text-white hover:bg-primary-500 transition-all">
                        <x-filament::icon icon="heroicon-m-plus" class="w-4 h-4" />
                        <span>Nueva conversación</span>
                    </button>
                </div>
            @endif
        </div>
    </div>

    <!-- MODAL: Nueva Conversación -->
    @if($showNewConversationModal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-xs"
             x-transition>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl max-w-lg w-full p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Iniciar Nueva Conversación</h3>
                    <button type="button" wire:click="closeNewConversationModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <x-filament::icon icon="heroicon-m-x-mark" class="w-5 h-5" />
                    </button>
                </div>

                <form wire:submit.prevent="createConversation" class="mt-4 space-y-4">
                    <!-- Destinatario con buscador -->
                    <div class="relative"
                         x-data="{
                             open: false,
                             search: '',
                             selectedId: @js($newRecipientId),
                             recipients: @js($this->recipientsList),
                             normalize(str) {
                                 return str ? str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase() : '';
                             },
                             init() {
                                 this.$watch('$wire.newRecipientId', (value) => {
                                     this.selectedId = value;
                                 });
                             },
                             get selectedRecipient() {
                                 return this.recipients.find(r => r.id == this.selectedId) || null;
                             },
                             get filteredRecipients() {
                                 if (!this.search || !this.search.trim()) {
                                     return this.recipients;
                                 }
                                 const term = this.normalize(this.search.trim());
                                 return this.recipients.filter(r => {
                                     return this.normalize(r.full_name).includes(term)
                                         || this.normalize(r.email).includes(term)
                                         || this.normalize(r.role_label).includes(term)
                                         || this.normalize(r.category).includes(term);
                                 });
                             },
                             select(recipient) {
                                 this.selectedId = recipient.id;
                                 $wire.set('newRecipientId', recipient.id);
                                 this.open = false;
                                 this.search = '';
                             },
                             clear() {
                                 this.selectedId = null;
                                 $wire.set('newRecipientId', null);
                                 this.search = '';
                                 this.open = true;
                                 this.$nextTick(() => {
                                     if (this.$refs.recipientSearchInput) {
                                         this.$refs.recipientSearchInput.focus();
                                     }
                                 });
                             }
                         }"
                         @click.outside="open = false">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1.5">
                            Destinatario
                        </label>

                        <!-- Input oculto para sincronización con Livewire -->
                        <input type="hidden" wire:model="newRecipientId" :value="selectedId" />

                        <!-- Estado: Destinatario Seleccionado -->
                        <div x-show="selectedRecipient" x-cloak class="p-2.5 rounded-xl border border-primary-200 dark:border-primary-800/80 bg-primary-50/50 dark:bg-primary-950/20 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <template x-if="selectedRecipient && selectedRecipient.avatar_url">
                                    <img :src="selectedRecipient.avatar_url" class="w-9 h-9 rounded-full object-cover border border-primary-200 dark:border-primary-700 shrink-0" :alt="selectedRecipient.full_name">
                                </template>
                                <template x-if="selectedRecipient && !selectedRecipient.avatar_url">
                                    <div class="w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900/60 text-primary-700 dark:text-primary-300 font-bold text-xs flex items-center justify-center shrink-0 border border-primary-200/50"
                                         x-text="selectedRecipient?.initials">
                                    </div>
                                </template>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-gray-900 dark:text-white truncate" x-text="selectedRecipient?.full_name"></span>
                                        <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold shrink-0"
                                              :class="{
                                                  'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': selectedRecipient?.role === 'admin',
                                                  'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': selectedRecipient?.role === 'lider',
                                                  'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300': selectedRecipient?.role === 'agremiado'
                                              }"
                                              x-text="selectedRecipient?.role_label">
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate" x-text="(selectedRecipient?.email ?? '') + (selectedRecipient?.category ? ' • ' + selectedRecipient.category : '')"></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button"
                                        @click="clear()"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white hover:bg-white/80 dark:hover:bg-gray-800 rounded-lg border border-gray-200/80 dark:border-gray-700 transition-colors"
                                        title="Cambiar destinatario">
                                    <x-filament::icon icon="heroicon-m-arrow-path" class="w-3.5 h-3.5" />
                                    <span>Cambiar</span>
                                </button>
                            </div>
                        </div>

                        <!-- Estado: Botón trigger cuando no hay selección -->
                        <div x-show="!selectedRecipient">
                            <button type="button"
                                    @click="open = !open; $nextTick(() => { if (open && $refs.recipientSearchInput) $refs.recipientSearchInput.focus(); })"
                                    class="w-full flex items-center justify-between px-3.5 py-2.5 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-left transition-all hover:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    :class="{ 'border-primary-500 ring-1 ring-primary-500 bg-white dark:bg-gray-800': open }">
                                <div class="flex items-center gap-2 text-gray-400 dark:text-gray-500">
                                    <x-filament::icon icon="heroicon-m-magnifying-glass" class="w-4 h-4 text-gray-400 shrink-0" />
                                    <span class="text-xs">Buscar y seleccionar destinatario...</span>
                                </div>
                                <div class="flex items-center gap-1.5 text-gray-400">
                                    <span class="text-[11px] font-medium hidden sm:inline" x-text="recipients.length + ' disponibles'"></span>
                                    <x-filament::icon icon="heroicon-m-chevron-down" class="w-4 h-4 transition-transform duration-200" ::class="{ 'rotate-180': open }" />
                                </div>
                            </button>
                        </div>

                        <!-- Desplegable con buscador -->
                        <div x-show="open"
                             x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-1"
                             class="absolute left-0 right-0 top-full mt-1.5 z-40 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col max-h-72">
                            
                            <!-- Buscador interno con autofocus -->
                            <div class="p-2.5 border-b border-gray-100 dark:border-gray-700/80 bg-gray-50/70 dark:bg-gray-900/50">
                                <div class="relative">
                                    <x-filament::icon icon="heroicon-m-magnifying-glass" class="w-4 h-4 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
                                    <input type="text"
                                           x-ref="recipientSearchInput"
                                           x-model="search"
                                           @keydown.escape.prevent="open = false"
                                           @keydown.enter.prevent="if (filteredRecipients.length > 0) select(filteredRecipients[0])"
                                           placeholder="Escribe el nombre, correo o rol..."
                                           class="w-full pl-8 pr-7 py-1.5 text-xs rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 transition-colors" />
                                    <button type="button"
                                            x-show="search.length > 0"
                                            @click="search = ''; $refs.recipientSearchInput.focus()"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                        <x-filament::icon icon="heroicon-m-x-circle" class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>

                            <!-- Lista de destinatarios filtrados -->
                            <div class="overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800/60 max-h-56">
                                <template x-for="recipient in filteredRecipients" :key="recipient.id">
                                    <button type="button"
                                            @click="select(recipient)"
                                            class="w-full text-left p-2.5 flex items-center justify-between gap-2.5 transition-colors hover:bg-primary-50/60 dark:hover:bg-primary-950/30"
                                            :class="{ 'bg-primary-50/80 dark:bg-primary-950/40': recipient.id == selectedId }">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <template x-if="recipient.avatar_url">
                                                <img :src="recipient.avatar_url" class="w-8 h-8 rounded-full object-cover border border-gray-200 dark:border-gray-700 shrink-0" :alt="recipient.full_name">
                                            </template>
                                            <template x-if="!recipient.avatar_url">
                                                <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/60 text-primary-700 dark:text-primary-300 font-bold text-xs flex items-center justify-center shrink-0 border border-primary-200/50"
                                                     x-text="recipient.initials">
                                                </div>
                                            </template>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="text-xs font-bold text-gray-900 dark:text-white truncate" x-text="recipient.full_name"></span>
                                                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-medium shrink-0"
                                                          :class="{
                                                              'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': recipient.role === 'admin',
                                                              'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': recipient.role === 'lider',
                                                              'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300': recipient.role === 'agremiado'
                                                          }"
                                                          x-text="recipient.role_label">
                                                    </span>
                                                </div>
                                                <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate" x-text="recipient.email + (recipient.category ? ' • ' + recipient.category : '')"></p>
                                            </div>
                                        </div>
                                        <template x-if="recipient.id == selectedId">
                                            <x-filament::icon icon="heroicon-m-check" class="w-4 h-4 text-primary-600 dark:text-primary-400 shrink-0" />
                                        </template>
                                    </button>
                                </template>

                                <!-- Sin resultados -->
                                <div x-show="filteredRecipients.length === 0" class="py-6 px-4 text-center text-gray-400 text-xs">
                                    <x-filament::icon icon="heroicon-o-user" class="w-7 h-7 mx-auto mb-1.5 opacity-40" />
                                    <p class="font-medium text-gray-600 dark:text-gray-300">No se encontraron usuarios</p>
                                    <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5" x-text="search ? 'No hay coincidencias para &quot;' + search + '&quot;' : 'No hay destinatarios disponibles'"></p>
                                </div>
                            </div>
                        </div>

                        @error('newRecipientId') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Asunto -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1.5">
                            Asunto o Motivo
                        </label>
                        <input type="text"
                               wire:model="newSubject"
                               placeholder="Ej. Consulta sobre solicitud de préstamo"
                               class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-1 focus:ring-primary-500" />
                        @error('newSubject') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Mensaje inicial -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1.5">
                            Mensaje
                        </label>
                        <textarea wire:model="newInitialMessage"
                                  rows="4"
                                  placeholder="Escribe aquí tu mensaje inicial..."
                                  class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-1 focus:ring-primary-500 resize-none"></textarea>
                        @error('newInitialMessage') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Archivo adjunto opcional -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1.5">
                            Archivo adjunto (Opcional)
                        </label>
                        @if($newAttachment)
                            <div class="p-2 px-3 rounded-lg bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800 flex items-center justify-between text-xs mb-2">
                                <span class="truncate font-medium text-primary-700 dark:text-primary-300">{{ $newAttachment->getClientOriginalName() }}</span>
                                <button type="button" wire:click="removeNewAttachment" class="text-gray-400 hover:text-red-500">
                                    <x-filament::icon icon="heroicon-m-x-mark" class="w-4 h-4" />
                                </button>
                            </div>
                        @else
                            <input type="file" wire:model="newAttachment"
                                   class="text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100" />
                        @endif
                        @error('newAttachment') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Botones de acción -->
                    <div class="pt-3 flex items-center justify-end gap-2 border-t border-gray-100 dark:border-gray-700">
                        <button type="button"
                                wire:click="closeNewConversationModal"
                                class="px-4 py-2 text-xs font-medium rounded-xl border border-gray-300 text-gray-700 dark:border-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-xs font-semibold rounded-xl bg-primary-600 text-white hover:bg-primary-500 shadow-sm transition-all">
                            Enviar e Iniciar Conversación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-filament-panels::page>
