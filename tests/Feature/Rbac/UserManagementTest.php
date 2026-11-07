<?php

use App\Actions\TransferLeadershipAction;
use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use App\Policies\UserPolicy;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
});

test('only one leader can exist in the system and creating or promoting a second leader throws DomainException', function () {
    $firstLeader = User::factory()->lider()->create(['is_active' => true]);

    expect(User::where('role', UserRole::Lider)->count())->toBe(1);

    // Attempting to create a second leader throws DomainException
    expect(fn () => User::factory()->lider()->create())
        ->toThrow(DomainException::class, 'Solo puede existir un usuario con el rol de Líder.');

    // Even if the current leader is inactive
    $firstLeader->update(['is_active' => false]);

    expect(fn () => User::factory()->lider()->create())
        ->toThrow(DomainException::class, 'Solo puede existir un usuario con el rol de Líder.');

    // Attempting to promote an existing user to leader directly throws DomainException
    $admin = User::factory()->admin()->create();
    expect(fn () => $admin->update(['role' => UserRole::Lider]))
        ->toThrow(DomainException::class, 'Solo puede existir un usuario con el rol de Líder.');
});

test('transfer leadership is atomic and only the current leader can execute it', function () {
    Log::shouldReceive('channel')
        ->with('audit')
        ->atLeast()->once()
        ->andReturnSelf();
    Log::shouldReceive('info')
        ->atLeast()->once()
        ->withArgs(function (string $message, array $context) {
            return $context['action'] === 'transfer_leadership'
                && ! isset($context['password'])
                && ! isset($context['email']);
        });

    $currentLeader = User::factory()->lider()->create();
    $targetUser = User::factory()->admin()->create();

    $action = app(TransferLeadershipAction::class);

    // Non-leader fails
    $randomAdmin = User::factory()->admin()->create();
    expect(fn () => $action->execute($randomAdmin, $targetUser))
        ->toThrow(AuthorizationException::class);

    // Leader executing transfer works
    $action->execute($currentLeader, $targetUser);

    $currentLeader->refresh();
    $targetUser->refresh();

    expect($currentLeader->role)->toBe(UserRole::Admin);
    expect($targetUser->role)->toBe(UserRole::Lider);
    expect(User::where('role', UserRole::Lider)->count())->toBe(1);
});

test('user cannot change own role or deactivate themselves on user resource edit', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
        ->assertFormFieldIsDisabled('role')
        ->assertFormFieldIsDisabled('is_active')
        ->set('data.role', UserRole::Agremiado->value)
        ->set('data.is_active', false)
        ->call('save')
        ->assertHasNoErrors();

    $admin->refresh();
    expect($admin->role)->toBe(UserRole::Admin);
    expect($admin->is_active)->toBeTrue();
});

test('user cannot delete themselves and user policy prevents self deletion', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $policy = new UserPolicy;

    expect($policy->delete($admin, $admin))->toBeFalse();
});

test('system never allows deleting or deactivating the last active admin or leader', function () {
    // Clean up admins/leaders
    User::whereIn('role', [UserRole::Admin, UserRole::Lider])->delete();

    $activeAdmin = User::factory()->admin()->create(['is_active' => true]);
    $secondAdmin = User::factory()->admin()->create(['is_active' => true]);
    $policy = new UserPolicy;

    // Both are active, so secondAdmin can be deleted
    expect($policy->delete($activeAdmin, $secondAdmin))->toBeTrue();

    // Now delete secondAdmin so activeAdmin is the only one
    $secondAdmin->delete();

    // Trying to delete activeAdmin when no other active admin exists returns false
    $inactiveAdmin = User::factory()->admin()->create(['is_active' => false]);
    expect($policy->delete($inactiveAdmin, $activeAdmin))->toBeFalse();

    // Deactivating the sole remaining active admin via edit form halts and leaves active
    $this->actingAs($activeAdmin);
    Livewire::test(EditUser::class, ['record' => $activeAdmin->getRouteKey()])
        ->set('data.is_active', false)
        ->call('save');

    $activeAdmin->refresh();
    expect($activeAdmin->is_active)->toBeTrue();
});

test('admin cannot edit deactivate delete or change password of the leader', function () {
    $leader = User::factory()->lider()->create(['is_active' => true]);
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $policy = new UserPolicy;

    expect($policy->update($admin, $leader))->toBeFalse();
    expect($policy->delete($admin, $leader))->toBeFalse();

    // In turn, the leader CAN edit and delete admins
    expect($policy->update($leader, $admin))->toBeTrue();
    expect($policy->delete($leader, $admin))->toBeTrue();

    // Trying to visit edit page as admin returns 403
    $this->actingAs($admin);
    $this->get("/portal/users/{$leader->id}/edit")->assertForbidden();
});

test('sensitive user operations write to audit log without PII', function () {
    Log::shouldReceive('channel')
        ->with('audit')
        ->atLeast()->once()
        ->andReturnSelf();
    Log::shouldReceive('info')
        ->atLeast()->once()
        ->withArgs(function (string $message, array $context) {
            expect($context)->not->toHaveKey('password')
                ->and($context)->not->toHaveKey('email')
                ->and($context)->not->toHaveKey('curp')
                ->and($context)->not->toHaveKey('salary')
                ->and($context)->toHaveKey('action')
                ->and($context)->toHaveKey('actor_id');

            return true;
        });

    $actor = User::factory()->admin()->create();
    $target = User::factory()->create(['is_active' => true, 'role' => UserRole::Agremiado]);

    $this->actingAs($actor);

    Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
        ->set('data.role', UserRole::Admin->value)
        ->set('data.is_active', false)
        ->set('data.password', 'brand-new-secret-123')
        ->call('save');

    $target->refresh();
    expect($target->role)->toBe(UserRole::Admin);
    expect($target->is_active)->toBeFalse();
});
