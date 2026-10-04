<?php

use App\Http\Controllers\MessageAttachmentController;
use App\Http\Controllers\PublicPetitionController;
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

Route::post('/api/petitions/{annualPetition}/submit', [PublicPetitionController::class, 'store'])
    ->name('public.petitions.store');

Route::get('/petitions/voucher/{annual_petition}/{curp}', [PublicPetitionController::class, 'downloadVoucher'])
    ->where('annual_petition', '[0-9]{4}')
    ->where('curp', '[A-Za-z0-9]{18}')
    ->name('public.petitions.voucher');

Route::get('/pliegos-anuales/convocatorias/{media}', [PublicPetitionController::class, 'downloadConvocationAttachment'])
    ->name('public.petitions.convocation.download');

Route::fallback(function () {
    return response()->view('errors.404-landing', [], 404);
});
