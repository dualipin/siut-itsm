<?php

namespace App\Filament\Resources\ContactSubmissions\Tables;

use App\Enums\ContactSubmissionStatus;
use App\Mail\ContactSubmissionReplyMail;
use App\Models\ContactReply;
use App\Models\ContactSubmission;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;

class ContactSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Remitente')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable()
                    ->copyable()
                    ->icon(Heroicon::Envelope),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('subject')
                    ->label('Asunto')
                    ->searchable()
                    ->limit(35)
                    ->placeholder('Sin asunto'),
                TextColumn::make('created_at')
                    ->label('Recibido el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(ContactSubmissionStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('reply')
                    ->label('Responder')
                    ->icon(Heroicon::ArrowUturnLeft)
                    ->color('success')
                    ->schema([
                        Textarea::make('message')
                            ->label('Mensaje de respuesta')
                            ->helperText('Esta respuesta se enviará automáticamente al correo del remitente.')
                            ->required()
                            ->rows(6),
                    ])
                    ->action(function (ContactSubmission $record, array $data): void {
                        $reply = ContactReply::create([
                            'contact_submission_id' => $record->id,
                            'user_id' => auth()->id(),
                            'message' => $data['message'],
                        ]);

                        $record->update([
                            'status' => ContactSubmissionStatus::Replied,
                        ]);

                        try {
                            Mail::to($record->email)->queue(new ContactSubmissionReplyMail($record, $reply));

                            Notification::make()
                                ->title('Respuesta enviada')
                                ->body("Se ha enviado la respuesta a {$record->email} correctamente.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error al enviar correo')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('archive')
                    ->label('Archivar')
                    ->icon(Heroicon::ArchiveBox)
                    ->color('gray')
                    ->visible(fn (ContactSubmission $record): bool => $record->status !== ContactSubmissionStatus::Archived)
                    ->action(function (ContactSubmission $record): void {
                        $record->update(['status' => ContactSubmissionStatus::Archived]);

                        Notification::make()
                            ->title('Mensaje archivado')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
