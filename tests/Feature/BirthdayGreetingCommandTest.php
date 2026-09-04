<?php

use App\Mail\BirthdayGreetingMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('birthday command sends greeting email to users with birthday today', function () {
    Mail::fake();

    $today = now();

    $celebrant = User::factory()->create([
        'name' => 'María',
        'is_active' => true,
        'email' => 'maria@example.com',
        'birth_date' => $today->copy()->subYears(30)->format('Y-m-d'),
    ]);

    $nonCelebrant = User::factory()->create([
        'name' => 'Pedro',
        'is_active' => true,
        'email' => 'pedro@example.com',
        'birth_date' => $today->copy()->subYears(25)->addMonths(2)->format('Y-m-d'),
    ]);

    $inactiveCelebrant = User::factory()->create([
        'name' => 'Lucía',
        'is_active' => false,
        'email' => 'lucia@example.com',
        'birth_date' => $today->copy()->subYears(28)->format('Y-m-d'),
    ]);

    $this->artisan('birthday:send-greetings')
        ->assertSuccessful();

    Mail::assertQueued(BirthdayGreetingMail::class, function ($mail) use ($celebrant) {
        return $mail->hasTo($celebrant->email)
            && $mail->user->id === $celebrant->id
            && ! empty($mail->messageContent)
            && count($mail->greetingTags) === 3;
    });

    Mail::assertNotQueued(BirthdayGreetingMail::class, function ($mail) use ($nonCelebrant) {
        return $mail->hasTo($nonCelebrant->email);
    });

    Mail::assertNotQueued(BirthdayGreetingMail::class, function ($mail) use ($inactiveCelebrant) {
        return $mail->hasTo($inactiveCelebrant->email);
    });
});

test('birthday command with dry-run does not dispatch emails', function () {
    Mail::fake();

    $today = now();

    User::factory()->create([
        'name' => 'Gabriel',
        'is_active' => true,
        'email' => 'gabriel@example.com',
        'birth_date' => $today->copy()->subYears(35)->format('Y-m-d'),
    ]);

    $this->artisan('birthday:send-greetings --dry-run')
        ->assertSuccessful();

    Mail::assertNothingSent();
});

test('birthday greeting mailable renders successfully', function () {
    $user = User::factory()->make([
        'name' => 'Esteban Morales',
    ]);

    $mailable = new BirthdayGreetingMail(
        $user,
        'Hoy celebramos tu dedicación y entrega constante. ¡Feliz día!',
        ['Salud 🌿', 'Éxito 🚀', 'Fuerza 💪']
    );

    $mailable->assertSeeInHtml('¡Feliz Cumpleaños, Esteban Morales!');
    $mailable->assertSeeInHtml('Hoy celebramos tu dedicación y entrega constante.');
    $mailable->assertSeeInHtml('Salud 🌿');
});
