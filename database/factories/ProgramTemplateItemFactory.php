<?php

namespace Database\Factories;

use App\Models\ProgramTemplate;
use App\Models\ProgramTemplateItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProgramTemplateItem>
 */
class ProgramTemplateItemFactory extends Factory
{
    protected $model = ProgramTemplateItem::class;

    public function definition(): array
    {
        return [
            'program_template_id' => ProgramTemplate::factory(),
            'day_of_week' => fake()->numberBetween(1, 7),
            'order_no' => fake()->numberBetween(1, 6),
            'exercise' => fake()->words(2, true),
            'sets' => fake()->optional()->numberBetween(2, 6),
            'reps' => fake()->optional()->numberBetween(6, 20),
            'rest_seconds' => fake()->optional()->numberBetween(30, 180),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
