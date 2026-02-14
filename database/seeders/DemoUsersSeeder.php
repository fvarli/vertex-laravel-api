<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'owner@vertex.local'],
            [
                'name' => 'Owner',
                'surname' => 'Admin',
                'phone' => '+905551111111',
                'is_active' => true,
                'email_verified_at' => now(),
                'password' => Hash::make('password123'),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'trainer@vertex.local'],
            [
                'name' => 'Primary',
                'surname' => 'Trainer',
                'phone' => '+905552222222',
                'is_active' => true,
                'email_verified_at' => now(),
                'password' => Hash::make('password123'),
            ],
        );
    }
}
