<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'calendar_id' => \App\Models\Calendar::factory(),
            'creator_id' => \App\Models\User::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->optional()->sentence(8),
            'location' => $this->faker->optional()->city,
            'start_at' => $this->faker->dateTimeBetween('+1 day', '+2 days'),
            'end_at' => fn (array $attrs) => (clone $attrs['start_at'])->modify('+1 hour'),
            'is_all_day' => false,
            'recurrence_rule' => null,
            'recurrence_end' => null,
            'visibility' => 'household',
            'category' => $this->faker->optional()->randomElement(['school', 'work', 'medical', 'errand']),
        ];
    }
}
