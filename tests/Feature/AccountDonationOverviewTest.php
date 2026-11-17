<?php

namespace Tests\Feature;

use App\Models\Donation;
use App\Models\Donor;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountDonationOverviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_an_admin_sees_donations_newest_first_with_donor_details_and_paid_totals(): void
    {
        $admin = User::factory()->approved()->create(['role' => 'admin']);
        $student = User::factory()->approved()->create(['role' => 'student', 'name' => 'Sam Student']);
        $donor = Donor::factory()->create(['name' => 'Dana Donateur', 'email' => 'dana@example.com']);
        $pending = Donation::factory()->for($donor)->create(['amount' => '100.00']);
        $paid = Donation::factory()->for($donor)->completed()->create([
            'amount' => '12.50', 'user_id' => $student->id, 'payment_method' => 'ideal', 'is_anonymous' => true,
        ]);

        $response = $this->actingAs($admin)->getJson(route('account.donations.index'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('totals', ['completedCents' => 1250, 'completedCount' => 1])
            ->assertJsonPath('data.1.id', $pending->id);

        $this->assertSame([
            'id' => $paid->id, 'amountCents' => 1250, 'status' => 'completed', 'paymentMethod' => 'ideal',
            'anonymous' => true, 'donorName' => 'Dana Donateur', 'donorEmail' => 'dana@example.com',
            'studentName' => 'Sam Student', 'createdAt' => $paid->created_at->toIso8601String(),
        ], $response->json('data.0'));
    }

    public function test_the_status_filter_limits_the_list_and_stays_in_the_pagination_links(): void
    {
        $admin = User::factory()->approved()->create(['role' => 'admin']);
        Donation::factory()->completed()->count(26)->create();
        Donation::factory()->create(['status' => 'failed']);

        $this->actingAs($admin)->getJson(route('account.donations.index', ['status' => 'completed']))->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('total', 26)
            ->assertJsonPath('next_page_url', route('account.donations.index', ['status' => 'completed', 'page' => 2]));
        $this->actingAs($admin)->getJson(route('account.donations.index', ['status' => 'paid']))
            ->assertJsonValidationErrors('status');
    }

    /** @return array<string, array{string}> */
    public static function rolesWithoutDonationAccess(): array
    {
        return [
            'organisation' => ['organisatie'],
            'volunteer' => ['vrijwilliger'],
            'student' => ['student'],
        ];
    }

    #[DataProvider('rolesWithoutDonationAccess')]
    public function test_other_roles_cannot_read_donor_details(string $role): void
    {
        $user = User::factory()->approved()->create(['role' => $role]);
        Donation::factory()->completed()->create();

        $this->actingAs($user)->getJson(route('account.donations.index'))->assertForbidden();
        $this->actingAs($user)->get(route('account.index'))->assertOk()
            ->assertViewHas('pageData.qr.canViewDonations', false);
    }

    public function test_guests_and_unapproved_admins_cannot_read_donations(): void
    {
        $this->getJson(route('account.donations.index'))->assertUnauthorized();

        $pendingAdmin = User::factory()->create(['role' => 'admin', 'registration_status' => 'pending']);
        $response = $this->actingAs($pendingAdmin)->getJson(route('account.donations.index'));

        $this->assertNotSame(200, $response->status());
        $response->assertJsonMissingPath('data');
    }

    public function test_the_account_page_offers_the_donation_overview_to_admins(): void
    {
        $admin = User::factory()->approved()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('account.index'))->assertOk()
            ->assertViewHas('pageData.qr.canViewDonations', true)
            ->assertViewHas('pageData.qr.endpoints.donations', route('account.donations.index'));
    }
}
