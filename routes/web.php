<?php

use App\Http\Controllers\MessageAttachmentController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/landing.php';

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/portal/messages/attachments/{media}', [MessageAttachmentController::class, 'download'])
        ->name('portal.messages.attachment.download');
});
