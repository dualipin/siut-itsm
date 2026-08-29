<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class Profile extends BaseEditProfile
{
    protected static ?string $title = 'Mi Perfil';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Fotografía y Datos Personales')
                    ->description('Actualiza tu información personal, datos de contacto y compensación.')
                    ->icon(Heroicon::User)
                    ->schema([
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
                            })
                            ->columnSpanFull(),
                        Grid::make(2)
                            ->schema([
                                $this->getNameFormComponent(),
                                TextInput::make('surnames')
                                    ->label('Apellidos')
                                    ->required()
                                    ->maxLength(255),
                                $this->getEmailFormComponent(),
                                TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->maxLength(20),
                                DatePicker::make('birth_date')
                                    ->label('Fecha de Nacimiento')
                                    ->maxDate(now()->subYears(18)),
                                TextInput::make('salary')
                                    ->label('Salario')
                                    ->numeric()
                                    ->prefix('$'),
                            ]),
                        Textarea::make('address')
                            ->label('Dirección')
                            ->rows(3)
                            ->cols(50)
                            ->columnSpanFull(),
                    ]),

                Section::make('Información Laboral e Identificación')
                    ->description('Datos de registro institucional y laboral (solo lectura).')
                    ->icon(Heroicon::Identification)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('curp_display')
                                    ->label('CURP')
                                    ->state(fn (?Model $record): string => $record instanceof User && filled($record->curp) ? $record->curp : 'No registrado'),
                                TextEntry::make('role_display')
                                    ->label('Rol en el Sistema')
                                    ->state(fn (?Model $record): string => $record instanceof User && $record->role ? ($record->role->getLabel() ?? 'Sin rol') : 'Sin rol asignado'),
                                TextEntry::make('category_display')
                                    ->label('Categoría')
                                    ->state(fn (?Model $record): string => $record instanceof User && filled($record->category) ? $record->category : 'Sin categoría asignada'),
                                TextEntry::make('nss_display')
                                    ->label('Número de Seguridad Social (NSS)')
                                    ->state(fn (?Model $record): string => $record instanceof User && filled($record->nss) ? $record->nss : 'No registrado'),
                                TextEntry::make('hiring_date_display')
                                    ->label('Fecha de Contratación')
                                    ->state(fn (?Model $record): string => $record instanceof User && $record->hiring_date ? $record->hiring_date->format('d/m/Y') : 'No registrada'),
                                TextEntry::make('status_display')
                                    ->label('Estado de Cuenta')
                                    ->state(fn (?Model $record): string => $record instanceof User ? ($record->is_active ? 'Activo' : 'Inactivo') : '-'),
                            ]),
                    ]),

                Section::make('Seguridad')
                    ->description('Modifica tu contraseña de acceso si lo requieres.')
                    ->icon(Heroicon::Key)
                    ->schema([
                        $this->getCurrentPasswordFormComponent(),
                        Grid::make(2)
                            ->schema([
                                $this->getPasswordFormComponent(),
                                $this->getPasswordConfirmationFormComponent(),
                            ]),
                    ]),
            ]);
    }
}
