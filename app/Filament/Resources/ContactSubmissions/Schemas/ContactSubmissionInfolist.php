<?php

namespace App\Filament\Resources\ContactSubmissions\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ContactSubmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Remitente')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nombre'),
                        TextEntry::make('email')
                            ->label('Correo Electrónico')
                            ->copyable()
                            ->icon(Heroicon::Envelope),
                        TextEntry::make('phone')
                            ->label('Teléfono')
                            ->placeholder('No proporcionado'),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge(),
                        TextEntry::make('ip_address')
                            ->label('Dirección IP')
                            ->placeholder('No registrada'),
                        TextEntry::make('created_at')
                            ->label('Fecha de Recepción')
                            ->dateTime('d/m/Y H:i'),
                    ]),

                Section::make('Contenido del Mensaje')
                    ->schema([
                        TextEntry::make('subject')
                            ->label('Asunto')
                            ->placeholder('Sin asunto especificado'),
                        TextEntry::make('message')
                            ->label('Mensaje')
                            ->prose(),
                    ]),

                Section::make('Historial de Respuestas Enviadas')
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('replies')
                            ->label('Respuestas')
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('Respondido por'),
                                TextEntry::make('created_at')
                                    ->label('Fecha y Hora')
                                    ->dateTime('d/m/Y H:i'),
                                TextEntry::make('message')
                                    ->label('Mensaje enviado')
                                    ->columnSpanFull()
                                    ->prose(),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }
}
