<?php

namespace App\Filament\Pages;

use App\Enums\DocumentStatus;
use App\Enums\UserDocumentType;
use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class Profile extends BaseEditProfile
{
    protected static ?string $title = 'Mi Perfil';

    public function getHeading(): string|Htmlable
    {
        return 'Mi Perfil';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Administra tus datos personales, credenciales y expediente digital de agremiado.';
    }

    protected function getNameFormComponent(): Component
    {
        return parent::getNameFormComponent()
            ->label('Nombre(s)');
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->label('Correo Electrónico');
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return parent::getCurrentPasswordFormComponent()
            ->label('Contraseña Actual');
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label('Nueva Contraseña');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return parent::getPasswordConfirmationFormComponent()
            ->label('Confirmar Nueva Contraseña');
    }

    public function form(Schema $schema): Schema
    {
        $user = $this->getUser();

        return $schema
            ->model($user)
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
                                TextEntry::make('document_status_display')
                                    ->label('Estatus de Validación de Documentos')
                                    ->badge()
                                    ->state(fn (?Model $record): string => $record instanceof User ? $record->getOverallDocumentStatus()->getLabel() : '-')
                                    ->color(fn (?Model $record): string => $record instanceof User ? $record->getOverallDocumentStatus()->getColor() : 'gray'),
                            ]),
                    ]),

                Section::make('Documentos de Afiliación (PDF)')
                    ->description('Sube y gestiona tus 5 documentos obligatorios en PDF. Estos documentos son revisados y validados por la administración.')
                    ->icon(Heroicon::DocumentCheck)
                    ->schema([
                        $this->buildDocumentUploadField(UserDocumentType::Afiliacion, '1. Comprobante de Afiliación'),
                        $this->buildDocumentUploadField(UserDocumentType::ComprobanteDomicilio, '2. Comprobante de Domicilio'),
                        $this->buildDocumentUploadField(UserDocumentType::Ine, '3. INE / Identificación Oficial'),
                        $this->buildDocumentUploadField(UserDocumentType::ComprobantePago, '4. Comprobante de Pago'),
                        $this->buildDocumentUploadField(UserDocumentType::Curp, '5. CURP (Documento Oficial)'),
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

    /**
     * Build a standardized Spatie Media file upload component for an affiliation document.
     */
    protected function buildDocumentUploadField(UserDocumentType $type, string $label): SpatieMediaLibraryFileUpload
    {
        return SpatieMediaLibraryFileUpload::make($type->value)
            ->label($label)
            ->collection($type->value)
            ->acceptedFileTypes(['application/pdf'])
            ->maxSize(10240)
            ->downloadable()
            ->openable()
            ->disabled(fn (?Model $record): bool => $record instanceof User && $record->getDocumentStatus($type) === DocumentStatus::Valid)
            ->hint(function (?Model $record) use ($type): ?string {
                if (! $record instanceof User) {
                    return null;
                }

                $status = $record->getDocumentStatus($type);
                if ($status === DocumentStatus::Valid) {
                    return '✓ Validado y Aprobado';
                }
                if ($status === DocumentStatus::Invalid) {
                    return '⚠ Inválido - Requiere corrección';
                }
                if ($status === DocumentStatus::Pending) {
                    return '⏳ Pendiente de revisión';
                }

                return 'Pendiente de subir';
            })
            ->hintColor(function (?Model $record) use ($type): string {
                if (! $record instanceof User) {
                    return 'gray';
                }

                $status = $record->getDocumentStatus($type);
                if ($status === DocumentStatus::Valid) {
                    return 'success';
                }
                if ($status === DocumentStatus::Invalid) {
                    return 'danger';
                }
                if ($status === DocumentStatus::Pending) {
                    return 'warning';
                }

                return 'gray';
            })
            ->helperText(function (?Model $record) use ($type): ?string {
                if (! $record instanceof User) {
                    return null;
                }

                $status = $record->getDocumentStatus($type);
                if ($status === DocumentStatus::Valid) {
                    return 'Este documento ha sido validado satisfactoriamente por la administración. No requiere cambios.';
                }
                if ($status === DocumentStatus::Invalid) {
                    $reason = $record->getDocumentRejectionReason($type);

                    return 'Observación de la administración: "'.($reason ?: 'Documento no válido').'". Por favor selecciona un nuevo archivo en PDF para reemplazarlo.';
                }
                if ($status === DocumentStatus::Pending) {
                    return 'Documento en proceso de revisión por parte de la administración.';
                }

                return 'Documento pendiente. Sube un archivo PDF válido.';
            });
    }

    /**
     * Handle updating user profile, saving media relationships, and resetting status on replaced files.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record = parent::handleRecordUpdate($record, $data);
        $this->form->model($record)->saveRelationships();

        // Check if any media was uploaded or replaced
        $updatedAny = false;
        foreach (UserDocumentType::cases() as $type) {
            $media = $record->getFirstMedia($type->value);
            if ($media) {
                $status = $media->getCustomProperty('status');
                // If it's freshly uploaded without status or was invalid and now replaced
                if (! $status || $status === DocumentStatus::Invalid->value) {
                    $media->setCustomProperty('status', DocumentStatus::Pending->value);
                    $media->forgetCustomProperty('rejection_reason');
                    $media->save();
                    $updatedAny = true;
                }
            }
        }

        if ($updatedAny) {
            Notification::make()
                ->title('Documentación actualizada')
                ->body('Tus documentos han sido actualizados y enviados a revisión por parte de la administración.')
                ->info()
                ->send();
        }

        return $record;
    }
}
