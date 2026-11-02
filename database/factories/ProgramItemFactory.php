<?php

namespace Database\Factories;

use App\Models\ProgramItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramItem>
 */
class ProgramItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->uuid(),
            'event_date' => '2026-12-16',
            'time_range' => '10:00–10:30',
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'responsible' => null,
            'presenter' => null,
            'production' => null,
            'materials' => null,
            'donation_challenge' => null,
            'sort_order' => 0,
        ];
    }
}
