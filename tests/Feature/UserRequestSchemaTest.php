<?php

use App\Enums\RequestStatus;
use App\Models\RequestType;
use App\Models\User;
use App\Models\UserRequest;
use App\Models\UserRequestHistory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('request type can be created and auto-generates slug', function () {
    $type = RequestType::factory()->create([
        'name' => 'Apoyo para Lentes',
        'slug' => null,
        'custom_fields' => [
            ['name' => 'graduacion', 'type' => 'text', 'label' => 'Graduación oftálmica'],
            ['name' => 'costo_armazon', 'type' => 'number', 'label' => 'Costo del armazón'],
        ],
    ]);

    expect($type->slug)->toBe('apoyo-para-lentes')
        ->and($type->custom_fields)->toBeArray()
        ->and($type->custom_fields[0]['name'])->toBe('graduacion')
        ->and($type->is_active)->toBeTrue();
});

test('active scope returns only active request types', function () {
    RequestType::factory()->create(['is_active' => true, 'sort_order' => 1]);
    RequestType::factory()->create(['is_active' => false, 'sort_order' => 2]);

    $activeTypes = RequestType::active()->get();

    expect($activeTypes)->toHaveCount(1);
});

test('user request can be created and auto-generates unique folio', function () {
    $user = User::factory()->create();
    $type = RequestType::factory()->create(['name' => 'Equipo Laptop']);

    $request = UserRequest::create([
        'user_id' => $user->id,
        'request_type_id' => $type->id,
        'reason' => 'Se requiere equipo portátil para labores de investigación y docencia en el ITSM.',
        'additional_data' => [
            'ram' => '16GB',
            'almacenamiento' => '512GB SSD',
        ],
        'status' => RequestStatus::Pending,
    ]);

    $year = now()->format('Y');

    expect($request->folio)->toStartWith("SOL-{$year}-")
        ->and($request->status)->toBe(RequestStatus::Pending)
        ->and($request->status->getLabel())->toBe('Pendiente')
        ->and($request->reason)->toContain('Se requiere equipo portátil')
        ->and($request->additional_data['ram'])->toBe('16GB')
        ->and($request->user->id)->toBe($user->id)
        ->and($request->requestType->id)->toBe($type->id);

    expect($user->userRequests)->toHaveCount(1);
});

test('user request supports media attachments with Spatie MediaLibrary', function () {
    $request = UserRequest::factory()->create();

    $fakeFile = UploadedFile::fake()->create('receta_medica.pdf', 500, 'application/pdf');

    $media = $request->addMedia($fakeFile)->toMediaCollection('attachments');

    expect($request->getMedia('attachments'))->toHaveCount(1)
        ->and($media->file_name)->toBe('receta_medica.pdf');
});

test('user request can record status changes in history', function () {
    $reviewer = User::factory()->create();
    $request = UserRequest::factory()->create([
        'status' => RequestStatus::Pending,
    ]);

    $history = $request->recordHistory(
        toStatus: RequestStatus::UnderReview,
        fromStatus: RequestStatus::Pending,
        notes: 'Iniciando revisión de documentación adjunta',
        userId: $reviewer->id
    );

    expect($history)->toBeInstanceOf(UserRequestHistory::class)
        ->and($history->from_status)->toBe(RequestStatus::Pending)
        ->and($history->to_status)->toBe(RequestStatus::UnderReview)
        ->and($history->notes)->toBe('Iniciando revisión de documentación adjunta')
        ->and($history->user_id)->toBe($reviewer->id)
        ->and($request->histories)->toHaveCount(1);
});

test('user request status enum provides labels, colors, and icons for filament', function () {
    expect(RequestStatus::Pending->getColor())->toBe('warning')
        ->and(RequestStatus::Approved->getColor())->toBe('success')
        ->and(RequestStatus::Rejected->getColor())->toBe('danger')
        ->and(RequestStatus::UnderReview->getLabel())->toBe('En Revisión')
        ->and(RequestStatus::Completed->getIcon())->toBe('heroicon-m-gift');
});
