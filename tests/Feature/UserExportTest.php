<?php

use App\Enums\UserDocumentType;
use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function (): void {
    File::deleteDirectory(storage_path('app/private/exports'));
});

test('agremiado cannot access list users page or export actions', function (): void {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $this->actingAs($agremiado);

    $this->get('/portal/users')->assertForbidden();
});

test('export pdf validates minimum 1, maximum 5, and whitelisted columns', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->callAction('exportPdf', data: ['columns' => []])
        ->assertHasActionErrors(['columns']);

    Livewire::test(ListUsers::class)
        ->callAction('exportPdf', data: [
            'columns' => ['full_name', 'email', 'curp', 'nss', 'phone', 'category'],
        ])
        ->assertHasActionErrors(['columns']);

    Livewire::test(ListUsers::class)
        ->callAction('exportPdf', data: [
            'columns' => ['full_name', 'password'],
        ])
        ->assertHasActionErrors(['columns.1']);
});

test('admin and leader can export users pdf with up to 5 custom columns', function (UserRole $role): void {
    $actor = User::factory()->create([
        'role' => $role,
        'name' => 'Daniel',
        'surnames' => 'Alvarez',
        'curp' => 'AAGD850203HTCLRN09',
    ]);
    User::factory()->create([
        'role' => UserRole::Agremiado,
        'name' => 'Ramon',
        'surnames' => 'Gomez',
        'curp' => null,
        'category' => null,
    ]);

    $this->actingAs($actor);

    $component = Livewire::test(ListUsers::class)
        ->callAction('exportPdf', data: [
            'columns' => ['full_name', 'curp', 'category'],
        ])
        ->assertHasNoActionErrors()
        ->assertFileDownloaded('padron-usuarios-'.now()->format('Y-m-d').'.pdf');

    $download = $component->effects['download'] ?? null;
    expect($download)->not->toBeNull();
    $binary = base64_decode((string) $download['content'], true);
    expect($binary)->toStartWith('%PDF-');
})->with([
    'admin' => UserRole::Admin,
    'lider' => UserRole::Lider,
]);

test('admin can export users with documents as a valid zip archive', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'name' => 'Daniel',
        'surnames' => 'Alvarez',
        'curp' => 'AAGD850203HTCLRN09',
    ]);

    $memberWithDocs = User::factory()->create([
        'role' => UserRole::Agremiado,
        'name' => 'Jonas',
        'surnames' => 'Hernandez',
        'curp' => 'HEVJ890726HCSRLN02',
    ]);

    $memberNoCurpNoDocs = User::factory()->create([
        'role' => UserRole::Agremiado,
        'name' => 'Ramon',
        'surnames' => 'Gomez',
        'curp' => null,
    ]);

    $tmpIne = tempnam(sys_get_temp_dir(), 'ine_').'.pdf';
    $tmpIneBack = tempnam(sys_get_temp_dir(), 'ineb_').'.jpg';
    $tmpPay = tempnam(sys_get_temp_dir(), 'pay_').'.jpg';
    file_put_contents($tmpIne, "%PDF-1.4\n%fake-ine");
    file_put_contents($tmpIneBack, "\xFF\xD8\xFF\xE0fake-ine-back");
    file_put_contents($tmpPay, "\xFF\xD8\xFF\xE0fake-jpg");

    $memberWithDocs->addMedia($tmpIne)->toMediaCollection(UserDocumentType::Ine->value);
    $memberWithDocs->addMedia($tmpIneBack)->toMediaCollection(User::INE_BACK_COLLECTION);
    $memberWithDocs->addMedia($tmpPay)->toMediaCollection(UserDocumentType::ComprobantePago->value);

    $this->actingAs($admin);

    $component = Livewire::test(ListUsers::class)
        ->callAction('exportWithDocuments')
        ->assertHasNoActionErrors()
        ->assertFileDownloaded('padron-usuarios-documentos-'.now()->format('Y-m-d').'.zip');

    $download = $component->effects['download'] ?? null;
    expect($download)->not->toBeNull();

    $zipBinary = base64_decode((string) $download['content'], true);
    $inspectZipPath = tempnam(sys_get_temp_dir(), 'exp_').'.zip';
    file_put_contents($inspectZipPath, $zipBinary);

    $zip = new ZipArchive;
    expect($zip->open($inspectZipPath))->toBeTrue();

    $csv = $zip->getFromName('padron-usuarios.csv');
    expect($csv)->not->toBeFalse();
    expect($csv)->toContain('HEVJ890726HCSRLN02');
    expect($csv)->toContain($memberNoCurpNoDocs->email);

    expect($zip->getFromName('HEVJ890726HCSRLN02/ine.pdf'))->toBe("%PDF-1.4\n%fake-ine");
    expect($zip->getFromName('HEVJ890726HCSRLN02/ine_reverso.jpg'))->toBe("\xFF\xD8\xFF\xE0fake-ine-back");
    expect($zip->getFromName('HEVJ890726HCSRLN02/comprobante_pago.jpg'))->toBe("\xFF\xD8\xFF\xE0fake-jpg");

    $zip->close();
    @unlink($inspectZipPath);
});
