<?php

namespace Database\Factories;

use App\Models\FundraisingChallenge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FundraisingChallenge>
 */
class FundraisingChallengeFactory extends Factory
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
            'amount_cents' => fake()->unique()->numberBetween(1, 1_000_000),
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'sort_order' => 0,
        ];
    }
}
