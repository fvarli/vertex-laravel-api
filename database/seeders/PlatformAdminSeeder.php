<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@vertex.local'],
            [
                'name' => 'Platform',
                'surname' => 'Admin',
                'is_active' => true,
                'system_role' => 'platform_admin',
                'email_verified_at' => now(),
                'password' => Hash::make('password123'),
            ],
        );
    }
}
