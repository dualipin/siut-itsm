<?php

namespace App\Filament\Pages\Auth;

use App\Enums\UserRole;
use App\Models\User;
use DiogoGPinto\AuthUIEnhancer\Pages\Auth\AuthUiEnhancerRegister;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use SensitiveParameter;

class Register extends AuthUiEnhancerRegister
{
    public function getLayout(): string
    {
        return 'filament.pages.auth.register-layout';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Registro de Agremiado - SIUT';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Crear Cuenta de Agremiado';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Completa tus datos personales para solicitar tu alta oficial en el padrón sindical.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos Personales y de Contacto')
                    ->description('Información básica de identificación requerida para tu expediente sindical.')
                    ->icon(Heroicon::UserCircle)
                    ->compact()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                $this->getNameFormComponent(),
                                TextInput::make('surnames')
                                    ->label('Apellidos')
                                    ->placeholder('Primer y segundo apellido')
                                    ->prefixIcon('heroicon-m-user-group')
                                    ->required()
                                    ->maxLength(255),
                                $this->getEmailFormComponent(),
                                TextInput::make('phone')
                                    ->label('Teléfono Móvil')
                                    ->placeholder('10 dígitos, ej. 9931234567')
                                    ->prefixIcon('heroicon-m-phone')
                                    ->tel()
                                    ->maxLength(20),
                                DatePicker::make('birth_date')
                                    ->label('Fecha de Nacimiento')
                                    ->prefixIcon('heroicon-m-calendar-days')
                                    ->required()
                                    ->maxDate(now()->subYears(18))
                                    ->displayFormat('d/m/Y'),
                                TextInput::make('curp')
                                    ->label('CURP')
                                    ->placeholder('18 caracteres alfanuméricos')
                                    ->prefixIcon('heroicon-m-identification')
                                    ->maxLength(18)
                                    ->unique($this->getUserModel(), 'curp')
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase;', 'maxlength' => 18])
                                    ->dehydrateStateUsing(fn ($state) => filled($state) ? strtoupper(trim($state)) : null),
                                TextInput::make('nss')
                                    ->label('Número de Seguridad Social (NSS)')
                                    ->placeholder('11 dígitos numéricos')
                                    ->prefixIcon('heroicon-m-shield-check')
                                    ->maxLength(20)
                                    ->unique($this->getUserModel(), 'nss'),
                                FileUpload::make('photo_path')
                                    ->label('Fotografía de Perfil')
                                    ->avatar()
                                    ->required()
                                    ->disk('public')
                                    ->visibility('public')
                                    ->directory('profile-photos')
                                    ->image()
                                    ->maxSize(2048)
                                    ->validationMessages([
                                        'required' => 'La fotografía de perfil es obligatoria para tu credencial digital.',
                                    ])
                                    ->helperText('Obligatoria. Sube una foto de frente con fondo claro para tu credencial sindical.'),
                            ]),
                        Textarea::make('address')
                            ->label('Dirección / Domicilio Particular')
                            ->placeholder('Calle, número exterior/interior, colonia, municipio y código postal')
                            ->rows(2)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),

                Section::make('Seguridad de la Cuenta')
                    ->description('Crea una contraseña segura para acceder posteriormente a tu portal.')
                    ->icon(Heroicon::Key)
                    ->compact()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                $this->getPasswordFormComponent(),
                                $this->getPasswordConfirmationFormComponent(),
                            ]),
                    ]),
            ]);
    }

    protected function getNameFormComponent(): Component
    {
        return TextInput::make('name')
            ->label('Nombre(s)')
            ->placeholder('Tu(s) nombre(s)')
            ->prefixIcon('heroicon-m-user')
            ->required()
            ->maxLength(255)
            ->autofocus();
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Correo Electrónico')
            ->placeholder('ejemplo@correo.com')
            ->prefixIcon('heroicon-m-envelope')
            ->email()
            ->required()
            ->maxLength(255)
            ->unique($this->getUserModel(), 'email');
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Contraseña')
            ->placeholder('Mínimo 8 caracteres')
            ->prefixIcon('heroicon-m-lock-closed')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->rule(Password::default())
            ->showAllValidationMessages()
            ->dehydrateStateUsing(fn (#[SensitiveParameter] $state) => Hash::make($state))
            ->same('passwordConfirmation');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label('Confirmar Contraseña')
            ->placeholder('Repite tu contraseña')
            ->prefixIcon('heroicon-m-lock-closed')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->dehydrated(false);
    }

    public function getRegisterFormAction(): Action
    {
        return Action::make('register')
            ->label('Crear Cuenta de Agremiado')
            ->icon('heroicon-m-arrow-right-circle')
            ->size('lg')
            ->submit('register');
    }

    public function loginAction(): Action
    {
        return Action::make('login')
            ->link()
            ->label('¿Ya tienes una cuenta registrada? Inicia sesión aquí')
            ->icon('heroicon-m-arrow-left')
            ->url(filament()->getLoginUrl());
    }

    /**
     * Handle registration ensuring the user strictly receives the Agremiado role and is active.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        $data['role'] = UserRole::Agremiado;
        $data['is_active'] = true;

        /** @var User $user */
        $user = $this->getUserModel()::create($data);

        Notification::make()
            ->title('¡Bienvenido al portal SIUT!')
            ->body('Tu cuenta de Agremiado ha sido creada exitosamente. Por favor accede a "Mi Perfil" para subir tus 5 documentos requeridos (Afiliación, Comprobante de Domicilio, INE, Comprobante de Pago y CURP) para que sean validados.')
            ->info()
            ->sendToDatabase($user);

        return $user;
    }
}
