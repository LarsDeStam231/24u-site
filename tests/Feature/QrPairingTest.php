<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class QrPairingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_computer_stays_logged_out_until_the_phone_approves(): void
    {
        $this->createPairing();

        $this->postJson(route('qr.pair.consume'))->assertOk()->assertJsonPath('status', 'pending');

        $this->assertGuest();
    }

    public function test_a_phone_can_authorize_only_the_original_computer_and_only_once(): void
    {
        $student = User::factory()->approved()->create(['role' => 'student', 'login_token_hash' => str_repeat('a', 64)]);
        $pairing = $this->createPairing();
        $desktopSession = session()->all();
        $this->actingAs($student)->postJson(route('qr.pair.update'), ['token' => $pairing['token'], 'code' => $pairing['code']])->assertOk();
        Auth::logout();
        $this->flushSession();
        $this->withSession($desktopSession);
        $oldSessionId = session()->getId();

        $this->postJson(route('qr.pair.consume'))->assertOk()->assertJsonPath('status', 'approved');

        $this->assertAuthenticatedAs($student);
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->postJson(route('qr.pair.consume'))->assertGone();
    }

    public function test_another_browser_cannot_consume_an_approved_pairing(): void
    {
        $student = User::factory()->approved()->create(['login_token_hash' => str_repeat('a', 64)]);
        $pairing = $this->createPairing();
        $desktopSession = session()->all();
        $this->actingAs($student)->postJson(route('qr.pair.update'), ['token' => $pairing['token'], 'code' => $pairing['code']])->assertOk();
        Auth::logout();
        $this->flushSession();
        $this->withSession([...$desktopSession, 'qr_pairing_owner' => 'not-the-owner']);

        $this->postJson(route('qr.pair.consume'))->assertGone();

        $this->assertGuest();
        $this->withSession($desktopSession)->postJson(route('qr.pair.consume'))->assertOk()->assertJsonPath('status', 'approved');
        $this->assertAuthenticatedAs($student);
    }

    public function test_phone_approval_requires_an_authenticated_account(): void
    {
        $pairing = $this->createPairing();

        $this->postJson(route('qr.pair.update'), ['token' => $pairing['token'], 'code' => $pairing['code']])->assertUnauthorized();

        $this->postJson(route('qr.pair.consume'))->assertJsonPath('status', 'pending');
    }

    public function test_phone_approval_requires_the_code_displayed_on_the_computer(): void
    {
        $student = User::factory()->approved()->create();
        $pairing = $this->createPairing();

        $this->actingAs($student)->postJson(route('qr.pair.update'), ['token' => $pairing['token'], 'code' => '000000'])
            ->assertUnprocessable()->assertJsonPath('message', 'Controleer de code op je computer.');

        $this->postJson(route('qr.pair.consume'))->assertJsonPath('status', 'pending');
    }

    public function test_student_only_pairing_refuses_other_roles(): void
    {
        $guest = User::factory()->approved()->create(['role' => 'gast']);
        $pairing = $this->createPairing(true);

        $this->actingAs($guest)->postJson(route('qr.pair.update'), ['token' => $pairing['token'], 'code' => $pairing['code']])->assertForbidden();

        $this->postJson(route('qr.pair.consume'))->assertJsonPath('status', 'pending');
    }

    public function test_an_expired_pairing_cannot_be_approved_or_consumed(): void
    {
        $student = User::factory()->approved()->create();
        $pairing = $this->createPairing();
        $this->travel(3)->minutes();

        $this->actingAs($student)->postJson(route('qr.pair.update'), ['token' => $pairing['token'], 'code' => $pairing['code']])->assertGone();
        $this->postJson(route('qr.pair.consume'))->assertGone();
    }

    public function test_new_pairing_invalidates_the_previous_qr_for_the_same_computer(): void
    {
        $student = User::factory()->approved()->create();
        $first = $this->createPairing();
        $this->createPairing();

        $this->actingAs($student)->postJson(route('qr.pair.update'), ['token' => $first['token'], 'code' => $first['code']])->assertGone();
    }

    public function test_revoked_credentials_cannot_complete_a_previously_approved_pairing(): void
    {
        $student = User::factory()->approved()->create(['login_token_hash' => str_repeat('a', 64)]);
        $pairing = $this->createPairing();
        $desktopSession = session()->all();
        $this->actingAs($student)->postJson(route('qr.pair.update'), ['token' => $pairing['token'], 'code' => $pairing['code']])->assertOk();
        $student->forceFill(['login_token_hash' => str_repeat('b', 64)])->save();
        Auth::logout();
        $this->flushSession();
        $this->withSession($desktopSession);

        $this->postJson(route('qr.pair.consume'))->assertForbidden();

        $this->assertGuest();
    }

    /** @return array<string, mixed> */
    private function createPairing(bool $studentOnly = false): array
    {
        $data = $this->postJson(route('qr.pair.store'), ['student_only' => $studentOnly])->assertOk()->json();
        parse_str(parse_url($data['qr']['url'], PHP_URL_FRAGMENT), $fragment);

        return [...$data, 'token' => $fragment['token']];
    }
}
