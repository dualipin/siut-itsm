<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\InquiryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing.home');
})->name('home');

Route::get('/about', function () {
    return view('landing.about');
})->name('about');

Route::get('/contact', function () {
    return view('landing.contact', [
        'syndicate' => config('syndicate'),
    ]);
})->name('contact');

Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

Route::get('/dudas', [InquiryController::class, 'index'])->name('inquiries.index');
Route::post('/dudas', [InquiryController::class, 'store'])->name('inquiries.store');
Route::get('/dudas/{slug}', [InquiryController::class, 'show'])->name('inquiries.show');
Route::get('/dudas/adjuntos/{media}', [InquiryController::class, 'downloadAttachment'])->name('inquiries.attachment.download');

Route::redirect('/sindicato/transparencia/dudas', '/dudas');
Route::redirect('/sindicato/transparencia/preguntas-frecuentes', '/dudas');
