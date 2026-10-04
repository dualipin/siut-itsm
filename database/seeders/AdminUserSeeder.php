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
        User::firstOrCreate([
            'email' => env('MAIL_ADMIN_ADDRESS'),
        ], [
            'role' => UserRole::Admin->value,
            'name' => 'Administrador',
            'password' => Hash::make('admin1234'),
        ]);
    }
}
