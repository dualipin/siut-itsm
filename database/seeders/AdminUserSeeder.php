<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Hash;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate([
            'email' => env('MAIL_ADMIN_ADDRESS'),
        ], [
            'role' => UserRole::Admin->value,
            'name' => 'Administrador',
            'curp' => env('ADMIN_CURP'),
            'password' => Hash::make('admin1234'),
        ]);

        // Login is done with CURP: backfill it for an admin created before this requirement.
        if (blank($admin->curp) && filled(env('ADMIN_CURP'))) {
            $admin->update(['curp' => env('ADMIN_CURP')]);
        }
    }
}
