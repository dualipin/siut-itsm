<?php

namespace App\Filament\Resources\Inquiries\Schemas;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InquiryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalles de la Duda')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('title')
                                    ->label('Título de la Duda')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                Select::make('category')
                                    ->label('Categoría')
                                    ->options([
                                        'Trámites' => 'Trámites',
                                        'Escalafón' => 'Escalafón',
                                        'Prestaciones' => 'Prestaciones',
                                        'Cuotas y Finanzas' => 'Cuotas y Finanzas',
                                        'Afiliación' => 'Afiliación',
                                        'General' => 'General',
                                    ])
                                    ->searchable()
                                    ->createOptionUsing(fn (string $value) => $value),
                            ]),

                        Textarea::make('body')
                            ->label('Descripción Detallada')
                            ->required()
                            ->rows(6)
                            ->columnSpanFull(),
                    ]),

                Section::make('Configuración y Visibilidad')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_public')
                            ->label('Visible en la Sección Pública')
                            ->helperText('Activa esta opción para que la duda y sus respuestas oficiales sean visibles para todos en la web.')
                            ->default(false),
                        Select::make('status')
                            ->label('Estado')
                            ->options(InquiryStatus::class)
                            ->default(InquiryStatus::Pending)
                            ->required(),
                    ]),

                Section::make('Información del Autor')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        Placeholder::make('author_info')
                            ->label('Autor')
                            ->content(fn (Inquiry $record): string => $record->user
                                ? "{$record->user->name} ({$record->user->role?->getLabel()}) - {$record->user->email}"
                                : ($record->guest_name ?: 'Visitante anónimo')),
                        Placeholder::make('guest_email_info')
                            ->label('Correo de Contacto')
                            ->content(fn (Inquiry $record): string => $record->user?->email ?? ($record->guest_email ?: 'No proporcionado')),
                    ]),
            ]);
    }
}
