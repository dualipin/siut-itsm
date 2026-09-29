<?php

namespace App\Filament\Resources\UserRequests\Pages;

use App\Enums\RequestStatus;
use App\Filament\Resources\UserRequests\UserRequestResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUserRequest extends CreateRecord
{
    protected static string $resource = UserRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var User */
        $user = auth()->user();
        if ($user && $user->isAgremiado()) {
            $data['user_id'] = $user->id;
        } elseif (! isset($data['user_id'])) {
            $data['user_id'] = $user->id;
        }

        if (! isset($data['status'])) {
            $data['status'] = RequestStatus::Pending->value;
        }

        return $data;
    }
}
