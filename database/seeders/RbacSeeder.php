<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $rolePermissions = [
            'owner_admin' => [
                'workspace.manage',
                'students.manage',
                'programs.manage',
                'appointments.manage',
                'calendar.view',
            ],
            'trainer' => [
                'students.own',
                'programs.own',
                'appointments.own',
                'calendar.view',
            ],
        ];

        foreach (array_keys($rolePermissions) as $roleName) {
            Role::query()->updateOrCreate(
                ['name' => $roleName],
                ['guard_name' => 'web'],
            );
        }

        $allPermissions = collect($rolePermissions)->flatten()->unique()->values();

        foreach ($allPermissions as $permissionName) {
            Permission::query()->updateOrCreate(
                ['name' => $permissionName],
                ['guard_name' => 'web'],
            );
        }

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();
            $permissionIds = Permission::query()->whereIn('name', $permissionNames)->pluck('id');
            $role->permissions()->sync($permissionIds);
        }

        $workspace = Workspace::query()->where('name', 'Vertex Demo Workspace')->first();

        if (! $workspace) {
            return;
        }

        $owner = User::query()->where('email', 'owner@vertex.local')->first();
        $trainer = User::query()->where('email', 'trainer@vertex.local')->first();

        if ($owner) {
            $ownerRole = Role::query()->where('name', 'owner_admin')->firstOrFail();
            $owner->roles()->syncWithoutDetaching([
                $ownerRole->id => ['workspace_id' => $workspace->id],
            ]);
        }

        if ($trainer) {
            $trainerRole = Role::query()->where('name', 'trainer')->firstOrFail();
            $trainer->roles()->syncWithoutDetaching([
                $trainerRole->id => ['workspace_id' => $workspace->id],
            ]);
        }
    }
}
