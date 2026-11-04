<?php

namespace Tests\Feature;

use App\Models\FundraisingChallenge;
use App\Models\ProgramItem;
use App\Models\Sponsor;
use App\Models\User;
use Database\Seeders\EventContentSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountContentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_organization_can_load_the_initial_content_catalog(): void
    {
        $organization = User::factory()->approved()->create(['role' => 'organisatie']);

        $this->actingAs($organization)->getJson(route('account.content.index'))->assertOk()
            ->assertJsonCount(20, 'program')->assertJsonCount(33, 'challenges')
            ->assertJsonPath('program.0.title', 'Officiële opening')->assertJsonPath('challenges.0.amountCents', 5000);

        $this->assertDatabaseCount('program_items', 20);
        $this->assertDatabaseCount('fundraising_challenges', 33);
    }

    public function test_organization_can_add_a_program_item_after_the_existing_items(): void
    {
        $organization = User::factory()->approved()->create(['role' => 'organisatie']);
        ProgramItem::factory()->create(['sort_order' => 7]);

        $response = $this->actingAs($organization)->postJson(route('account.content.program.store'), $this->programPayload());

        $response->assertCreated()->assertJsonPath('item.sortOrder', 8)->assertJsonPath('item.presenter', 'Kevin');
        $this->assertDatabaseHas('program_items', [
            'id' => $response->json('item.id'), 'title' => 'Nieuwe uitzending', 'sort_order' => 8,
            'event_date' => '2026-12-17', 'presenter' => 'Kevin',
        ]);
    }

    public function test_organization_can_update_program_details_and_the_public_page(): void
    {
        $organization = User::factory()->approved()->create(['role' => 'organisatie']);
        $item = ProgramItem::factory()->create(['sort_order' => 5]);

        $this->actingAs($organization)->patchJson(route('account.content.program.update', $item), [
            ...$this->programPayload(), 'sortOrder' => 2, 'production' => 'Regie A',
            'responsible' => 'Team A', 'materials' => 'Microfoon', 'donationChallenge' => 'Finale',
        ])->assertOk()->assertJsonPath('item.sortOrder', 2);

        $this->assertDatabaseHas('program_items', ['id' => $item->id, 'sort_order' => 2, 'production' => 'Regie A']);
        $this->get('/programma')->assertOk()
            ->assertViewHas('pageData.event.schedule.0.title', 'Nieuwe uitzending')
            ->assertViewHas('pageData.event.schedule.0.donationChallenge', 'Finale');
    }

    public function test_omitting_program_order_preserves_the_existing_order(): void
    {
        $organization = User::factory()->approved()->create(['role' => 'organisatie']);
        $item = ProgramItem::factory()->create(['sort_order' => 5]);

        $this->actingAs($organization)->patchJson(route('account.content.program.update', $item), $this->programPayload())
            ->assertOk()->assertJsonPath('item.sortOrder', 5);

        $this->assertDatabaseHas('program_items', ['id' => $item->id, 'sort_order' => 5]);
    }

    public function test_organization_can_remove_a_program_item_but_not_the_last_one(): void
    {
        $organization = User::factory()->approved()->create(['role' => 'organisatie']);
        $items = ProgramItem::factory()->count(2)->create();

        $this->actingAs($organization)->deleteJson(route('account.content.program.destroy', $items[0]))->assertOk();

        $this->assertModelMissing($items[0]);
        $this->deleteJson(route('account.content.program.destroy', $items[1]))->assertUnprocessable()
            ->assertJsonPath('message', 'Er moet minimaal één programmaonderdeel blijven staan.');
        $this->assertModelExists($items[1]);
    }

    public function test_invalid_program_input_does_not_create_an_item(): void
    {
        $organization = User::factory()->approved()->create(['role' => 'organisatie']);

        $this->actingAs($organization)->postJson(route('account.content.program.store'), [
            'date' => '17-12-2026', 'time' => str_repeat('a', 33), 'title' => '',
            'description' => '', 'sortOrder' => -1, 'presenter' => str_repeat('a', 256),
        ])->assertUnprocessable()->assertInvalid(['date', 'time', 'title', 'description', 'sortOrder', 'presenter']);

        $this->assertDatabaseCount('program_items', 0);
    }

    public function test_organization_can_edit_challenge_text_without_changing_the_amount(): void
    {
        $organization = User::factory()->approved()->create(['role' => 'organisatie']);
        $challenge = FundraisingChallenge::factory()->create(['amount_cents' => 5000]);

        $this->actingAs($organization)->patchJson(route('account.content.challenges.update', $challenge), [
            'name' => 'Nieuwe naam', 'description' => 'Nieuwe beschrijving.',
        ])->assertOk()->assertJsonPath('challenge.amountCents', 5000);

        $this->assertDatabaseHas('fundraising_challenges', ['id' => $challenge->id, 'name' => 'Nieuwe naam', 'amount_cents' => 5000]);
        $this->get('/challenges')->assertOk()->assertViewHas('pageData.event.challenges.0.name', 'Nieuwe naam');
    }

    public function test_organization_cannot_change_challenge_amounts_or_modify_the_ladder(): void
    {
        $organization = User::factory()->approved()->create(['role' => 'organisatie']);
        $challenge = FundraisingChallenge::factory()->create(['amount_cents' => 5000, 'name' => 'Origineel']);

        $this->actingAs($organization)->patchJson(route('account.content.challenges.update', $challenge), [
            'amountCents' => 7500, 'name' => 'Verboden wijziging', 'description' => 'Beschrijving.',
        ])->assertUnprocessable()->assertInvalid(['amountCents']);
        $this->postJson(route('account.content.challenges.store'), [
            'amountCents' => 7500, 'name' => 'Nieuw', 'description' => 'Beschrijving.',
        ])->assertForbidden();
        $this->deleteJson(route('account.content.challenges.destroy', $challenge))->assertForbidden();

        $this->assertDatabaseCount('fundraising_challenges', 1);
        $this->assertDatabaseHas('fundraising_challenges', ['id' => $challenge->id, 'amount_cents' => 5000, 'name' => 'Origineel']);
    }

    public function test_admin_can_create_update_and_remove_challenge_milestones(): void
    {
        $admin = User::factory()->approved()->create(['role' => 'admin']);
        FundraisingChallenge::factory()->create(['amount_cents' => 5000]);

        $response = $this->actingAs($admin)->postJson(route('account.content.challenges.store'), [
            'amountCents' => 7500, 'name' => 'Nieuwe challenge', 'description' => 'Beschrijving.',
        ])->assertCreated()->assertJsonPath('challenge.amountCents', 7500);
        $id = $response->json('challenge.id');
        $this->assertDatabaseHas('fundraising_challenges', ['id' => $id, 'amount_cents' => 7500]);

        $this->patchJson(route('account.content.challenges.update', $id), [
            'amountCents' => 10000, 'name' => 'Bijgewerkt', 'description' => 'Nieuwe beschrijving.',
        ])->assertOk()->assertJsonPath('challenge.amountCents', 10000);
        $this->assertDatabaseHas('fundraising_challenges', ['id' => $id, 'amount_cents' => 10000, 'sort_order' => 10000]);

        $this->deleteJson(route('account.content.challenges.destroy', $id))->assertOk();
        $this->assertDatabaseMissing('fundraising_challenges', ['id' => $id]);
    }

    public function test_admin_cannot_remove_the_last_challenge(): void
    {
        $admin = User::factory()->approved()->create(['role' => 'admin']);
        $challenge = FundraisingChallenge::factory()->create();

        $this->actingAs($admin)->deleteJson(route('account.content.challenges.destroy', $challenge))->assertUnprocessable()
            ->assertJsonPath('message', 'Er moet minimaal één challenge blijven staan.');

        $this->assertModelExists($challenge);
    }

    /** @return array<string, array{int|float}> */
    public static function invalidAmounts(): array
    {
        return ['zero' => [0], 'above goal' => [1000001], 'fractional cent' => [12.5]];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_challenge_amounts_return_422_without_creating_a_milestone(int|float $amount): void
    {
        $admin = User::factory()->approved()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson(route('account.content.challenges.store'), [
            'amountCents' => $amount, 'name' => 'Ongeldig', 'description' => 'Beschrijving.',
        ])->assertUnprocessable()->assertInvalid(['amountCents']);

        $this->assertDatabaseCount('fundraising_challenges', 0);
    }

    public function test_duplicate_challenge_amounts_are_rejected_but_an_unchanged_amount_can_be_saved(): void
    {
        $admin = User::factory()->approved()->create(['role' => 'admin']);
        $challenge = FundraisingChallenge::factory()->create(['amount_cents' => 5000]);
        $payload = ['amountCents' => 5000, 'name' => 'Nieuwe naam', 'description' => 'Nieuwe tekst.'];

        $this->actingAs($admin)->postJson(route('account.content.challenges.store'), $payload)
            ->assertUnprocessable()->assertInvalid(['amountCents']);
        $this->patchJson(route('account.content.challenges.update', $challenge), $payload)->assertOk();

        $this->assertDatabaseCount('fundraising_challenges', 1);
        $this->assertDatabaseHas('fundraising_challenges', ['id' => $challenge->id, 'name' => 'Nieuwe naam', 'amount_cents' => 5000]);
    }

    public function test_organization_can_manage_sponsors_and_publish_them_on_the_website(): void
    {
        $organization = User::factory()->approved()->create(['role' => 'organisatie']);

        $response = $this->actingAs($organization)->postJson(route('account.content.sponsors.store'), [
            'companyName' => 'Sponsor A', 'sponsorshipType' => 'gold', 'companyType' => '',
        ])->assertCreated();
        $id = $response->json('sponsor.id');
        $this->assertDatabaseHas('sponsor', ['id' => $id, 'company_name' => 'Sponsor A', 'company_type' => '']);

        $this->patchJson(route('account.content.sponsors.update', $id), [
            'companyName' => 'Sponsor A', 'sponsorshipType' => 'silver', 'companyType' => 'Horeca',
        ])->assertOk();
        $this->assertDatabaseHas('sponsor', ['id' => $id, 'sponsorship_type' => 'silver', 'company_type' => 'Horeca']);
        $this->get('/sponsoren')->assertOk()->assertViewHas('pageData.event.sponsorTiers.1.sponsors.0.name', 'Sponsor A');

        $this->deleteJson(route('account.content.sponsors.destroy', $id))->assertOk();
        $this->assertDatabaseMissing('sponsor', ['id' => $id]);
    }

    public function test_organization_can_upload_replace_and_remove_a_sponsor_logo(): void
    {
        Storage::fake('public');
        $organization = User::factory()->approved()->create(['role' => 'organisatie']);

        $response = $this->actingAs($organization)->post(route('account.content.sponsors.store'), [
            'companyName' => 'Sponsor A', 'sponsorshipType' => 'gold', 'logo' => $this->fakeLogo('logo.png'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $sponsor = Sponsor::query()->findOrFail($response->json('sponsor.id'));
        $firstLogo = $sponsor->logo_path;
        Storage::disk('public')->assertExists($firstLogo);
        $response->assertJsonPath('sponsor.logoUrl', Storage::disk('public')->url($firstLogo));
        $this->get('/sponsoren')->assertViewHas('pageData.event.sponsorTiers.0.sponsors.0.logo', Storage::disk('public')->url($firstLogo));

        $this->post(route('account.content.sponsors.update', $sponsor), [
            '_method' => 'PATCH', 'companyName' => 'Sponsor A', 'sponsorshipType' => 'gold',
            'logo' => $this->fakeLogo('nieuw.png'),
        ], ['Accept' => 'application/json'])->assertOk();
        $secondLogo = $sponsor->fresh()->logo_path;
        Storage::disk('public')->assertMissing($firstLogo);
        Storage::disk('public')->assertExists($secondLogo);

        $this->patchJson(route('account.content.sponsors.update', $sponsor), [
            'companyName' => 'Sponsor A', 'sponsorshipType' => 'gold', 'removeLogo' => true,
        ])->assertOk()->assertJsonPath('sponsor.logoUrl', null);
        Storage::disk('public')->assertMissing($secondLogo);
    }

    public function test_deleting_a_sponsor_removes_its_logo_and_non_images_are_rejected(): void
    {
        Storage::fake('public');
        $organization = User::factory()->approved()->create(['role' => 'organisatie']);
        $sponsor = Sponsor::factory()->create(['logo_path' => $this->fakeLogo('logo.png')->store('sponsors', 'public')]);

        $this->actingAs($organization)->post(route('account.content.sponsors.store'), [
            'companyName' => 'Sponsor B', 'sponsorshipType' => 'gold',
            'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertInvalid(['logo']);

        $this->deleteJson(route('account.content.sponsors.destroy', $sponsor))->assertOk();
        Storage::disk('public')->assertMissing($sponsor->logo_path);
    }

    public function test_invalid_sponsor_details_do_not_create_a_sponsor(): void
    {
        $organization = User::factory()->approved()->create(['role' => 'organisatie']);

        $this->actingAs($organization)->postJson(route('account.content.sponsors.store'), [
            'companyName' => '', 'sponsorshipType' => 'platinum', 'companyType' => 'Onbekend',
        ])->assertUnprocessable()->assertInvalid(['companyName', 'sponsorshipType', 'companyType']);

        $this->assertDatabaseCount('sponsor', 0);
    }

    public function test_duplicate_sponsor_names_are_rejected(): void
    {
        $organization = User::factory()->approved()->create(['role' => 'organisatie']);
        Sponsor::factory()->create(['company_name' => 'Sponsor A']);

        $this->actingAs($organization)->postJson(route('account.content.sponsors.store'), [
            'companyName' => 'Sponsor A', 'sponsorshipType' => 'gold',
        ])->assertUnprocessable()->assertInvalid(['companyName']);

        $this->assertDatabaseCount('sponsor', 1);
    }

    private function fakeLogo(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    }

    /** @return array<string, array{string, string, bool, bool}> */
    public static function contentPermissions(): array
    {
        return [
            'approved admin' => ['admin', 'approved', true, true],
            'pending admin' => ['admin', 'pending', false, false],
            'rejected admin' => ['admin', 'rejected', false, false],
            'approved organization' => ['organisatie', 'approved', true, false],
            'pending organization' => ['organisatie', 'pending', false, false],
            'volunteer' => ['vrijwilliger', 'approved', false, false],
            'student' => ['student', 'approved', false, false],
            'donor' => ['donateur', 'approved', false, false],
            'guest account' => ['gast', 'approved', false, false],
        ];
    }

    #[DataProvider('contentPermissions')]
    public function test_content_and_challenge_ladder_permissions_are_enforced(string $role, string $status, bool $content, bool $ladder): void
    {
        $user = User::factory()->make(['role' => $role, 'registration_status' => $status]);

        $this->assertSame($content, Gate::forUser($user)->allows('manage-event-content'));
        $this->assertSame($ladder, Gate::forUser($user)->allows('manage-challenge-ladder'));
    }

    /** @return array<string, array{string, string, ?string}> */
    public static function contentEndpoints(): array
    {
        return [
            'catalog' => ['getJson', 'account.content.index', null],
            'add program' => ['postJson', 'account.content.program.store', null],
            'update program' => ['patchJson', 'account.content.program.update', 'program'],
            'delete program' => ['deleteJson', 'account.content.program.destroy', 'program'],
            'add challenge' => ['postJson', 'account.content.challenges.store', null],
            'update challenge' => ['patchJson', 'account.content.challenges.update', 'challenge'],
            'delete challenge' => ['deleteJson', 'account.content.challenges.destroy', 'challenge'],
            'add sponsor' => ['postJson', 'account.content.sponsors.store', null],
            'update sponsor' => ['patchJson', 'account.content.sponsors.update', 'sponsor'],
            'delete sponsor' => ['deleteJson', 'account.content.sponsors.destroy', 'sponsor'],
        ];
    }

    #[DataProvider('contentEndpoints')]
    public function test_student_access_to_each_content_endpoint_returns_403(string $method, string $routeName, ?string $resource): void
    {
        $student = User::factory()->approved()->create(['role' => 'student']);
        $model = match ($resource) {
            'program' => ProgramItem::factory()->create(),
            'challenge' => FundraisingChallenge::factory()->create(),
            'sponsor' => Sponsor::factory()->create(),
            default => null,
        };

        $this->actingAs($student)->{$method}(route($routeName, $model))->assertForbidden();

        if ($model !== null) {
            $this->assertModelExists($model);
        }
    }

    public function test_guests_receive_401_instead_of_accessing_content_management(): void
    {
        $this->getJson(route('account.content.index'))->assertUnauthorized();
        $this->postJson(route('account.content.program.store'), $this->programPayload())->assertUnauthorized();

        $this->assertDatabaseCount('program_items', 0);
    }

    public function test_unapproved_organization_receives_403_and_cannot_change_content(): void
    {
        $organization = User::factory()->create(['role' => 'organisatie']);

        $this->actingAs($organization)->postJson(route('account.content.program.store'), $this->programPayload())->assertForbidden();

        $this->assertDatabaseCount('program_items', 0);
    }

    public function test_organization_cannot_approve_accounts_or_grant_admin_roles(): void
    {
        Mail::fake();
        $organization = User::factory()->approved()->create(['role' => 'organisatie']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($organization)->getJson(route('account.requests'))->assertForbidden();
        $this->patchJson(route('account.approve', $student), ['role' => 'admin', 'status' => 'approved'])->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $student->id, 'role' => 'student', 'registration_status' => 'pending']);
        Mail::assertNothingSent();
    }

    public function test_account_page_exposes_content_controls_without_removing_passkey_settings(): void
    {
        $organization = User::factory()->approved()->create(['role' => 'organisatie']);

        $this->actingAs($organization)->get(route('account.index'))->assertOk()
            ->assertViewHas('pageData.qr.canManageContent', true)
            ->assertViewHas('pageData.qr.canManageChallengeLadder', false)
            ->assertViewHas('pageData.qr.canManage', false)
            ->assertViewHas('pageData.qr.canScan', true)
            ->assertViewHas('pageData.qr.endpoints.content', route('account.content.index'))
            ->assertViewHas('pageData.qr.endpoints.passkeys', route('passkeys.index'));
    }

    public function test_existing_seeded_edits_are_not_overwritten_when_loading_management(): void
    {
        $this->seed(EventContentSeeder::class);
        $organization = User::factory()->approved()->create(['role' => 'organisatie']);
        ProgramItem::query()->where('slug', 'opening')->update(['title' => 'Eigen opening']);

        $this->actingAs($organization)->getJson(route('account.content.index'))->assertOk()->assertJsonPath('program.0.title', 'Eigen opening');

        $this->assertDatabaseCount('program_items', 20);
    }

    /** @return array{date: string, time: string, title: string, description: string, presenter: string} */
    private function programPayload(): array
    {
        return [
            'date' => '2026-12-17', 'time' => '08:00–09:00', 'title' => 'Nieuwe uitzending',
            'description' => 'De laatste challenges.', 'presenter' => 'Kevin',
        ];
    }
}
