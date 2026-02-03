<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Calendar>
 */
class CalendarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => \App\Models\Household::factory(),
            'name' => $this->faker->randomElement(['Family', 'Work', 'Personal']) . ' Calendar',
            'color' => $this->faker->randomElement(['#14b8a6', '#f59e0b', '#c084fc', '#312e81', '#e0f2fe', '#0f172a']),
            'visibility_scope' => 'household',
            'owner_id' => null,
            'is_default' => false,
        ];
    }
}
