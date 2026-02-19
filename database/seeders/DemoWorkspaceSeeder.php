<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class DemoWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->where('email', 'owner@vertex.local')->firstOrFail();
        $trainer = User::query()->where('email', 'trainer@vertex.local')->firstOrFail();
        $admin = User::query()->where('email', 'admin@vertex.local')->firstOrFail();

        $workspace = Workspace::query()->updateOrCreate(
            ['name' => 'Vertex Demo Workspace'],
            [
                'owner_user_id' => $owner->id,
                'reminder_policy' => [
                    'enabled' => true,
                    'whatsapp_offsets_minutes' => [1440, 120],
                    'weekend_mute' => true,
                    'manual_send_confirmation_required' => true,
                    'quiet_hours' => [
                        'enabled' => true,
                        'start' => '22:00',
                        'end' => '08:00',
                        'timezone' => 'Europe/Istanbul',
                    ],
                    'retry' => [
                        'max_attempts' => 2,
                        'backoff_minutes' => [15, 30],
                        'escalate_on_exhausted' => true,
                    ],
                ],
            ],
        );

        $workspace->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner_admin', 'is_active' => true],
            $trainer->id => ['role' => 'trainer', 'is_active' => true],
            $admin->id => ['role' => 'owner_admin', 'is_active' => true],
        ]);

        $owner->update(['active_workspace_id' => $workspace->id]);
        $trainer->update(['active_workspace_id' => $workspace->id]);
        $admin->update(['active_workspace_id' => $workspace->id]);
    }
}
