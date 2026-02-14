<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'trainer_user_id' => User::factory(),
            'full_name' => fake()->name(),
            'phone' => '+'.fake()->numerify('90##########'),
            'notes' => fake()->optional()->sentence(),
            'status' => Student::STATUS_ACTIVE,
        ];
    }
}
