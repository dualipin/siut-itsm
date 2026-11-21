<?php

use App\Enums\UserRole;
use App\Models\TransparencyRecord;
use App\Models\User;
use App\Policies\TransparencyRecordPolicy;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
    Storage::fake('public');
});

test('directiva policy allows active admin and lider but denies agremiado and inactive directiva', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $leader = User::factory()->lider()->create(['is_active' => true]);
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $inactiveAdmin = User::factory()->admin()->create(['is_active' => false]);
    $inactiveLeader = User::factory()->lider()->make(['is_active' => false]);

    $record = TransparencyRecord::factory()->interno()->create(['created_by' => $admin->id]);
    $policy = new TransparencyRecordPolicy;

    expect($policy->viewAny($admin))->toBeTrue()
        ->and($policy->view($admin, $record))->toBeTrue()
        ->and($policy->create($admin))->toBeTrue()
        ->and($policy->update($admin, $record))->toBeTrue()
        ->and($policy->delete($admin, $record))->toBeTrue()
        ->and($policy->viewAny($leader))->toBeTrue()
        ->and($policy->view($leader, $record))->toBeTrue()
        ->and($policy->viewAny($agremiado))->toBeFalse()
        ->and($policy->view($agremiado, $record))->toBeFalse()
        ->and($policy->viewAny($inactiveAdmin))->toBeFalse()
        ->and($policy->view($inactiveAdmin, $record))->toBeFalse()
        ->and($policy->viewAny($inactiveLeader))->toBeFalse()
        ->and($policy->view($inactiveLeader, $record))->toBeFalse();
});

test('portal transparency media endpoint allows active directiva on both publico and interno records', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $leader = User::factory()->lider()->create(['is_active' => true]);

    $publicRecord = TransparencyRecord::factory()->publico()->create(['created_by' => $admin->id]);
    $publicMedia = $publicRecord->addMedia(UploadedFile::fake()->createWithContent('pub.pdf', '%PDF-1.4 pub'))
        ->toMediaCollection('documents');

    $internoRecord = TransparencyRecord::factory()->interno()->create(['created_by' => $admin->id]);
    $internoMedia = $internoRecord->addMedia(UploadedFile::fake()->createWithContent('int.pdf', '%PDF-1.4 int'))
        ->toMediaCollection('documents');

    $this->actingAs($admin);
    $inlineResponse = $this->get(route('portal.transparency.documents.show', ['record' => $internoRecord, 'media' => $internoMedia]));
    $inlineResponse->assertSuccessful();
    expect($inlineResponse->headers->get('content-disposition'))->toContain('inline');

    $downloadResponse = $this->get(route('portal.transparency.documents.show', ['record' => $publicRecord, 'media' => $publicMedia, 'download' => 1]));
    $downloadResponse->assertSuccessful();
    expect($downloadResponse->headers->get('content-disposition'))->toContain('attachment');

    $this->actingAs($leader);
    $this->get(route('portal.transparency.documents.show', ['record' => $internoRecord, 'media' => $internoMedia]))
        ->assertSuccessful();
});

test('portal transparency media endpoint denies agremiado and inactive users and redirects guests', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $inactiveAdmin = User::factory()->admin()->create(['is_active' => false]);

    $publicRecord = TransparencyRecord::factory()->publico()->create(['created_by' => $admin->id]);
    $publicMedia = $publicRecord->addMedia(UploadedFile::fake()->createWithContent('pub.pdf', '%PDF-1.4 pub'))
        ->toMediaCollection('documents');

    $internoRecord = TransparencyRecord::factory()->interno()->create(['created_by' => $admin->id]);
    $internoMedia = $internoRecord->addMedia(UploadedFile::fake()->createWithContent('int.pdf', '%PDF-1.4 int'))
        ->toMediaCollection('documents');

    // Guest redirects to portal login
    $this->get(route('portal.transparency.documents.show', ['record' => $publicRecord, 'media' => $publicMedia]))
        ->assertRedirect('/portal/login');

    // Agremiado gets 403 even on Publico records via internal endpoint
    $this->actingAs($agremiado);
    $this->get(route('portal.transparency.documents.show', ['record' => $publicRecord, 'media' => $publicMedia]))
        ->assertForbidden();
    $this->get(route('portal.transparency.documents.show', ['record' => $internoRecord, 'media' => $internoMedia]))
        ->assertForbidden();

    // Inactive admin gets 403
    $this->actingAs($inactiveAdmin);
    $this->get(route('portal.transparency.documents.show', ['record' => $internoRecord, 'media' => $internoMedia]))
        ->assertForbidden();

    // Mismatched media returns 404 for active admin
    $this->actingAs($admin);
    $this->get(route('portal.transparency.documents.show', ['record' => $publicRecord, 'media' => $internoMedia]))
        ->assertNotFound();
});
