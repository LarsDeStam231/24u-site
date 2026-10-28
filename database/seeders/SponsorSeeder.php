<?php

namespace Database\Seeders;

use App\Models\Sponsor;
use Illuminate\Database\Seeder;

class SponsorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (config('event.event.sponsorTiers') as $tier) {
            foreach ($tier['sponsors'] as $sponsor) {
                Sponsor::query()->updateOrCreate(
                    ['company_name' => $sponsor['name']],
                    ['sponsorship_type' => $tier['id'], 'company_type' => ''],
                );
            }
        }
    }
}
