<?php

namespace Database\Factories;

use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Program>
 */
class ProgramFactory extends Factory
{
    protected $model = Program::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'student_id' => Student::factory(),
            'trainer_user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'goal' => fake()->optional()->sentence(),
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'status' => Program::STATUS_DRAFT,
        ];
    }
}
