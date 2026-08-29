<?php

namespace App\Filament\Pages;

use App\Enums\ConversationStatus;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\User;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\WithFileUploads;
use UnitEnum;

class Messages extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChatBubbleLeftRight;

    protected static UnitEnum|string|null $navigationGroup = 'Comunicación';

    protected static ?string $navigationLabel = 'Mensajería';

    protected static ?string $title = 'Mensajería';

    protected static ?string $slug = 'messages';

    protected string $view = 'filament.pages.messages';

    public ?int $activeConversationId = null;

    public string $search = '';

    public string $newMessageBody = '';

    public mixed $attachment = null;

    // New conversation modal properties
    public bool $showNewConversationModal = false;

    public ?int $newRecipientId = null;

    public string $newSubject = '';

    public string $newInitialMessage = '';

    public mixed $newAttachment = null;

    public function mount(?int $conversation = null): void
    {
        if ($conversation && $this->canAccessConversation($conversation)) {
            $this->selectConversation($conversation);
        } else {
            $first = $this->conversations->first();
            if ($first) {
                $this->selectConversation($first->id);
            }
        }
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->is_active ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $unreadCount = 0;
        $conversations = Conversation::whereHas('participants', fn ($q) => $q->where('user_id', $user->id))->get();

        foreach ($conversations as $conv) {
            $unreadCount += $conv->unreadMessagesCountFor($user);
        }

        return $unreadCount > 0 ? (string) $unreadCount : null;
    }

    public function selectConversation(int $id): void
    {
        if (! $this->canAccessConversation($id)) {
            return;
        }

        $this->activeConversationId = $id;

        /** @var Conversation|null $conv */
        $conv = Conversation::find($id);
        if ($conv) {
            $conv->markAsReadFor(auth()->id());
        }

        $this->reset(['newMessageBody', 'attachment']);
    }

    protected function canAccessConversation(int $conversationId): bool
    {
        return ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', auth()->id())
            ->exists();
    }

    public function sendMessage(): void
    {
        $this->validate([
            'newMessageBody' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:15360'], // 15MB
        ]);

        if (empty(trim($this->newMessageBody)) && ! $this->attachment) {
            Notification::make()
                ->title('Mensaje vacío')
                ->body('Escribe un mensaje o adjunta un archivo.')
                ->warning()
                ->send();

            return;
        }

        $conversation = Conversation::find($this->activeConversationId);
        if (! $conversation || ! $this->canAccessConversation($conversation->id)) {
            return;
        }

        $message = ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => auth()->id(),
            'body' => trim($this->newMessageBody) ?: null,
        ]);

        if ($this->attachment) {
            $message->addMedia($this->attachment->getRealPath())
                ->usingFileName($this->attachment->getClientOriginalName())
                ->toMediaCollection('attachments');
        }

        $conversation->update([
            'last_message_at' => now(),
            'status' => ConversationStatus::Active,
        ]);

        $conversation->markAsReadFor(auth()->id());

        $this->reset(['newMessageBody', 'attachment']);
    }

    public function removeAttachment(): void
    {
        $this->attachment = null;
    }

    public function removeNewAttachment(): void
    {
        $this->newAttachment = null;
    }

    public function openNewConversationModal(): void
    {
        $this->reset(['newRecipientId', 'newSubject', 'newInitialMessage', 'newAttachment']);
        $this->showNewConversationModal = true;
    }

    public function closeNewConversationModal(): void
    {
        $this->showNewConversationModal = false;
        $this->reset(['newRecipientId', 'newSubject', 'newInitialMessage', 'newAttachment']);
    }

    public function createConversation(): void
    {
        $this->validate([
            'newRecipientId' => ['required', 'exists:users,id'],
            'newSubject' => ['required', 'string', 'max:255', 'min:3'],
            'newInitialMessage' => ['required', 'string', 'max:5000'],
            'newAttachment' => ['nullable', 'file', 'max:15360'],
        ], [
            'newRecipientId.required' => 'Selecciona un destinatario.',
            'newSubject.required' => 'El asunto es obligatorio.',
            'newInitialMessage.required' => 'Escribe un mensaje inicial.',
        ]);

        $userId = auth()->id();

        if ($this->newRecipientId === $userId) {
            Notification::make()
                ->title('Error')
                ->body('No puedes iniciar una conversación contigo mismo.')
                ->danger()
                ->send();

            return;
        }

        $conversation = Conversation::create([
            'subject' => $this->newSubject,
            'created_by' => $userId,
            'status' => ConversationStatus::Active,
            'last_message_at' => now(),
        ]);

        ConversationParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
            'last_read_at' => now(),
        ]);

        ConversationParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->newRecipientId,
            'last_read_at' => null,
        ]);

        $message = ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userId,
            'body' => $this->newInitialMessage,
        ]);

        if ($this->newAttachment) {
            $message->addMedia($this->newAttachment->getRealPath())
                ->usingFileName($this->newAttachment->getClientOriginalName())
                ->toMediaCollection('attachments');
        }

        $this->closeNewConversationModal();
        $this->selectConversation($conversation->id);

        Notification::make()
            ->title('Conversación iniciada')
            ->success()
            ->send();
    }

    public function toggleConversationStatus(): void
    {
        if (! $this->activeConversation) {
            return;
        }

        $newStatus = $this->activeConversation->status === ConversationStatus::Active
            ? ConversationStatus::Closed
            : ConversationStatus::Active;

        $this->activeConversation->update(['status' => $newStatus]);

        Notification::make()
            ->title($newStatus === ConversationStatus::Closed ? 'Conversación cerrada' : 'Conversación reabierta')
            ->success()
            ->send();
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function getConversationsProperty(): Collection
    {
        $userId = auth()->id();

        $query = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->with(['participants.user', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at');

        if (! empty(trim($this->search))) {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term, $userId) {
                $q->where('subject', 'like', $term)
                    ->orWhereHas('participants.user', function ($sub) use ($term, $userId) {
                        $sub->where('id', '!=', $userId)
                            ->where(function ($u) use ($term) {
                                $u->where('name', 'like', $term)
                                    ->orWhere('surnames', 'like', $term)
                                    ->orWhere('email', 'like', $term);
                            });
                    });
            });
        }

        return $query->get();
    }

    public function getActiveConversationProperty(): ?Conversation
    {
        if (! $this->activeConversationId) {
            return null;
        }

        return Conversation::with([
            'participants.user',
            'messages.sender',
            'messages.media',
        ])->find($this->activeConversationId);
    }

    /**
     * Users available to start a conversation with.
     *
     * @return Collection<int, User>
     */
    public function getPotentialRecipientsProperty(): Collection
    {
        /** @var User $currentUser */
        $currentUser = auth()->user();

        $query = User::where('is_active', true)->where('id', '!=', $currentUser->id);

        if ($currentUser->isAgremiado()) {
            // Agremiado can contact leaders or admins
            $query->whereIn('role', [UserRole::Lider, UserRole::Admin]);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Users available formatted for the searchable recipient picker.
     *
     * @return array<int, array{id: int, name: string, full_name: string, email: string, role: string, role_label: string, category: ?string, avatar_url: ?string, initials: string}>
     */
    public function getRecipientsListProperty(): array
    {
        return $this->potentialRecipients->map(function (User $user) {
            $name = $user->name ?: '';
            $fullName = $user->full_name ?: $name;

            return [
                'id' => $user->id,
                'name' => $name,
                'full_name' => $fullName,
                'email' => $user->email ?? '',
                'role' => $user->role?->value ?? '',
                'role_label' => $user->role?->getLabel() ?? 'Usuario',
                'category' => $user->category,
                'avatar_url' => $user->getFilamentAvatarUrl(),
                'initials' => strtoupper(substr($name ?: 'U', 0, 2)),
            ];
        })->values()->all();
    }
}
