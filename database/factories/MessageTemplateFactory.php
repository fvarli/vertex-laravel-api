<?php

namespace Database\Factories;

use App\Models\MessageTemplate;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MessageTemplate>
 */
class MessageTemplateFactory extends Factory
{
    protected $model = MessageTemplate::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->words(3, true),
            'channel' => 'whatsapp',
            'body' => 'Hi {student_name}, your session is on {appointment_date} at {appointment_time}.',
            'is_default' => false,
        ];
    }
}
