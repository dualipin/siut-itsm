<?php

namespace App\Filament\Resources\ContactSubmissions\Pages;

use App\Enums\ContactSubmissionStatus;
use App\Filament\Resources\ContactSubmissions\ContactSubmissionResource;
use App\Mail\ContactSubmissionReplyMail;
use App\Models\ContactReply;
use App\Models\ContactSubmission;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;

class ViewContactSubmission extends ViewRecord
{
    protected static string $resource = ContactSubmissionResource::class;

    protected static ?string $title = 'Detalle del Mensaje de Contacto';

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ContactSubmission $record */
        $record = $this->getRecord();
        $record->markAsRead();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label('Responder por Correo')
                ->icon(Heroicon::ArrowUturnLeft)
                ->color('success')
                ->schema([
                    Textarea::make('message')
                        ->label('Mensaje de respuesta')
                        ->helperText('Esta respuesta se enviará automáticamente al correo del remitente.')
                        ->required()
                        ->rows(6),
                ])
                ->action(function (array $data): void {
                    /** @var ContactSubmission $record */
                    $record = $this->getRecord();

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
                            ->title('Respuesta enviada exitosamente')
                            ->body("Se ha enviado la respuesta a {$record->email}.")
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
        ];
    }
}
