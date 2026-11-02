<?php

namespace Database\Seeders;

use App\EventContentCatalog;
use Illuminate\Database\Seeder;

class EventContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(EventContentCatalog $catalog): void
    {
        $catalog->seedProgramDefaults();
        $catalog->seedChallengeDefaults();
    }
}
