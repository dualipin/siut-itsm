<?php

namespace App\Console\Commands;

use App\Mail\BirthdayGreetingMail;
use App\Models\User;
use App\Services\BirthdayService;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

#[Signature('birthday:send-greetings {--date= : Fecha a consultar en formato Y-m-d (por defecto hoy)} {--dry-run : Simular envío sin mandar correos reales}')]
#[Description('Envía correos electrónicos de felicitación a los usuarios activos que cumplen años')]
class SendBirthdayGreetings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dateInput = $this->option('date');
        $date = $dateInput ? Carbon::parse($dateInput) : now();
        $isDryRun = (bool) $this->option('dry-run');

        $this->info("Buscando cumpleañeros para la fecha: {$date->format('d/m/Y')}".($isDryRun ? ' (MODO SIMULACIÓN)' : ''));

        $users = User::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->whereNotNull('birth_date')
            ->whereMonth('birth_date', $date->month)
            ->whereDay('birth_date', $date->day)
            ->get();

        if ($users->isEmpty()) {
            $this->info("No hay usuarios activos que cumplan años el {$date->format('d/m')}.");

            return Command::SUCCESS;
        }

        $this->info("Se encontraron {$users->count()} cumpleañero(s). Procesando felicitaciones...");

        $usedPhrases = [];
        $sentCount = 0;

        foreach ($users as $index => $user) {
            $phrase = BirthdayService::getPhrase($index, $usedPhrases);
            $usedPhrases[] = $phrase;
            $tags = BirthdayService::getTags(3, $index);

            if ($isDryRun) {
                $this->line(" - [SIMULACIÓN] Para: {$user->name} ({$user->email}) | Frase: \"{$phrase}\"");
            } else {
                Mail::to($user->email)->send(new BirthdayGreetingMail($user, $phrase, $tags));
                $this->line(" - [ENVIADO] Felicitación enviada a {$user->name} ({$user->email})");
                Log::info("Felicitación de cumpleaños enviada a {$user->email}");
            }

            $sentCount++;
        }

        $this->info("Proceso completado. Total de felicitaciones procesadas: {$sentCount}");

        return Command::SUCCESS;
    }
}
