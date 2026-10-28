<?php

namespace Database\Factories;

use App\Models\Donation;
use App\Models\Donor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Donation>
 */
class DonationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'donor_id' => Donor::factory(),
            'amount' => '25.00',
            'status' => 'pending',
            'is_anonymous' => false,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'completed']);
    }
}
