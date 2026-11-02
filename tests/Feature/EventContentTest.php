<?php

namespace Tests\Feature;

use App\Models\FundraisingChallenge;
use App\Models\ProgramItem;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EventContentSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventContentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_program_page_contains_the_twenty_supplied_slots_for_both_days(): void
    {
        $response = $this->get('/programma');

        $response->assertOk();
        $schedule = $response->viewData('pageData')['event']['schedule'];

        $this->assertCount(20, $schedule);
        $this->assertSame('2026-12-16', $schedule[0]['date']);
        $this->assertSame('10:00–10:30', $schedule[0]['time']);
        $this->assertSame('Officiële opening', $schedule[0]['title']);
        $this->assertSame('Kevin', $schedule[7]['presenter']);
        $this->assertSame('Kevin', $schedule[9]['presenter']);
        $this->assertSame('2026-12-17', $schedule[11]['date']);
        $this->assertSame('09:30–10:00', $schedule[19]['time']);
        $this->assertSame('Grote finale', $schedule[19]['title']);
        $this->assertArrayNotHasKey('responsible', $schedule[0]);
        $this->assertArrayNotHasKey('production', $schedule[0]);
    }

    public function test_challenge_page_contains_the_complete_supplied_ladder(): void
    {
        $response = $this->get('/challenges');

        $response->assertOk();
        $challenges = $response->viewData('pageData')['event']['challenges'];

        $this->assertSame([
            5000, 10000, 20000, 30000, 40000, 50000, 60000, 70000, 80000, 90000,
            100000, 110000, 120000, 130000, 140000, 150000, 160000, 170000, 180000, 190000,
            200000, 250000, 300000, 350000, 400000, 450000, 500000, 600000, 700000, 750000,
            800000, 900000, 1000000,
        ], array_column($challenges, 'amountCents'));
        $this->assertSame('Rad van Pech', $challenges[0]['name']);
        $this->assertSame('THE ULTIMATE 10K', $challenges[32]['name']);
    }

    public function test_program_database_content_replaces_defaults_with_stable_order_and_production_details(): void
    {
        ProgramItem::factory()->create(['slug' => 'last', 'sort_order' => 2]);
        ProgramItem::factory()->create([
            'slug' => 'first', 'sort_order' => 1, 'event_date' => '2026-12-17',
            'time_range' => '08:00–09:00', 'title' => 'Aangepaste ochtendshow',
            'description' => 'Het nieuwste programma.', 'responsible' => 'Team A',
            'presenter' => 'Presentator', 'production' => 'Regie', 'materials' => 'Microfoon',
            'donation_challenge' => 'Ochtendchallenge',
        ]);
        ProgramItem::factory()->create(['slug' => 'second', 'sort_order' => 1]);

        $response = $this->get('/programma');

        $response->assertOk()->assertViewHas('pageData.event.schedule.0', [
            'id' => 'first', 'date' => '2026-12-17', 'time' => '08:00–09:00',
            'title' => 'Aangepaste ochtendshow', 'description' => 'Het nieuwste programma.',
            'responsible' => 'Team A', 'presenter' => 'Presentator', 'production' => 'Regie',
            'materials' => 'Microfoon', 'donationChallenge' => 'Ochtendchallenge',
        ]);
        $this->assertSame(['first', 'second', 'last'], array_column($response->viewData('pageData')['event']['schedule'], 'id'));
    }

    public function test_database_challenges_replace_defaults_and_are_sorted_with_display_flags(): void
    {
        FundraisingChallenge::factory()->create(['amount_cents' => 100000, 'name' => 'Headline', 'description' => 'Groot moment.']);
        FundraisingChallenge::factory()->create(['amount_cents' => 5000, 'name' => 'Eerste', 'description' => 'Eerste moment.']);
        FundraisingChallenge::factory()->create(['amount_cents' => 50000, 'name' => 'Uitgelicht', 'description' => 'Bijzonder moment.']);

        $this->get('/challenges')->assertOk()->assertViewHas('pageData.event.challenges', [
            ['amountCents' => 5000, 'name' => 'Eerste', 'description' => 'Eerste moment.', 'isFeatured' => false, 'isHeadline' => false],
            ['amountCents' => 50000, 'name' => 'Uitgelicht', 'description' => 'Bijzonder moment.', 'isFeatured' => true, 'isHeadline' => false],
            ['amountCents' => 100000, 'name' => 'Headline', 'description' => 'Groot moment.', 'isFeatured' => true, 'isHeadline' => true],
        ]);
    }

    public function test_public_pages_keep_working_before_the_content_migrations_are_applied(): void
    {
        $this->assertDatabaseCount('program_items', 0);
        Schema::drop('program_items');
        Schema::drop('fundraising_challenges');

        $this->get('/')->assertOk()
            ->assertViewHas('pageData.event.schedule.19.title', 'Grote finale')
            ->assertViewHas('pageData.event.challenges.32.name', 'THE ULTIMATE 10K');
    }

    public function test_seeding_imports_the_supplied_content_without_replacing_later_edits(): void
    {
        $this->seed(EventContentSeeder::class);
        $programItem = ProgramItem::query()->where('slug', 'opening')->firstOrFail();
        $programItem->update(['title' => 'Eigen opening']);
        $challenge = FundraisingChallenge::query()->where('slug', 'challenge-5000')->firstOrFail();
        $challenge->update(['name' => 'Eigen challenge', 'amount_cents' => 7500]);

        $this->seed(EventContentSeeder::class);

        $this->assertDatabaseCount('program_items', 20);
        $this->assertDatabaseCount('fundraising_challenges', 33);
        $this->assertDatabaseHas('program_items', ['slug' => 'opening', 'title' => 'Eigen opening']);
        $this->assertDatabaseHas('fundraising_challenges', ['slug' => 'challenge-5000', 'name' => 'Eigen challenge', 'amount_cents' => 7500]);
        $this->assertDatabaseHas('program_items', ['slug' => 'evening-show-1', 'presenter' => 'Kevin']);
    }

    public function test_default_seeding_does_not_create_example_accounts_sponsors_or_donations(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('sponsor', 0);
        $this->assertDatabaseCount('donors', 0);
        $this->assertDatabaseCount('donations', 0);
    }

    public function test_database_content_is_safely_encoded_for_the_public_website(): void
    {
        $unsafeTitle = '</script><script>window.injected = true</script>';
        ProgramItem::factory()->create(['title' => $unsafeTitle]);

        $this->get('/programma')->assertOk()
            ->assertViewHas('pageData.event.schedule.0.title', $unsafeTitle)
            ->assertDontSee($unsafeTitle, false);
    }
}
