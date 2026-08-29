<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\TransparencyRecord;
use App\Models\User;
use Illuminate\Database\Seeder;

class DocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $records = TransparencyRecord::all();
        $user = User::first() ?? User::factory()->create();

        if ($records->isEmpty()) {
            $records = TransparencyRecord::factory()->count(5)->create([
                'created_by' => $user->id,
            ]);
        }

        foreach ($records as $record) {
            Document::factory()
                ->count(2)
                ->create([
                    'record_id' => $record->id,
                    'owner_id' => $user->id,
                    'uploaded_by' => $user->id,
                ]);
        }
    }
}
