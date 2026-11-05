<?php

use App\Http\Controllers\LoanApplicationController;
use App\Http\Controllers\LoanSimulatorController;
use App\Http\Controllers\MessageAttachmentController;
use App\Http\Controllers\PublicPetitionController;
use App\Http\Controllers\TransparencyDocumentController;
use App\Http\Controllers\UserDocumentController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/landing.php';

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/portal/messages/attachments/{media}', [MessageAttachmentController::class, 'download'])
        ->name('portal.messages.attachment.download');

    Route::get('/portal/users/{user}/documents/{type}', [UserDocumentController::class, 'show'])
        ->name('portal.users.documents.show');

    Route::get('/portal/transparency-documents/{document}', [TransparencyDocumentController::class, 'show'])
        ->name('portal.transparency.documents.show');

    // Módulo de Préstamos - Solicitud y Documentos
    Route::get('/portal/solicitud-prestamo', [LoanApplicationController::class, 'create'])
        ->name('loans.apply');

    Route::post('/api/prestamos', [LoanApplicationController::class, 'store'])
        ->name('loans.store');

    Route::post('/api/prestamos/evidencias', [LoanApplicationController::class, 'uploadEvidence'])
        ->name('loans.evidences.upload');

    Route::post('/api/prestamos/{loan}/firmar', [LoanApplicationController::class, 'sign'])
        ->name('loans.sign');

    Route::post('/api/prestamos/{loan}/enviar', [LoanApplicationController::class, 'submit'])
        ->name('loans.submit');

    Route::get('/portal/prestamos/{loan}/documentos/{document}', [LoanApplicationController::class, 'downloadDocument'])
        ->name('loans.documents.download');

    Route::get('/portal/prestamos/{loan}/evidencias/{evidence}', [LoanApplicationController::class, 'downloadEvidence'])
        ->name('loans.evidences.download');
});

Route::post('/api/petitions/{annualPetition}/submit', [PublicPetitionController::class, 'store'])
    ->name('public.petitions.store');

Route::get('/petitions/voucher/{annual_petition}/{curp}', [PublicPetitionController::class, 'downloadVoucher'])
    ->where('annual_petition', '[0-9]{4}')
    ->where('curp', '[A-Za-z0-9]{18}')
    ->name('public.petitions.voucher');

Route::get('/pliegos-anuales/convocatorias/{media}', [PublicPetitionController::class, 'downloadConvocationAttachment'])
    ->name('public.petitions.convocation.download');

Route::post('/api/simulador-prestamo/simulate', [LoanSimulatorController::class, 'simulate'])
    ->name('public.simulator.simulate');

Route::get('/api/simulador-prestamo/download/{token}', [LoanSimulatorController::class, 'download'])
    ->name('public.simulator.download');

/**
 * Ruta para servir archivos de almacenamiento público (/storage/...) en servidores
 * compartidos (como Neubox / cPanel) donde no se pueden crear enlaces simbólicos físicos.
 * Si en el servidor web existe un enlace simbólico físico (Apache/Nginx), el servidor web
 * despacha el archivo directamente sin ejecutar PHP.
 */
Route::get('/storage/{path}', function (string $path) {
    $cleanPath = str_replace(['..', "\0"], '', $path);
    $cleanPath = ltrim($cleanPath, '/\\');

    if ($cleanPath === '' || ! Storage::disk('public')->exists($cleanPath)) {
        abort(404);
    }

    $fullPath = Storage::disk('public')->path($cleanPath);

    return response()->file($fullPath, [
        'Cache-Control' => 'public, max-age=86400, must-revalidate',
    ]);
})->where('path', '.*')->name('public.storage');

Route::fallback(function () {
    return response()->view('errors.404-landing', [], 404);
});
