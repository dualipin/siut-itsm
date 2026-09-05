<?php

use App\Http\Controllers\MessageAttachmentController;
use App\Http\Controllers\TransparencyDocumentController;
use App\Http\Controllers\UserDocumentController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/landing.php';

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/portal/messages/attachments/{media}', [MessageAttachmentController::class, 'download'])
        ->name('portal.messages.attachment.download');

    Route::get('/portal/users/{user}/documents/{type}', [UserDocumentController::class, 'show'])
        ->name('portal.users.documents.show');

    Route::get('/portal/transparency-documents/{document}', [TransparencyDocumentController::class, 'show'])
        ->name('portal.transparency.documents.show');
});
