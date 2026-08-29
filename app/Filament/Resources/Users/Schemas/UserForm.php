<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),
                TextInput::make('surnames')
                    ->label('Apellidos')
                    ->required(),
                TextInput::make('email')
                    ->label('Correo Electrónico')
                    ->email()
                    ->required(),
                Select::make('role')
                    ->label('Rol de Usuario')
                    ->options(UserRole::class)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->required(),
                TextInput::make('curp')
                    ->label('CURP'),
                DatePicker::make('birth_date')
                    ->label('Fecha de Nacimiento')
                    ->required()
                    ->maxDate(now()->subYears(18)),
                TextInput::make('phone')
                    ->label('Teléfono')
                    ->tel(),
                Textarea::make('address')
                    ->label('Dirección')
                    ->columnSpanFull(),
                FileUpload::make('photo_path')
                    ->label('Foto de Perfil')
                    ->avatar()
                    ->disk('public')
                    ->visibility('public')
                    ->directory('profile-photos')
                    ->getUploadedFileUsing(static function (FileUpload $component, string $file, string|array|null $storedFileNames): ?array {
                        $data = $component->getUploadedFile($file, $storedFileNames);

                        if ($data && ! empty($data['url'])) {
                            $parsed = parse_url($data['url']);
                            $data['url'] = ($parsed['path'] ?? '').(isset($parsed['query']) ? '?'.$parsed['query'] : '');
                        }

                        return $data;
                    }),
                TextInput::make('category')
                    ->label('Categoría'),
                TextInput::make('nss')
                    ->label('Número de Seguridad Social'),
                TextInput::make('salary')
                    ->label('Salario')
                    ->numeric(),
                DatePicker::make('hiring_date')
                    ->label('Fecha de Contratación'),
                TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->minLength(8)
                    ->maxLength(255)
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state)),
            ]);
    }
}
