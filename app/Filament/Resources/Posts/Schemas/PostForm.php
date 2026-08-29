<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Enums\PostType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('author_id')
                    ->default(fn () => auth()->id()),
                Select::make('type')
                    ->label('Tipo de Publicación')
                    ->options(PostType::class)
                    ->default(PostType::Aviso)
                    ->required(),
                TextInput::make('title')
                    ->label('Título de la Publicación')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),
                TextInput::make('slug')
                    ->disabled()
                    ->required(),
                RichEditor::make('content')
                    ->required()
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('thumbnail')
                    ->label('Imagen destacada')
                    ->collection('thumbnail')
                    ->image()
                    ->downloadable()
                    ->openable()
                    ->required()
                    ->validationMessages([
                        'required' => 'La imagen destacada es obligatoria para guardar la publicación.',
                    ]),
                DatePicker::make('expires_at')
                    ->label('Fecha de expiración')
                    ->minDate(now())
                    ->helperText('La publicación expirará después de esta fecha.'),
                SpatieMediaLibraryFileUpload::make('attachments')
                    ->label('Archivos Adjuntos')
                    ->collection('attachments')
                    ->multiple()
                    ->reorderable()
                    ->downloadable()
                    ->openable()
                    ->columnSpanFull(),
            ]);
    }
}
