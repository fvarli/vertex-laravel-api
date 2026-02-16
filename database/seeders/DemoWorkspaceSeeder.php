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

        $workspace = Workspace::query()->updateOrCreate(
            ['name' => 'Vertex Demo Workspace'],
            [
                'owner_user_id' => $owner->id,
                'reminder_policy' => [
                    'enabled' => true,
                    'whatsapp_offsets_minutes' => [1440, 120],
                ],
            ],
        );

        $workspace->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner_admin', 'is_active' => true],
            $trainer->id => ['role' => 'trainer', 'is_active' => true],
        ]);

        $owner->update(['active_workspace_id' => $workspace->id]);
        $trainer->update(['active_workspace_id' => $workspace->id]);
    }
}
