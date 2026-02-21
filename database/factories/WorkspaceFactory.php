<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Workspace',
            'owner_user_id' => User::factory(),
            'approval_status' => 'approved',
            'approval_requested_at' => now(),
            'approved_at' => now(),
        ];
    }
}
