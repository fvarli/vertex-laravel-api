<?php

namespace Database\Factories;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeviceToken>
 */
class DeviceTokenFactory extends Factory
{
    protected $model = DeviceToken::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform' => fake()->randomElement([DeviceToken::PLATFORM_IOS, DeviceToken::PLATFORM_ANDROID, DeviceToken::PLATFORM_WEB]),
            'token' => fake()->sha256(),
            'device_name' => fake()->optional()->word().' '.fake()->randomElement(['iPhone', 'Pixel', 'Chrome']),
            'is_active' => true,
            'last_used_at' => null,
        ];
    }
}
