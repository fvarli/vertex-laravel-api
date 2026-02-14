<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $start = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0);

        return [
            'workspace_id' => Workspace::factory(),
            'trainer_user_id' => User::factory(),
            'student_id' => Student::factory(),
            'starts_at' => $start,
            'ends_at' => (clone $start)->addHour(),
            'status' => Appointment::STATUS_PLANNED,
            'location' => fake()->optional()->streetAddress(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
