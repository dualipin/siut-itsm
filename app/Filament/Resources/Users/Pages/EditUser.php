<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\AuditService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Editar Usuario';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var User $record */
        $record = $this->record;

        if ($record->id === auth()->id()) {
            $data['role'] = $record->role;
            $data['is_active'] = $record->is_active;
        }

        // The system must never be left without at least one active Admin or Leader
        if ($record->is_active && array_key_exists('is_active', $data) && ! $data['is_active'] && $record->isLeaderOrAdmin()) {
            $remainingActive = User::whereIn('role', [UserRole::Admin, UserRole::Lider])
                ->where('is_active', true)
                ->where('id', '!=', $record->id)
                ->count();

            if ($remainingActive === 0) {
                Notification::make()
                    ->title('Operación no permitida')
                    ->body('El sistema no puede quedarse sin al menos un Administrador o Líder activo.')
                    ->danger()
                    ->send();

                $this->halt();
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var User $record */
        $record = $this->record;

        if ($record->wasChanged('role')) {
            AuditService::log('change_role', auth()->id(), $record->id, [
                'old_role' => $record->getOriginal('role') instanceof UserRole ? $record->getOriginal('role')->value : (string) $record->getOriginal('role'),
                'new_role' => $record->role instanceof UserRole ? $record->role->value : (string) $record->role,
            ]);
        }

        if ($record->wasChanged('is_active')) {
            AuditService::log('toggle_active', auth()->id(), $record->id, [
                'old_active' => (bool) $record->getOriginal('is_active'),
                'new_active' => (bool) $record->is_active,
            ]);
        }

        if ($record->wasChanged('password')) {
            AuditService::log('reset_password', auth()->id(), $record->id);
        }
    }
}
