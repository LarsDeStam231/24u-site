<?php

namespace Tests\Feature;

use App\Models\Donation;
use App\Models\Donor;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DonationTotalTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_total_starts_at_zero_without_donations(): void
    {
        $this->getJson(route('donations.total'))->assertOk()
            ->assertExactJson(['raisedCents' => 0])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_old_preview_settings_and_cached_test_amounts_do_not_affect_real_donations(): void
    {
        config(['event.event.fundraising.isPreview' => true, 'event.event.fundraising.raisedCents' => 12345]);
        Cache::forever('fundraising:preview-test-added-cents', 1000);
        Donation::factory()->completed()->create(['amount' => '25.25']);

        $this->getJson(route('donations.total'))->assertOk()->assertExactJson(['raisedCents' => 2525]);
    }

    public function test_public_total_only_exposes_completed_donations_without_personal_details(): void
    {
        $donor = Donor::factory()->create(['email' => 'private@example.com', 'phone' => '0612345678']);
        Donation::factory()->for($donor)->completed()->count(2)
            ->sequence(['amount' => '25.25'], ['amount' => '10.10'])->create();
        Donation::factory()->for($donor)->count(5)->sequence(
            ['status' => 'pending', 'payment_id' => 'private-payment'], ['status' => 'failed'], ['status' => 'cancelled'],
            ['status' => 'refunded'], ['status' => 'expired'],
        )->create(['amount' => '100.00']);

        $this->getJson(route('donations.total'))->assertOk()
            ->assertExactJson(['raisedCents' => 3535])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_public_total_reflects_payment_status_changes_without_a_page_reload(): void
    {
        $donation = Donation::factory()->create(['amount' => '12.50']);
        $this->getJson(route('donations.total'))->assertOk()->assertExactJson(['raisedCents' => 0]);
        $donation->status = 'completed';
        $donation->save();

        $this->getJson(route('donations.total'))->assertOk()->assertExactJson(['raisedCents' => 1250]);

        $donation->status = 'refunded';
        $donation->save();

        $this->getJson(route('donations.total'))->assertOk()->assertExactJson(['raisedCents' => 0]);
    }

    /** @return array<string, array{string}> */
    public static function counterPages(): array
    {
        return ['home' => ['/'], 'livestream' => ['/livestream'], 'donations' => ['/doneren'], 'challenges' => ['/challenges']];
    }

    #[DataProvider('counterPages')]
    public function test_pages_share_the_real_total_without_preview_controls(string $path): void
    {
        Donation::factory()->completed()->create(['amount' => '25.00']);
        Donation::factory()->create(['amount' => '100.00']);

        $this->get($path)->assertOk()
            ->assertViewHas('pageData.event.fundraising', ['goalCents' => 1000000, 'raisedCents' => 2500])
            ->assertViewHas('pageData.links.donationTotal', route('donations.total'))
            ->assertDontSee('donationTestAdd')->assertDontSee('donationTestReset');
    }

    /** @return array<string, array{string}> */
    public static function formerCounterActions(): array
    {
        return ['add' => ['/donations/test/add'], 'reset' => ['/donations/test/reset']];
    }

    #[DataProvider('formerCounterActions')]
    public function test_former_test_counter_endpoints_return_404_for_guests(string $path): void
    {
        $this->postJson($path)->assertNotFound();

        $this->assertDatabaseCount('donations', 0);
    }

    #[DataProvider('formerCounterActions')]
    public function test_former_test_counter_endpoints_return_404_for_admins_and_preserve_real_donations(string $path): void
    {
        $admin = User::factory()->approved()->create(['role' => 'admin']);
        $donation = Donation::factory()->completed()->create(['amount' => '25.00']);

        $this->actingAs($admin)->postJson($path)->assertNotFound();

        $this->assertDatabaseCount('donations', 1);
        $this->assertDatabaseHas('donations', ['id' => $donation->id, 'amount' => '25.00', 'status' => 'completed']);
        $this->getJson(route('donations.total'))->assertOk()->assertExactJson(['raisedCents' => 2500]);
    }

    public function test_admins_only_receive_real_fundraising_data_without_test_controls(): void
    {
        $admin = User::factory()->approved()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/doneren')->assertOk()
            ->assertViewHas('pageData.event.fundraising', ['goalCents' => 1000000, 'raisedCents' => 0])
            ->assertDontSee('donationTestAdd')->assertDontSee('donationTestReset');
    }
}
