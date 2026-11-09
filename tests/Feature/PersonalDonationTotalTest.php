<?php

namespace Tests\Feature;

use App\Models\Donation;
use App\Models\Donor;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersonalDonationTotalTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_student_starts_at_zero_with_a_five_hundred_euro_goal_even_before_qr_activation(): void
    {
        $student = User::factory()->approved()->create(['role' => 'student', 'name' => null]);

        $this->actingAs($student)->get(route('account.index'))->assertOk()
            ->assertViewHas('pageData.qr.personalFundraising', ['goalCents' => 50000, 'raisedCents' => 0])
            ->assertViewHas('pageData.qr.endpoints.personalDonationTotal', route('account.donations.total'));
    }

    public function test_the_personal_total_only_counts_the_current_students_completed_donations_and_ignores_a_different_user_id(): void
    {
        $student = User::factory()->approved()->create(['role' => 'student']);
        $other = User::factory()->approved()->create(['role' => 'student']);
        $donor = Donor::factory()->create(['email' => 'private@example.com', 'phone' => '0612345678']);
        Donation::factory()->for($donor)->completed()->count(2)
            ->sequence(['amount' => '25.25'], ['amount' => '10.10'])->create(['user_id' => $student->id]);
        Donation::factory()->for($donor)->count(5)->sequence(
            ['status' => 'pending'], ['status' => 'failed'], ['status' => 'cancelled'],
            ['status' => 'refunded'], ['status' => 'expired'],
        )->create(['user_id' => $student->id, 'amount' => '100.00']);
        Donation::factory()->for($donor)->completed()->create(['user_id' => $other->id, 'amount' => '200.00']);
        Donation::factory()->for($donor)->completed()->create(['user_id' => null, 'amount' => '300.00']);

        $this->actingAs($student)->getJson(route('account.donations.total', ['user_id' => $other->id]))->assertOk()
            ->assertExactJson(['raisedCents' => 3535])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_the_account_total_continues_above_the_students_goal(): void
    {
        $student = User::factory()->approved()->create(['role' => 'student']);
        Donation::factory()->completed()->create(['user_id' => $student->id, 'amount' => '650.00']);

        $this->actingAs($student)->get(route('account.index'))->assertOk()
            ->assertViewHas('pageData.qr.personalFundraising', ['goalCents' => 50000, 'raisedCents' => 65000]);
    }

    public function test_the_personal_total_updates_after_payment_confirmation_and_refund(): void
    {
        $student = User::factory()->approved()->create(['role' => 'student']);
        $donation = Donation::factory()->create(['user_id' => $student->id, 'amount' => '650.00']);

        $this->actingAs($student)->getJson(route('account.donations.total'))->assertOk()->assertExactJson(['raisedCents' => 0]);
        $donation->status = 'completed';
        $donation->save();

        $this->getJson(route('account.donations.total'))->assertOk()->assertExactJson(['raisedCents' => 65000]);
        $donation->status = 'refunded';
        $donation->save();

        $this->getJson(route('account.donations.total'))->assertOk()->assertExactJson(['raisedCents' => 0]);
    }

    public function test_guests_receive_401_when_requesting_a_personal_total(): void
    {
        $this->getJson(route('account.donations.total'))->assertUnauthorized();
    }

    /** @return array<string, array{string}> */
    public static function unapprovedStatuses(): array
    {
        return ['pending' => ['pending'], 'rejected' => ['rejected']];
    }

    #[DataProvider('unapprovedStatuses')]
    public function test_unapproved_students_receive_403_when_requesting_a_personal_total(string $status): void
    {
        $student = User::factory()->create(['role' => 'student', 'registration_status' => $status]);

        $this->actingAs($student)->getJson(route('account.donations.total'))->assertForbidden();
    }

    /** @return array<string, array{string}> */
    public static function nonStudentRoles(): array
    {
        return [
            'administrator' => ['admin'], 'organization' => ['organisatie'], 'volunteer' => ['vrijwilliger'],
            'donor' => ['donateur'], 'guest' => ['gast'],
        ];
    }

    #[DataProvider('nonStudentRoles')]
    public function test_non_students_receive_403_when_requesting_a_personal_total(string $role): void
    {
        $user = User::factory()->approved()->create(['role' => $role]);

        $this->actingAs($user)->getJson(route('account.donations.total'))->assertForbidden();
    }

    public function test_non_student_accounts_do_not_receive_a_student_fundraising_goal(): void
    {
        $organization = User::factory()->approved()->create(['role' => 'organisatie']);

        $this->actingAs($organization)->get(route('account.index'))->assertOk()
            ->assertViewHas('pageData.qr.personalFundraising', fn (?array $fundraising): bool => $fundraising === null);
    }
}
