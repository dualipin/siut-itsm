<?php

use App\Enums\UserRole;
use App\Filament\Resources\AnnualPetitions\Pages\CreateAnnualPetition;
use App\Filament\Resources\AnnualPetitions\Pages\EditAnnualPetition;
use App\Filament\Resources\AnnualPetitions\RelationManagers\PetitionRequestsRelationManager;
use App\Filament\Resources\AnnualPetitions\Schemas\AnnualPetitionForm;
use App\Models\AnnualPetition;
use App\Models\AnnualPetitionConvocation;
use App\Models\PetitionRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');

    Filament::setCurrentPanel(Filament::getPanel('portal'));

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
});

function convocationRepeater(): Repeater
{
    $schema = AnnualPetitionForm::configure(
        Schema::make(Livewire::test(CreateAnnualPetition::class)->instance())
    );

    return collect($schema->getComponents(withHidden: true))
        ->flatten()
        ->first(fn ($component) => $component->getName() === 'convocations');
}

test('the convocation repeater allows adding multiple files', function () {
    expect(convocationRepeater()->isReorderable())->toBeTrue();
});

test('several convocation files can be stored via the relationship', function () {
    $annualPetition = AnnualPetition::factory()->create(['year' => 2031]);

    $convocation1 = AnnualPetitionConvocation::factory()->create([
        'annual_petition_id' => $annualPetition->id,
        'name' => 'Convocatoria 2026',
    ]);
    $convocation1
        ->addMedia(UploadedFile::fake()->create('convocatoria.pdf', 120, 'application/pdf'))
        ->toMediaCollection('file');

    $convocation2 = AnnualPetitionConvocation::factory()->create([
        'annual_petition_id' => $annualPetition->id,
        'name' => 'Anexo 1',
    ]);
    $convocation2
        ->addMedia(UploadedFile::fake()->create('anexo-uno.jpg', 200, 'image/jpeg'))
        ->toMediaCollection('file');

    $convocation3 = AnnualPetitionConvocation::factory()->create([
        'annual_petition_id' => $annualPetition->id,
        'name' => 'Formato Base',
    ]);
    $convocation3
        ->addMedia(UploadedFile::fake()->create('formato-base.png', 90, 'image/png'))
        ->toMediaCollection('file');

    expect($annualPetition->refresh()->convocations)->toHaveCount(3)
        ->and($annualPetition->convocations->pluck('name')->all())
        ->toBe(['Convocatoria 2026', 'Anexo 1', 'Formato Base']);
});

test('a convocation file can be removed without affecting the others', function () {
    $annualPetition = AnnualPetition::factory()->create(['year' => 2031]);

    foreach (['Convocatoria 2026', 'Anexo 1', 'Formato Base'] as $name) {
        $convocation = AnnualPetitionConvocation::factory()->create([
            'annual_petition_id' => $annualPetition->id,
            'name' => $name,
        ]);
        $convocation
            ->addMedia(UploadedFile::fake()->create(strtolower(str_replace(' ', '-', $name)).'.pdf', 100, 'application/pdf'))
            ->toMediaCollection('file');
    }

    $annualPetition->convocations()->where('name', 'Anexo 1')->first()?->delete();

    expect($annualPetition->refresh()->convocations->pluck('name')->all())
        ->toBe(['Convocatoria 2026', 'Formato Base']);
});

test('the edit form loads the stored convocation file', function () {
    $annualPetition = AnnualPetition::factory()->create(['year' => 2031]);

    $convocation = AnnualPetitionConvocation::factory()->create([
        'annual_petition_id' => $annualPetition->id,
        'name' => 'Convocatoria 2026',
    ]);
    $convocation
        ->addMedia(UploadedFile::fake()->create('convocatoria.pdf', 120, 'application/pdf'))
        ->toMediaCollection('file');

    $fileState = Livewire::test(EditAnnualPetition::class, [
        'record' => $annualPetition->getRouteKey(),
    ])->get('data.convocations.record-1.file');

    expect(array_keys($fileState))->toContain($convocation->getFirstMedia('file')->uuid);
});

test('the view modal of a petition request shows its attachments', function () {
    $annualPetition = AnnualPetition::factory()->create(['year' => 2034]);
    $petitionRequest = PetitionRequest::factory()->create([
        'annual_petition_id' => $annualPetition->id,
        'proposal' => 'sdfdfsdfsdf sdfs',
    ]);
    $petitionRequest
        ->addMedia(UploadedFile::fake()->create('adjunto.png', 100, 'image/png'))
        ->toMediaCollection('proposal_files');

    $test = Livewire::test(PetitionRequestsRelationManager::class, [
        'ownerRecord' => $annualPetition,
        'pageClass' => EditAnnualPetition::class,
    ])->call('mountTableAction', 'view', $petitionRequest->getKey());

    $manager = $test->instance();

    expect($manager->mountedActionShouldOpenModal())->toBeTrue()
        ->and($manager->mountedActionHasSchema())->toBeTrue();

    $getSchema = new ReflectionMethod($manager, 'getMountedActionSchema');
    $getSchema->setAccessible(true);
    $schema = $getSchema->invoke($manager);

    $sections = collect($schema->getComponents());

    expect($sections->map(fn ($section) => $section->getHeading())->all())
        ->toBe(['Agremiado', 'Propuesta', 'Adjuntos', 'Auditoría']);

    $attachments = collect($sections
        ->first(fn ($section) => $section->getHeading() === 'Adjuntos')
        ->getChildComponents())
        ->first();

    $state = collect($attachments->getState());

    expect($state)->toHaveCount(1)
        ->and($state->first()->file_name)->toBe('adjunto.png');

    $fileEntry = collect(collect($attachments->getItems())->first()->getComponents())->first();

    expect($fileEntry->getUrl())
        ->toBe($petitionRequest->getFirstMedia('proposal_files')->getUrl());
});
