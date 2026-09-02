<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\FinancialReportController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\PublicTransparencyController;
use Illuminate\Http\Request;
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

// Informes Financieros
Route::get('/transparencia/informes-financieros', [FinancialReportController::class, 'index'])->name('financial-reports.index');
Route::redirect('/sindicato/transparencia/informes-financieros', '/transparencia/informes-financieros');

Route::get('/transparencia/normativos', function () {
    return view('landing.transparency.normatives');
});
Route::redirect('sindicato/transparencia/normativos', '/transparencia/normativos');

// Registros de Transparencia por Tipo
Route::get('/transparencia/{type}', [PublicTransparencyController::class, 'index'])
    ->whereIn('type', ['financiero', 'normativo', 'convenio', 'acta', 'otro'])
    ->name('transparency.type');

Route::get('/transparencia/documentos/{document}/descargar', [PublicTransparencyController::class, 'download'])
    ->name('transparency.documents.download');

// Publicaciones (Posts)
Route::get('/publicaciones', [PublicationController::class, 'index'])->name('publications.index');
Route::get('/publicaciones/adjuntos/{media}', [PublicationController::class, 'downloadAttachment'])->name('publications.attachment.download');

// Named routes for each PostType
Route::get('/publicaciones/noticias', fn (Request $request) => app(PublicationController::class)->type($request, 'noticias'))->name('publications.noticias');
Route::get('/publicaciones/avisos', fn (Request $request) => app(PublicationController::class)->type($request, 'avisos'))->name('publications.avisos');
Route::get('/publicaciones/gestiones', fn (Request $request) => app(PublicationController::class)->type($request, 'gestiones'))->name('publications.gestiones');
Route::get('/publicaciones/contratos', fn (Request $request) => app(PublicationController::class)->type($request, 'contratos'))->name('publications.contratos');
Route::get('/publicaciones/formatos', fn (Request $request) => app(PublicationController::class)->type($request, 'formatos'))->name('publications.formatos');
Route::get('/publicaciones/acervo', fn (Request $request) => app(PublicationController::class)->type($request, 'acervo'))->name('publications.acervo');

// Dynamic routes
Route::get('/publicaciones/{type}', [PublicationController::class, 'type'])->name('publications.type');
Route::get('/publicaciones/{type}/{slug}', [PublicationController::class, 'show'])->name('publications.show');

// Legacy redirects
Route::redirect('/sindicato/recursos/formatos', '/publicaciones/formatos');
Route::redirect('/sindicato/recursos/biblioteca', '/publicaciones/acervo');
Route::redirect('/sindicato/repositorios/gestoria', '/publicaciones/gestiones');
Route::redirect('/sindicato/repositorios/gremiales', '/publicaciones/noticias');
Route::redirect('/sindicato/repositorios/tramites', '/publicaciones/formatos');
Route::redirect('/sindicato/repositorios/minutas', '/transparencia/acta');
