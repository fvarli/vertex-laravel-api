<?php

namespace Database\Factories;

use App\Models\AppointmentSeries;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentSeriesFactory extends Factory
{
    protected $model = AppointmentSeries::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'trainer_user_id' => User::factory(),
            'student_id' => Student::factory(),
            'title' => fake()->words(3, true),
            'location' => fake()->optional()->streetAddress(),
            'recurrence_rule' => [
                'freq' => 'weekly',
                'interval' => 1,
                'byweekday' => [1, 3],
                'until' => now()->addMonths(2)->toDateString(),
            ],
            'start_date' => now()->toDateString(),
            'starts_at_time' => '10:00:00',
            'ends_at_time' => '11:00:00',
            'status' => AppointmentSeries::STATUS_ACTIVE,
        ];
    }
}
