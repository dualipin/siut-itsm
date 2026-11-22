<?php

use App\Enums\DocumentStatus;
use App\Enums\UserDocumentType;
use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ImportUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

afterEach(function (): void {
    File::deleteDirectory(storage_path('app/private/imports'));
});

test('guests and agremiados cannot access bulk user import endpoints or page', function (): void {
    $this->postJson(route('portal.users.import.chunk'), [])->assertUnauthorized();
    $this->postJson(route('portal.users.import.process'), [])->assertUnauthorized();
    $this->deleteJson(route('portal.users.import.cleanup', 'batch_1'))->assertUnauthorized();

    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $this->actingAs($agremiado);

    $this->postJson(route('portal.users.import.chunk'), [
        'batch_id' => 'batch_1',
        'file_id' => 'file_0',
        'chunk_index' => 0,
        'total_chunks' => 1,
        'original_name' => 'padron.csv',
        'chunk' => UploadedFile::fake()->create('padron.csv', 1),
    ])->assertForbidden();

    $this->postJson(route('portal.users.import.process'), [
        'batch_id' => 'batch_1',
        'offset' => 0,
        'limit' => 5,
        'fallback_password' => 'Siut2026!',
    ])->assertForbidden();

    $this->deleteJson(route('portal.users.import.cleanup', 'batch_1'))->assertForbidden();

    expect(ImportUsers::canAccess())->toBeFalse();
});

test('validation fails when chunk or process payload is invalid', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $this->postJson(route('portal.users.import.chunk'), [
        'batch_id' => 'invalid batch!',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['batch_id', 'file_id', 'chunk_index', 'total_chunks', 'original_name', 'chunk']);

    $this->postJson(route('portal.users.import.process'), [
        'batch_id' => 'batch_1',
        'offset' => -1,
        'limit' => 100,
        'fallback_password' => '123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['offset', 'limit', 'fallback_password']);
});

test('admin can upload csv and zip in chunks, upsert users, and attach valid documents', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'curp' => 'AAGD850203HTCLRN09',
        'email' => 'idaniel.alvarez@hotmail.com',
        'password' => 'existing-secret',
    ]);
    $this->actingAs($admin);

    expect(ImportUsers::canAccess())->toBeTrue();

    $batchId = 'test_batch_01';

    $csvContent = implode("\n", [
        'nombre,apellidos,direccion,telefono,email,categoria,departamento,nss,curp,fecha_nacimiento,fecha_ingreso,rol',
        'Jonas,Hernández Velasco,"Calle 1",9321025111,joher_89@msn.com,Docente,ING,83128916109,HEVJ890726HCSRLN02,26/07/1989 00:00,16/01/2014 00:00,agremiado',
        'Rubí Fabiola,Falcon Moreno,,9361050891,,Jefa de División,Subdirección,,FAMR810127MTCLRB01,27/01/1981 00:00,16/01/2008 00:00,agremiado',
        'Ramón,Gomez Hernández,"C. Matamoros",9934031361,,,,,,,,agremiado',
        'Daniel,Álvarez García,"R/a Limon",9935900293,idaniel.alvarez@hotmail.com,Docente,Sub,83078517105,AAGD850203HTCLRN09,03/02/1985 00:00,03/02/2009 00:00,administrador',
    ]);

    // Upload CSV split into 2 chunks
    $mid = (int) floor(strlen($csvContent) / 2);
    $part1 = UploadedFile::fake()->createWithContent('part0', substr($csvContent, 0, $mid));
    $part2 = UploadedFile::fake()->createWithContent('part1', substr($csvContent, $mid));

    $this->postJson(route('portal.users.import.chunk'), [
        'batch_id' => $batchId,
        'file_id' => 'csv_0',
        'chunk_index' => 0,
        'total_chunks' => 2,
        'original_name' => 'padron.csv',
        'chunk' => $part1,
    ])->assertOk()->assertJson(['completed' => false]);

    $this->postJson(route('portal.users.import.chunk'), [
        'batch_id' => $batchId,
        'file_id' => 'csv_0',
        'chunk_index' => 1,
        'total_chunks' => 2,
        'original_name' => 'padron.csv',
        'chunk' => $part2,
    ])->assertOk()->assertJson(['completed' => true, 'total_rows' => 4]);

    unset($part1, $part2);

    // Build a real ZIP with INE and comprobante_pago for Jonas
    $zipPath = sys_get_temp_dir().'/test_docs_'.uniqid().'.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('HEVJ890726HCSRLN02/ine.pdf', "%PDF-1.4\n%fake-ine");
    $zip->addFromString('HEVJ890726HCSRLN02/comprobante_pago.pdf', "%PDF-1.4\n%fake-recibo");
    $zip->close();

    $zipUpload = new UploadedFile($zipPath, 'docs_1.zip', 'application/zip', null, true);

    $this->postJson(route('portal.users.import.chunk'), [
        'batch_id' => $batchId,
        'file_id' => 'zip_0',
        'chunk_index' => 0,
        'total_chunks' => 1,
        'original_name' => 'docs_1.zip',
        'chunk' => $zipUpload,
    ])->assertOk()->assertJson(['completed' => true, 'extracted_files' => 2]);

    @unlink($zipPath);

    // Process batch
    $response = $this->postJson(route('portal.users.import.process'), [
        'batch_id' => $batchId,
        'offset' => 0,
        'limit' => 5,
        'fallback_password' => 'Siut2026!',
    ])->assertOk();

    $response->assertJson([
        'processed' => 4,
        'created' => 3,
        'updated' => 1,
        'docs_attached' => 2,
        'errors' => [],
        'total' => 4,
    ]);

    // Verify Jonas (new user with CURP, password = CURP, and 2 valid documents)
    $jonas = User::where('curp', 'HEVJ890726HCSRLN02')->firstOrFail();
    expect($jonas->email)->toBe('joher_89@msn.com');
    expect($jonas->birth_date->format('Y-m-d'))->toBe('1989-07-26');
    expect($jonas->hiring_date->format('Y-m-d'))->toBe('2014-01-16');
    expect(Hash::check('HEVJ890726HCSRLN02', $jonas->password))->toBeTrue();
    expect($jonas->getDocumentStatus(UserDocumentType::Ine))->toBe(DocumentStatus::Valid);
    expect($jonas->getDocumentStatus(UserDocumentType::ComprobantePago))->toBe(DocumentStatus::Valid);

    // Verify Rubí (empty email -> placeholder email)
    $rubi = User::where('curp', 'FAMR810127MTCLRB01')->firstOrFail();
    expect($rubi->email)->toBe('famr810127mtclrb01@pendiente.siut.local');

    // Verify Ramón (no CURP, no email -> placeholder + fallback password)
    $ramon = User::where('name', 'Ramón')->firstOrFail();
    expect($ramon->email)->toEndWith('@pendiente.siut.local');
    expect(Hash::check('Siut2026!', $ramon->password))->toBeTrue();

    // Verify Daniel (existing user -> updated without changing password)
    $admin->refresh();
    expect($admin->category)->toBe('Docente');
    expect(Hash::check('existing-secret', $admin->password))->toBeTrue();

    // Cleanup removes batch directory
    $this->deleteJson(route('portal.users.import.cleanup', $batchId))->assertOk();
    expect(File::isDirectory(storage_path('app/private/imports/'.$batchId)))->toBeFalse();
});

test('row errors are isolated and do not roll back valid rows in the same batch', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $batchId = 'test_batch_err';
    $csvContent = implode("\n", [
        'nombre,apellidos,direccion,telefono,email,categoria,departamento,nss,curp,fecha_nacimiento,fecha_ingreso,rol',
        ',Sin Nombre,,9360000000,,Docente,,,,,,agremiado',
        'Valid,User,,9360000001,valid@example.com,Docente,,,,,,agremiado',
    ]);

    $this->postJson(route('portal.users.import.chunk'), [
        'batch_id' => $batchId,
        'file_id' => 'csv_0',
        'chunk_index' => 0,
        'total_chunks' => 1,
        'original_name' => 'padron.csv',
        'chunk' => UploadedFile::fake()->createWithContent('padron.csv', $csvContent),
    ])->assertOk();

    $response = $this->postJson(route('portal.users.import.process'), [
        'batch_id' => $batchId,
        'offset' => 0,
        'limit' => 5,
        'fallback_password' => 'Siut2026!',
    ])->assertOk();

    $data = $response->json();
    expect($data['created'])->toBe(1);
    expect($data['errors'])->toHaveCount(1);
    expect($data['errors'][0]['line'])->toBe(2);
    expect(User::where('email', 'valid@example.com')->exists())->toBeTrue();
});
