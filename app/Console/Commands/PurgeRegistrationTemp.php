<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeRegistrationTemp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'registration:purge-temp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge temporary registration uploads older than 3 hours';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $disk = Storage::disk('local');
        $directory = 'temp-uploads/registro';

        if (! $disk->exists($directory)) {
            $this->info('No registration temp directory found.');

            return self::SUCCESS;
        }

        $files = $disk->allFiles($directory);
        $threshold = now()->subHours(3)->timestamp;
        $purgedCount = 0;

        foreach ($files as $file) {
            try {
                if ($disk->lastModified($file) < $threshold) {
                    $disk->delete($file);
                    $purgedCount++;
                }
            } catch (\Throwable $e) {
                $this->warn("Failed to check or delete {$file}: {$e->getMessage()}");
            }
        }

        $this->info("Purged {$purgedCount} temporary registration files.");

        return self::SUCCESS;
    }
}
