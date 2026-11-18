<?php

namespace App\Filament\Pages\Auth;

use DiogoGPinto\AuthUIEnhancer\Pages\Auth\AuthUiEnhancerLogin;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Override;
use SensitiveParameter;

class Login extends AuthUiEnhancerLogin
{
    /**
     * The "email" slot of the parent form is replaced by the CURP field.
     */
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('curp')
            ->label('CURP')
            ->placeholder('18 caracteres')
            ->prefixIcon('heroicon-m-identification')
            ->required()
            ->maxLength(25)
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes([
                'style' => 'text-transform: uppercase;',
                'autocapitalize' => 'characters',
                'spellcheck' => 'false',
            ]);
    }

    #[Override]
    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Contraseña')
            ->placeholder('Ingresa tu contraseña')
            ->prefixIcon('heroicon-m-lock-closed')
            ->password()
            ->revealable()
            ->required()
            ->maxLength(18)
            ->autocomplete('current-password');
    }

    public function registerAction(): Action
    {
        return Action::make('register')
            ->link()
            ->label(__('filament-panels::auth/pages/login.actions.register.label'))
            ->url(route('register'));
    }

    public function homeAction(): Action
    {
        return Action::make('home')
            ->link()
            ->size('sm')
            ->color('gray')
            ->icon('heroicon-m-arrow-left')
            ->label('Volver al inicio')
            ->url(route('home'));
    }

    #[Override]
    public function content(Schema $schema): Schema
    {
        return parent::content($schema)
            ->components([
                ...parent::content($schema)->getComponents(withHidden: true),
                Actions::make([
                    $this->homeAction(),
                ])->alignment(Alignment::Center),
            ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return __('filament-panels::auth/pages/login.multi_factor.subheading');
        }

        return new HtmlString(__('filament-panels::auth/pages/login.actions.register.before').' '.$this->registerAction->toHtml());
    }

    /**
     * CURP is stored in uppercase, so the typed value is normalized (upper/lower case both work).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(
        #[SensitiveParameter] array $data,
    ): array {
        return [
            'curp' => mb_strtoupper(trim((string) $data['curp'])),
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.curp' => __(
                'filament-panels::auth/pages/login.messages.failed',
            ),
        ]);
    }
}
