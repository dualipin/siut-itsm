<?php

use App\Filament\Widgets\QrGeneratorWidget;
use App\Models\User;
use Livewire\Livewire;

test('qr generator widget can render successfully for admin', function () {
    $user = User::factory()->admin()->create(['is_active' => true]);

    $this->actingAs($user);

    Livewire::test(QrGeneratorWidget::class)
        ->assertSuccessful()
        ->assertSee('data-vue="portal/qr-generator"', escape: false)
        ->assertSee('island', escape: false);
});

test('admin can view dashboard with qr generator widget while agremiado cannot', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);

    $response = $this->actingAs($admin)->get('/portal');

    $response->assertSuccessful();
    $response->assertSee('data-vue="portal/qr-generator"', escape: false);

    $agremiado = User::factory()->create(['is_active' => true]);
    $agremiadoResponse = $this->actingAs($agremiado)->get('/portal');
    $agremiadoResponse->assertSuccessful();
    $agremiadoResponse->assertDontSee('data-vue="portal/qr-generator"', escape: false);
});
