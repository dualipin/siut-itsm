<?php

use App\Filament\Widgets\QrGeneratorWidget;
use App\Models\User;
use Livewire\Livewire;

test('qr generator widget can render successfully', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($user);

    Livewire::test(QrGeneratorWidget::class)
        ->assertSuccessful()
        ->assertSee('data-vue="portal/qr-generator"', escape: false)
        ->assertSee('island', escape: false);
});

test('authenticated user can view dashboard with qr generator widget', function () {
    $user = User::factory()->create(['is_active' => true]);

    $response = $this->actingAs($user)->get('/portal');

    $response->assertSuccessful();
    $response->assertSee('data-vue="portal/qr-generator"', escape: false);
});
