<?php

namespace Tests\Feature;

use App\Models\Passkey;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Tests\TestCase;

class QrPairingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://localhost', 'passkeys.relying_party.id' => 'localhost']);
        URL::forceRootUrl('https://localhost');
    }

    public function test_computer_stays_logged_out_until_a_passkey_approves(): void
    {
        $user = User::factory()->approved()->create();
        Passkey::factory()->for($user, 'authenticatable')->create();
        $this->createPairing($user);

        $this->postJson(route('qr.pair.consume'))->assertOk()->assertJsonPath('status', 'pending');

        $this->assertGuest();
    }

    public function test_a_phone_can_authorize_only_the_original_computer_and_only_once(): void
    {
        $user = User::factory()->approved()->create();
        $key = Passkey::factory()->for($user, 'authenticatable')->create();
        $pair = $this->createPairing($user);
        $desktop = session()->all();
        $this->flushSession();
        $this->approve($pair, $key);
        $this->flushSession();
        $this->withSession($desktop);
        $oldSessionId = session()->getId();

        $this->postJson(route('qr.pair.consume'))->assertOk()->assertJsonPath('status', 'approved');

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->postJson(route('qr.pair.consume'))->assertGone();
    }

    public function test_another_browser_cannot_consume_an_approved_pairing(): void
    {
        $user = User::factory()->approved()->create();
        $key = Passkey::factory()->for($user, 'authenticatable')->create();
        $pair = $this->createPairing($user);
        $desktop = session()->all();
        $this->approve($pair, $key);
        $this->flushSession();
        $this->withSession([...$desktop, 'qr_login_owner' => 'not-the-owner']);

        $this->postJson(route('qr.pair.consume'))->assertGone();

        $this->assertGuest();
        $this->withSession($desktop)->postJson(route('qr.pair.consume'))->assertOk()->assertJsonPath('status', 'approved');
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_logged_in_phone_cannot_approve_without_a_passkey_ceremony(): void
    {
        $user = User::factory()->approved()->create();
        Passkey::factory()->for($user, 'authenticatable')->create();
        $pair = $this->createPairing($user);

        $this->actingAs($user)->postJson(route('qr.pair.update'), ['token' => $pair['token'], 'credential' => '{}'])->assertGone();

        $this->postJson(route('qr.pair.consume'))->assertOk()->assertJsonPath('status', 'pending');
    }

    public function test_phone_approval_requires_the_code_displayed_on_the_computer(): void
    {
        $user = User::factory()->approved()->create();
        Passkey::factory()->for($user, 'authenticatable')->create();
        $pair = $this->createPairing($user);

        $this->postJson(route('qr.pair.options'), [...$pair, 'code' => '000000'])
            ->assertUnprocessable()->assertJsonPath('message', 'Controleer de code op je computer.');

        $this->postJson(route('qr.pair.consume'))->assertOk()->assertJsonPath('status', 'pending');
    }

    public function test_a_passkey_for_another_account_cannot_approve_the_pairing(): void
    {
        $user = User::factory()->approved()->create();
        $other = User::factory()->approved()->create();
        Passkey::factory()->for($user, 'authenticatable')->create();
        $wrongKey = Passkey::factory()->for($other, 'authenticatable')->create();
        $pair = $this->createPairing($user);
        $this->postJson(route('qr.pair.options'), $pair)->assertOk();
        $this->mock(FindPasskeyToAuthenticateAction::class, fn (MockInterface $mock) => $mock->shouldReceive('execute')->once()->andReturn($wrongKey));

        $this->postJson(route('qr.pair.update'), ['token' => $pair['token'], 'credential' => '{}'])->assertUnprocessable();

        $this->postJson(route('qr.pair.consume'))->assertOk()->assertJsonPath('status', 'pending');
        $this->assertGuest();
    }

    public function test_an_expired_pairing_cannot_be_approved_or_consumed(): void
    {
        $this->freezeTime();
        $user = User::factory()->approved()->create();
        Passkey::factory()->for($user, 'authenticatable')->create();
        $pair = $this->createPairing($user);
        $this->travel(6)->minutes();

        $this->postJson(route('qr.pair.options'), $pair)->assertGone();
        $this->postJson(route('qr.pair.consume'))->assertGone();

        $this->assertGuest();
    }

    public function test_new_pairing_invalidates_the_previous_qr_for_the_same_computer(): void
    {
        $user = User::factory()->approved()->create();
        Passkey::factory()->for($user, 'authenticatable')->create();
        $first = $this->createPairing($user);
        $this->createPairing($user);

        $this->postJson(route('qr.pair.options'), $first)->assertGone();
    }

    public function test_revoked_credentials_cannot_complete_a_previously_approved_pairing(): void
    {
        $user = User::factory()->approved()->create();
        $key = Passkey::factory()->for($user, 'authenticatable')->create();
        $pair = $this->createPairing($user);
        $this->approve($pair, $key);
        $key->delete();

        $this->postJson(route('qr.pair.consume'))->assertForbidden();

        $this->assertGuest();
    }

    public function test_qr_login_cannot_start_without_an_existing_passkey_or_account_approval(): void
    {
        $withoutKey = User::factory()->approved()->create();
        $pending = User::factory()->create();
        Passkey::factory()->for($pending, 'authenticatable')->create();

        $this->postJson(route('qr.pair.store'), ['email' => $withoutKey->email])->assertUnprocessable();
        $this->postJson(route('qr.pair.store'), ['email' => $pending->email])->assertUnprocessable();

        $this->assertGuest();
    }

    public function test_login_qr_cannot_be_used_to_register_a_first_passkey(): void
    {
        $user = User::factory()->approved()->create();
        Passkey::factory()->for($user, 'authenticatable')->create();
        $pair = $this->createPairing($user);

        $this->postJson(route('passkeys.registration.authorize'), [...$pair, 'email' => $user->email, 'password' => 'password'])->assertGone();

        $this->assertDatabaseCount('passkeys', 1);
    }

    public function test_password_changes_and_withdrawn_approval_invalidate_pending_pairings(): void
    {
        $user = User::factory()->approved()->create();
        Passkey::factory()->for($user, 'authenticatable')->create();
        $pair = $this->createPairing($user);
        $user->forceFill(['password' => 'changed-password'])->save();

        $this->postJson(route('qr.pair.options'), $pair)->assertForbidden();
        $this->postJson(route('qr.pair.consume'))->assertForbidden();
        $user->forceFill(['registration_status' => 'rejected'])->save();
        $this->postJson(route('qr.pair.store'), ['email' => $user->email])->assertUnprocessable();
    }

    public function test_cancelling_a_pairing_prevents_phone_approval_and_browser_consumption(): void
    {
        $user = User::factory()->approved()->create();
        Passkey::factory()->for($user, 'authenticatable')->create();
        $pair = $this->createPairing($user);

        $this->deleteJson(route('qr.pair.destroy'))->assertOk();

        $this->postJson(route('qr.pair.options'), $pair)->assertGone();
        $this->postJson(route('qr.pair.consume'))->assertGone();
        $this->assertGuest();
    }

    public function test_malformed_qr_input_is_rejected_without_breaking_the_rate_limiter(): void
    {
        $this->postJson(route('qr.pair.options'), ['token' => ['unexpected-array'], 'code' => '123456'])
            ->assertUnprocessable()->assertInvalid(['token']);

        $this->assertGuest();
    }

    public function test_an_existing_passkey_logs_the_computer_in_without_a_password(): void
    {
        $user = User::factory()->approved()->create(['password' => null]);
        $key = Passkey::factory()->for($user, 'authenticatable')->create();
        $pair = $this->createPairing($user);
        $this->approve($pair, $key);

        $this->postJson(route('qr.pair.consume'))->assertOk()
            ->assertJsonPath('status', 'approved')->assertJsonPath('redirect', route('account.index'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('account.index'))->assertOk();
        $this->assertNull($user->fresh()->password);
    }

    public function test_temporary_polling_rate_limits_do_not_discard_a_successful_phone_approval(): void
    {
        $this->freezeTime();
        $user = User::factory()->approved()->create(['password' => null]);
        $key = Passkey::factory()->for($user, 'authenticatable')->create();
        $pair = $this->createPairing($user);
        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
        for ($request = 0; $request < 45; $request++) {
            $this->postJson(route('qr.pair.consume'))->assertOk()->assertJsonPath('status', 'pending');
        }
        $this->postJson(route('qr.pair.consume'))->assertTooManyRequests();
        $this->postJson(route('login.options'), ['email' => $user->email])->assertOk();
        $this->approve($pair, $key);
        $this->travel(61)->seconds();

        $this->postJson(route('qr.pair.consume'))->assertOk()->assertJsonPath('status', 'approved');

        $this->assertAuthenticatedAs($user);
        $this->get(route('account.index'))->assertOk();
    }

    /** @return array{token: string, code: string} */
    private function createPairing(User $user): array
    {
        $data = $this->postJson(route('qr.pair.store'), ['email' => $user->email])->assertOk()->json();
        parse_str(parse_url($data['qr']['url'], PHP_URL_FRAGMENT), $fragment);

        return ['token' => $fragment['token'], 'code' => $data['code']];
    }

    /** @param array{token: string, code: string} $pair */
    private function approve(array $pair, Passkey $passkey): void
    {
        $this->postJson(route('qr.pair.options'), $pair)->assertOk();
        $this->mock(FindPasskeyToAuthenticateAction::class, fn (MockInterface $mock) => $mock->shouldReceive('execute')->once()->andReturn($passkey));
        $this->postJson(route('qr.pair.update'), ['token' => $pair['token'], 'credential' => '{}'])->assertOk();
    }
}
