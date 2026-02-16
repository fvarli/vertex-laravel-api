<?php

namespace Database\Factories;

use App\Models\ProgramTemplate;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProgramTemplate>
 */
class ProgramTemplateFactory extends Factory
{
    protected $model = ProgramTemplate::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'trainer_user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'title' => fake()->sentence(3),
            'goal' => fake()->optional()->sentence(),
        ];
    }
}
