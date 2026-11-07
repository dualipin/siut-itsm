<?php

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Filament\Resources\UserRequests\UserRequestResource;
use App\Models\RequestType;
use App\Models\User;
use App\Models\UserRequest;
use App\Policies\UserRequestPolicy;
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
});

test('agremiado only sees their own requests and soft-deleted requests are hidden', function () {
    $agremiado1 = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $agremiado2 = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $type = RequestType::factory()->create();

    $ownActive = UserRequest::factory()->create(['user_id' => $agremiado1->id, 'request_type_id' => $type->id]);
    $ownDeleted = UserRequest::factory()->create(['user_id' => $agremiado1->id, 'request_type_id' => $type->id]);
    $ownDeleted->delete();

    $otherActive = UserRequest::factory()->create(['user_id' => $agremiado2->id, 'request_type_id' => $type->id]);

    $this->actingAs($agremiado1);

    $results = UserRequestResource::getEloquentQuery()->get();
    expect($results->pluck('id'))->toContain($ownActive->id)
        ->and($results->pluck('id'))->not->toContain($ownDeleted->id)
        ->and($results->pluck('id'))->not->toContain($otherActive->id);

    // Admin CAN see soft-deleted requests
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);
    $adminResults = UserRequestResource::getEloquentQuery()->get();
    expect($adminResults->pluck('id'))->toContain($ownDeleted->id);
});

test('agremiado can cancel their request only when status is pending', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $otherUser = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $type = RequestType::factory()->create();

    $pendingRequest = UserRequest::factory()->create([
        'user_id' => $agremiado->id,
        'request_type_id' => $type->id,
        'status' => RequestStatus::Pending,
    ]);

    $reviewRequest = UserRequest::factory()->create([
        'user_id' => $agremiado->id,
        'request_type_id' => $type->id,
        'status' => RequestStatus::UnderReview,
    ]);

    $policy = new UserRequestPolicy;

    // Agremiado can cancel own pending
    expect($policy->cancel($agremiado, $pendingRequest))->toBeTrue();

    // Agremiado cannot cancel own in UnderReview
    expect($policy->cancel($agremiado, $reviewRequest))->toBeFalse();

    // Another user cannot cancel
    expect($policy->cancel($otherUser, $pendingRequest))->toBeFalse();

    // Cancelling pending request updates status to cancelled
    $this->actingAs($agremiado);
    $pendingRequest->update(['status' => RequestStatus::Cancelled]);
    expect($pendingRequest->fresh()->status)->toBe(RequestStatus::Cancelled);
});

test('agremiado cannot edit requests and receives 403 on edit page', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $type = RequestType::factory()->create();
    $request = UserRequest::factory()->create([
        'user_id' => $agremiado->id,
        'request_type_id' => $type->id,
        'status' => RequestStatus::Pending,
    ]);

    $policy = new UserRequestPolicy;
    expect($policy->update($agremiado, $request))->toBeFalse();

    $this->actingAs($agremiado);
    $this->get("/portal/user-requests/{$request->id}/edit")->assertForbidden();
});
