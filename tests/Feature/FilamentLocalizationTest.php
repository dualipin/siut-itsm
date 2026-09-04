<?php

use App\Filament\Pages\Profile;
use App\Filament\Resources\ContactSubmissions\ContactSubmissionResource;
use App\Filament\Resources\FinancialReports\FinancialReportResource;
use App\Filament\Resources\FinancialReports\Pages\CreateFinancialReport;
use App\Filament\Resources\FinancialReports\Pages\EditFinancialReport;
use App\Filament\Resources\FinancialReports\Pages\ListFinancialReports;
use App\Filament\Resources\FinancialReports\Pages\ViewFinancialReport;
use App\Filament\Resources\Inquiries\InquiryResource;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Themes\Pages\EditTheme;
use App\Filament\Resources\Themes\Pages\ListThemes;
use App\Filament\Resources\Themes\ThemeResource;
use App\Filament\Resources\TransparencyRecords\Pages\CreateTransparencyRecord;
use App\Filament\Resources\TransparencyRecords\Pages\EditTransparencyRecord;
use App\Filament\Resources\TransparencyRecords\Pages\ListTransparencyRecords;
use App\Filament\Resources\TransparencyRecords\Pages\ViewTransparencyRecord;
use App\Filament\Resources\TransparencyRecords\TransparencyRecordResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;

test('application locale is configured to spanish', function () {
    expect(config('app.locale'))->toBe('es')
        ->and(config('app.fallback_locale'))->toBe('es')
        ->and(config('app.faker_locale'))->toBe('es_MX');
});

test('filament resources have spanish model and navigation labels', function () {
    expect(PostResource::getModelLabel())->toBe('Publicación')
        ->and(PostResource::getPluralModelLabel())->toBe('publicaciones')
        ->and(PostResource::getNavigationLabel())->toBe('Publicaciones')
        ->and(PostResource::getBreadcrumb())->toBe('Publicaciones');

    expect(FinancialReportResource::getModelLabel())->toBe('Reporte Financiero')
        ->and(FinancialReportResource::getPluralModelLabel())->toBe('reportes financieros')
        ->and(FinancialReportResource::getNavigationLabel())->toBe('Reportes Financieros')
        ->and(FinancialReportResource::getBreadcrumb())->toBe('Reportes Financieros');

    expect(TransparencyRecordResource::getModelLabel())->toBe('Registro de Transparencia')
        ->and(TransparencyRecordResource::getPluralModelLabel())->toBe('registros de transparencia')
        ->and(TransparencyRecordResource::getNavigationLabel())->toBe('Transparencia')
        ->and(TransparencyRecordResource::getBreadcrumb())->toBe('Transparencia');

    expect(UserResource::getModelLabel())->toBe('Usuario')
        ->and(UserResource::getPluralModelLabel())->toBe('usuarios')
        ->and(UserResource::getNavigationLabel())->toBe('Usuarios')
        ->and(UserResource::getBreadcrumb())->toBe('Usuarios');

    expect(ContactSubmissionResource::getModelLabel())->toBe('Mensaje de Contacto')
        ->and(ContactSubmissionResource::getPluralModelLabel())->toBe('mensajes de contacto')
        ->and(ContactSubmissionResource::getNavigationLabel())->toBe('Buzón de Contacto')
        ->and(ContactSubmissionResource::getBreadcrumb())->toBe('Contacto');

    expect(InquiryResource::getModelLabel())->toBe('Duda / Consulta')
        ->and(InquiryResource::getPluralModelLabel())->toBe('dudas y consultas')
        ->and(InquiryResource::getNavigationLabel())->toBe('Dudas y Consultas')
        ->and(InquiryResource::getBreadcrumb())->toBe('Dudas');

    expect(ThemeResource::getModelLabel())->toBe('Configuración de Apariencia')
        ->and(ThemeResource::getPluralModelLabel())->toBe('configuraciones de apariencia')
        ->and(ThemeResource::getNavigationLabel())->toBe('Apariencia')
        ->and(ThemeResource::getBreadcrumb())->toBe('Apariencia');
});

test('filament pages have custom spanish titles', function () {
    $getPageTitle = fn (string $class): ?string => (new ReflectionClass($class))->getStaticPropertyValue('title');

    expect($getPageTitle(ListPosts::class))->toBe('Publicaciones')
        ->and($getPageTitle(CreatePost::class))->toBe('Nueva Publicación')
        ->and($getPageTitle(EditPost::class))->toBe('Editar Publicación');

    expect($getPageTitle(ListFinancialReports::class))->toBe('Reportes Financieros')
        ->and($getPageTitle(CreateFinancialReport::class))->toBe('Nuevo Reporte Financiero')
        ->and($getPageTitle(EditFinancialReport::class))->toBe('Editar Reporte Financiero')
        ->and($getPageTitle(ViewFinancialReport::class))->toBe('Detalle del Reporte Financiero');

    expect($getPageTitle(ListTransparencyRecords::class))->toBe('Registros de Transparencia')
        ->and($getPageTitle(CreateTransparencyRecord::class))->toBe('Nuevo Registro de Transparencia')
        ->and($getPageTitle(EditTransparencyRecord::class))->toBe('Editar Registro de Transparencia')
        ->and($getPageTitle(ViewTransparencyRecord::class))->toBe('Detalle del Registro de Transparencia');

    expect($getPageTitle(ListUsers::class))->toBe('Padrón de Usuarios')
        ->and($getPageTitle(CreateUser::class))->toBe('Nuevo Usuario')
        ->and($getPageTitle(EditUser::class))->toBe('Editar Usuario');

    expect($getPageTitle(ListThemes::class))->toBe('Apariencia')
        ->and($getPageTitle(EditTheme::class))->toBe('Editar Apariencia');

    expect($getPageTitle(Profile::class))->toBe('Mi Perfil');
});

test('validation messages and attributes resolve in spanish', function () {
    expect(__('validation.required', ['attribute' => __('validation.attributes.title')]))
        ->toBe('El campo título es obligatorio.')
        ->and(__('validation.required', ['attribute' => __('validation.attributes.content')]))
        ->toBe('El campo contenido es obligatorio.')
        ->and(__('validation.required', ['attribute' => __('validation.attributes.email')]))
        ->toBe('El campo correo electrónico es obligatorio.')
        ->and(__('validation.required', ['attribute' => __('validation.attributes.password')]))
        ->toBe('El campo contraseña es obligatorio.');
});
