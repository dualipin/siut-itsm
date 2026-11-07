<?php

use App\Enums\UserRole;
use App\Filament\Pages\Messages;
use App\Filament\Pages\PostDeployMaintenance;
use App\Filament\Pages\Profile;
use App\Filament\Resources\AnnualPetitions\AnnualPetitionResource;
use App\Filament\Resources\Benefits\BenefitResource;
use App\Filament\Resources\FinancialReports\FinancialReportResource;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\TransparencyRecords\TransparencyRecordResource;
use App\Filament\Resources\UserRequests\UserRequestResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\QrGeneratorWidget;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Resources\Resource;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
});

test('panel access matrix enforces strict whitelist for agremiado iterating all resources and pages', function () {
    $agremiado = User::factory()->create([
        'role' => UserRole::Agremiado,
        'is_active' => true,
    ]);

    $this->actingAs($agremiado);

    $allowedResources = [
        LoanResource::class,
        UserRequestResource::class,
    ];

    $allResources = Filament::getCurrentPanel()->getResources();

    foreach ($allResources as $resource) {
        if (in_array($resource, $allowedResources, true)) {
            expect($resource::canAccess())->toBeTrue("Resource {$resource} should be accessible to Agremiado");
        } else {
            expect($resource::canAccess())->toBeFalse("Resource {$resource} must be forbidden to Agremiado");
            $this->get($resource::getUrl('index'))->assertForbidden();
        }
    }

    $allowedPages = [
        Dashboard::class,
        Messages::class,
        Profile::class,
    ];

    $allPages = Filament::getCurrentPanel()->getPages();

    foreach ($allPages as $page) {
        if (in_array($page, $allowedPages, true)) {
            expect($page::canAccess())->toBeTrue("Page {$page} should be accessible to Agremiado");
        } else {
            expect($page::canAccess())->toBeFalse("Page {$page} must be forbidden to Agremiado");
        }
    }

    expect(QrGeneratorWidget::canView())->toBeFalse();
});

test('admin can access all resources and pages including post deploy maintenance', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $allResources = Filament::getCurrentPanel()->getResources();
    foreach ($allResources as $resource) {
        expect($resource::canAccess())->toBeTrue("Resource {$resource} should be accessible to Admin");
    }

    expect(PostDeployMaintenance::canAccess())->toBeTrue();
    expect(QrGeneratorWidget::canView())->toBeTrue();
});

test('leader can access administrative resources but is denied on post deploy maintenance', function () {
    $leader = User::factory()->lider()->create(['is_active' => true]);
    $this->actingAs($leader);

    expect(UserResource::canAccess())->toBeTrue();
    expect(BenefitResource::canAccess())->toBeTrue();
    expect(PostResource::canAccess())->toBeTrue();
    expect(TransparencyRecordResource::canAccess())->toBeTrue();
    expect(FinancialReportResource::canAccess())->toBeTrue();
    expect(AnnualPetitionResource::canAccess())->toBeTrue();

    // PostDeployMaintenance is an explicit documented exception: Admin only, denied for Leader
    expect(PostDeployMaintenance::canAccess())->toBeFalse();
    $this->get(PostDeployMaintenance::getUrl())->assertForbidden();

    expect(QrGeneratorWidget::canView())->toBeTrue();
});

test('a new resource without a policy or canAccess is denied by default to agremiado', function () {
    $agremiado = User::factory()->create([
        'role' => UserRole::Agremiado,
        'is_active' => true,
    ]);

    $this->actingAs($agremiado);

    $dummyResource = new class extends Resource
    {
        protected static ?string $model = 'App\Models\NonExistentModel';
    };

    expect($dummyResource::canAccess())->toBeFalse();
});
