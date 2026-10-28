<?php

namespace Tests\Feature;

use App\Models\Donation;
use App\Models\Donor;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EventPagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{string, string, string, string}> */
    public static function pages(): array
    {
        return [
            'home' => ['/', 'home', 'home', 'Home'],
            'livestream' => ['/livestream', 'livestream.index', 'livestream', 'Livestream'],
            'doneren' => ['/doneren', 'donations.index', 'donations', 'Doneren'],
            'challenges' => ['/challenges', 'challenges.index', 'challenges', 'Challenges'],
            'programma' => ['/programma', 'program.index', 'program', 'Programma'],
            'sponsoren' => ['/sponsoren', 'sponsors.index', 'sponsors', 'Sponsoren'],
            'over ons' => ['/over-ons', 'about.index', 'about', 'Over ons'],
        ];
    }

    #[DataProvider('pages')]
    public function test_pages_are_directly_accessible_with_their_own_page_data(string $path, string $routeName, string $pageName, string $label): void
    {
        $response = $this->get($path);

        $response->assertOk()
            ->assertViewIs('site')
            ->assertViewHas('pageData.page.name', $pageName)
            ->assertViewHas('pageData.currentRoute', $routeName)
            ->assertViewHas('pageData.page.canonical', url($path))
            ->assertSee('<title>'.$label.' — DCTerra 24 Uur Live</title>', false)
            ->assertSee('window.eventPageData = ', false);
    }

    public function test_navigation_contains_real_laravel_page_links(): void
    {
        $response = $this->get('/');

        $response->assertOk()->assertViewHas('pageData.navigation', [
            ['label' => 'Home', 'href' => url('/'), 'route' => 'home'],
            ['label' => 'Livestream', 'href' => url('/livestream'), 'route' => 'livestream.index'],
            ['label' => 'Doneren', 'href' => url('/doneren'), 'route' => 'donations.index'],
            ['label' => 'Challenges', 'href' => url('/challenges'), 'route' => 'challenges.index'],
            ['label' => 'Programma', 'href' => url('/programma'), 'route' => 'program.index'],
            ['label' => 'Sponsoren', 'href' => url('/sponsoren'), 'route' => 'sponsors.index'],
            ['label' => 'Over ons', 'href' => url('/over-ons'), 'route' => 'about.index'],
        ]);
    }

    public function test_guests_see_login_and_registration_even_when_accounts_already_exist(): void
    {
        User::factory()->approved()->create();

        $response = $this->get('/');

        $response->assertOk()->assertViewHas('pageData.accountNavigation', [
            ['label' => 'Inloggen', 'href' => route('login'), 'route' => 'login'],
            ['label' => 'Registreren', 'href' => route('registration.create'), 'route' => 'registration.create'],
        ])->assertSee('>Inloggen</a>', false)
            ->assertSee('>Registreren</a>', false)
            ->assertDontSee('>Mijn account</a>', false);
        $this->assertGuest();
    }

    public function test_authenticated_users_only_see_my_account_in_the_account_navigation(): void
    {
        $user = User::factory()->approved()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk()->assertViewHas('pageData.accountNavigation', [
            ['label' => 'Mijn account', 'href' => route('account.index'), 'route' => 'account.index'],
        ])->assertSee('>Mijn account</a>', false)
            ->assertDontSee('>Inloggen</a>', false)
            ->assertDontSee('>Registreren</a>', false);
    }

    public function test_preview_content_comes_from_laravel_configuration_without_database_tables(): void
    {
        config(['event.event.name' => 'Het aangepaste evenement', 'event.event.fundraising.raisedCents' => 12345]);

        $response = $this->get('/');

        $response->assertOk()
            ->assertViewHas('pageData.event.name', 'Het aangepaste evenement')
            ->assertViewHas('pageData.event.fundraising.raisedCents', 12345)
            ->assertViewHas('pageData.event.fundraising.isPreview', true)
            ->assertViewHas('pageData.event.images.hero', asset('assets/event/studio-hero.jpg'));
    }

    public function test_laravel_data_is_safely_encoded_for_the_vue_bootstrap(): void
    {
        $introduction = '</script><script>window.injected = true</script>';
        config(['event.event.introduction' => $introduction]);

        $response = $this->get('/');

        $response->assertOk()
            ->assertViewHas('pageData.event.introduction', $introduction)
            ->assertDontSee($introduction, false);
    }

    public function test_live_counter_only_includes_completed_donations_without_exposing_donor_details(): void
    {
        config(['event.event.fundraising.isPreview' => false]);
        $donor = Donor::factory()->create(['email' => 'private-donor@example.com', 'phone' => '0612345678']);
        Donation::factory()->for($donor)->completed()->count(2)
            ->sequence(['amount' => '25.25'], ['amount' => '10.10'])->create();
        Donation::factory()->for($donor)->count(5)->sequence(
            ['status' => 'pending', 'payment_id' => 'private-payment-id'], ['status' => 'failed'], ['status' => 'cancelled'],
            ['status' => 'refunded'], ['status' => 'expired'],
        )->create(['amount' => '100.00']);

        $response = $this->get('/');

        $response->assertOk()
            ->assertViewHas('pageData.event.fundraising.raisedCents', 3535)
            ->assertViewHas('pageData.event.fundraising.isPreview', false)
            ->assertDontSee('private-donor@example.com')
            ->assertDontSee('0612345678')
            ->assertDontSee('private-payment-id');
    }

    public function test_sponsors_are_loaded_from_the_existing_table_and_grouped_in_name_order(): void
    {
        config(['event.event.fundraising.isPreview' => false]);
        Sponsor::factory()->count(3)->sequence(
            ['company_name' => 'Zebra', 'sponsorship_type' => 'gold'],
            ['company_name' => 'Alfa', 'sponsorship_type' => 'gold'],
            ['company_name' => 'Beta', 'sponsorship_type' => 'silver'],
        )->create();

        $response = $this->get('/sponsoren');

        $response->assertOk()
            ->assertViewHas('pageData.event.sponsorTiers.0.sponsors', [
                ['name' => 'Alfa', 'url' => null, 'logo' => null],
                ['name' => 'Zebra', 'url' => null, 'logo' => null],
            ])
            ->assertViewHas('pageData.event.sponsorTiers.1.sponsors', [['name' => 'Beta', 'url' => null, 'logo' => null]])
            ->assertViewHas('pageData.event.sponsorTiers.2.sponsors', []);
    }

    public function test_live_counter_starts_at_zero_when_there_are_no_donations(): void
    {
        config(['event.event.fundraising.isPreview' => false]);

        $response = $this->get('/doneren');

        $response->assertOk()->assertViewHas('pageData.event.fundraising.raisedCents', 0);
    }

    public function test_unknown_page_returns_not_found(): void
    {
        $this->get('/bestaat-niet')->assertNotFound();
    }
}
