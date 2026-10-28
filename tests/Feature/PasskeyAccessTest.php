<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasskeyChanged;
use CBOR\ByteStringObject;
use CBOR\Encoder;
use CBOR\MapObject;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\LaravelPasskeys\Models\Passkey;
use Tests\TestCase;

class PasskeyAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://localhost', 'passkeys.relying_party.id' => 'localhost']);
        URL::forceRootUrl('https://localhost');
    }

    public function test_registration_requires_settings_access_and_fresh_credentials_for_the_same_user(): void
    {
        $user = User::factory()->approved()->create(['password' => 'correct-password']);
        $other = User::factory()->approved()->create(['password' => 'other-password']);
        $this->postJson(route('passkeys.store'))->assertUnauthorized();
        $qr = $this->registrationQr($user);
        $this->postJson(route('passkeys.registration.options'), ['token' => $qr['token']])->assertForbidden();

        $this->postJson(route('passkeys.registration.authorize'), [...$qr, 'email' => $other->email, 'password' => 'other-password'])
            ->assertUnprocessable()->assertInvalid(['email']);
        $this->postJson(route('passkeys.registration.authorize'), [...$qr, 'email' => $user->email, 'password' => 'wrong-password'])
            ->assertUnprocessable()->assertInvalid(['email']);

        $this->assertDatabaseCount('passkeys', 0);
        $this->assertGuest();
    }

    public function test_a_real_registration_stores_the_public_credential_once_and_hides_it_from_settings(): void
    {
        Notification::fake();
        $user = User::factory()->approved()->create(['password' => 'correct-password']);
        $authenticator = $this->authenticator();
        $qr = $this->registrationQr($user);
        $this->authorizeRegistration($user, $qr);
        $options = $this->postJson(route('passkeys.registration.options'), ['token' => $qr['token']])->assertOk()
            ->assertJsonPath('authenticatorSelection.userVerification', 'required')->json();
        $data = ['token' => $qr['token'], 'name' => 'Mijn telefoon', 'credential' => $this->registrationCredential($options, $authenticator)];

        $this->postJson(route('passkeys.registration.store'), $data)->assertOk();

        $this->assertDatabaseCount('passkeys', 1);
        $this->assertDatabaseHas('passkeys', ['authenticatable_id' => $user->id, 'name' => 'Mijn telefoon', 'credential_id' => base64_encode($authenticator['id'])]);
        Notification::assertSentTo($user, PasskeyChanged::class);
        $this->assertGuest();
        $this->postJson(route('passkeys.registration.store'), $data)->assertGone();
        $this->actingAs($user)->get(route('passkeys.index'))->assertOk()
            ->assertViewHas('pageData.qr.passkeys.0.name', 'Mijn telefoon')
            ->assertDontSee(base64_encode($authenticator['id']))->assertDontSee('credentialPublicKey');
    }

    public function test_real_passkey_authentication_logs_in_only_the_original_browser_once(): void
    {
        Notification::fake();
        $user = User::factory()->approved()->create(['password' => 'correct-password']);
        $authenticator = $this->authenticator();
        $this->registerPasskey($user, $authenticator);
        $pair = $this->loginQr($user);
        $desktop = session()->all();
        $this->flushSession();
        $options = $this->postJson(route('qr.pair.options'), $pair)->assertOk()->assertJsonPath('userVerification', 'required')->json();

        $this->postJson(route('qr.pair.update'), ['token' => $pair['token'], 'credential' => $this->assertion($options, $authenticator)])->assertOk();

        $this->assertGuest();
        $this->flushSession();
        $this->postJson(route('qr.pair.consume'))->assertGone();
        $this->withSession($desktop);
        $oldSessionId = session()->getId();
        $this->postJson(route('qr.pair.consume'))->assertOk()->assertJsonPath('status', 'approved');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertNotNull($user->passkeys()->first()->last_used_at);
        $this->postJson(route('qr.pair.consume'))->assertGone();
    }

    /** @return array<string, array{string}> */
    public static function invalidAssertions(): array
    {
        return ['wrong origin' => ['origin'], 'wrong challenge' => ['challenge'], 'no local verification' => ['verification'], 'bad signature' => ['signature']];
    }

    #[DataProvider('invalidAssertions')]
    public function test_invalid_passkey_proofs_do_not_approve_login_and_cannot_be_replayed(string $defect): void
    {
        Notification::fake();
        $user = User::factory()->approved()->create(['password' => 'correct-password']);
        $authenticator = $this->authenticator();
        $this->registerPasskey($user, $authenticator);
        $pair = $this->loginQr($user);
        $options = $this->postJson(route('qr.pair.options'), $pair)->assertOk()->json();
        $data = ['token' => $pair['token'], 'credential' => $this->assertion($options, $authenticator, $defect)];

        $this->postJson(route('qr.pair.update'), $data)->assertUnprocessable()->assertInvalid(['credential']);

        $this->assertGuest();
        $this->postJson(route('qr.pair.update'), $data)->assertGone();
        $this->postJson(route('qr.pair.consume'))->assertOk()->assertJsonPath('status', 'pending');
    }

    public function test_registration_challenge_cannot_be_changed_by_client_options(): void
    {
        $user = User::factory()->approved()->create(['password' => 'correct-password']);
        $qr = $this->registrationQr($user);
        $this->authorizeRegistration($user, $qr);
        $options = $this->postJson(route('passkeys.registration.options'), ['token' => $qr['token']])->assertOk()->json();
        $options['challenge'] = $this->base64url('attacker-challenge');

        $this->postJson(route('passkeys.registration.store'), [
            'token' => $qr['token'], 'name' => 'Phone', 'options' => json_encode($options),
            'credential' => $this->registrationCredential($options, $this->authenticator()),
        ])->assertUnprocessable()->assertInvalid(['credential']);

        $this->assertDatabaseCount('passkeys', 0);
    }

    /** @return array<string, array{bool}> */
    public static function invalidRegistrations(): array
    {
        return ['wrong origin' => [false], 'no local verification' => [true]];
    }

    #[DataProvider('invalidRegistrations')]
    public function test_registration_requires_local_verification_and_the_configured_origin(bool $withoutVerification): void
    {
        $user = User::factory()->approved()->create(['password' => 'correct-password']);
        $qr = $this->registrationQr($user);
        $this->authorizeRegistration($user, $qr);
        $options = $this->postJson(route('passkeys.registration.options'), ['token' => $qr['token']])->assertOk()->json();
        $credential = json_decode($this->registrationCredential($options, $this->authenticator(), $withoutVerification), true);
        $clientData = json_decode(base64_decode(strtr($credential['response']['clientDataJSON'], '-_', '+/')), true);
        $clientData['origin'] = $withoutVerification ? 'https://localhost' : 'https://attacker.localhost';
        $credential['response']['clientDataJSON'] = $this->base64url(json_encode($clientData));

        $this->postJson(route('passkeys.registration.store'), ['token' => $qr['token'], 'name' => 'Phone', 'credential' => json_encode($credential)])
            ->assertUnprocessable();

        $this->assertDatabaseCount('passkeys', 0);
    }

    public function test_another_phone_cannot_use_the_registration_grant_or_challenge(): void
    {
        $user = User::factory()->approved()->create(['password' => 'correct-password']);
        $qr = $this->registrationQr($user);
        $this->authorizeRegistration($user, $qr);
        $options = $this->postJson(route('passkeys.registration.options'), ['token' => $qr['token']])->assertOk()->json();
        $this->flushSession();

        $this->postJson(route('passkeys.registration.store'), ['token' => $qr['token'], 'name' => 'Phone', 'credential' => $this->registrationCredential($options, $this->authenticator())])
            ->assertForbidden();

        $this->assertDatabaseCount('passkeys', 0);
    }

    public function test_passkey_deletion_requires_ownership_and_password_and_sends_a_notice(): void
    {
        Notification::fake();
        $user = User::factory()->approved()->create(['password' => 'correct-password']);
        $other = User::factory()->approved()->create();
        $key = $user->passkeys()->create(['name' => 'Phone', 'data' => Passkey::factory()->make()->data]);

        $this->actingAs($other)->deleteJson(route('passkeys.destroy', $key->id), ['password' => 'password'])->assertNotFound();
        $this->actingAs($user)->deleteJson(route('passkeys.destroy', $key->id), ['password' => 'wrong-password'])->assertUnprocessable();
        $this->assertModelExists($key);
        $this->deleteJson(route('passkeys.destroy', $key->id), ['password' => 'correct-password'])->assertOk();

        $this->assertModelMissing($key);
        Notification::assertSentTo($user, PasskeyChanged::class);
    }

    public function test_registration_qr_cannot_be_used_to_log_in_and_requires_account_approval(): void
    {
        $user = User::factory()->approved()->create(['password' => 'correct-password']);
        $qr = $this->registrationQr($user);

        $this->postJson(route('qr.pair.options'), $qr)->assertGone();
        $user->forceFill(['registration_status' => 'rejected'])->save();
        $this->actingAs($user)->postJson(route('passkeys.store'))->assertForbidden();

        $this->assertDatabaseCount('passkeys', 0);
    }

    public function test_expired_registration_qrs_and_changed_passwords_cannot_create_passkeys(): void
    {
        $this->freezeTime();
        $user = User::factory()->approved()->create(['password' => 'correct-password']);
        $qr = $this->registrationQr($user);
        $this->authorizeRegistration($user, $qr);
        $user->forceFill(['password' => 'changed-password'])->save();

        $this->postJson(route('passkeys.registration.options'), ['token' => $qr['token']])->assertForbidden();
        $this->travel(11)->minutes();
        $this->postJson(route('passkeys.registration.options'), ['token' => $qr['token']])->assertGone();

        $this->assertDatabaseCount('passkeys', 0);
    }

    public function test_a_passkey_registration_challenge_expires_before_the_qr_itself(): void
    {
        $this->freezeTime();
        $user = User::factory()->approved()->create(['password' => 'correct-password']);
        $qr = $this->registrationQr($user);
        $this->authorizeRegistration($user, $qr);
        $options = $this->postJson(route('passkeys.registration.options'), ['token' => $qr['token']])->assertOk()->json();
        $this->travel(3)->minutes();

        $this->postJson(route('passkeys.registration.store'), [
            'token' => $qr['token'], 'name' => 'Phone', 'credential' => $this->registrationCredential($options, $this->authenticator()),
        ])->assertGone();

        $this->assertDatabaseCount('passkeys', 0);
    }

    public function test_the_phone_must_reconfirm_credentials_after_the_registration_grant_expires(): void
    {
        $this->freezeTime();
        $user = User::factory()->approved()->create(['password' => 'correct-password']);
        $qr = $this->registrationQr($user);
        $this->authorizeRegistration($user, $qr);
        $this->travel(6)->minutes();

        $this->postJson(route('passkeys.registration.options'), ['token' => $qr['token']])->assertForbidden();

        $this->assertDatabaseCount('passkeys', 0);
    }

    /** @return array{token: string, code: string} */
    private function registrationQr(User $user): array
    {
        $data = $this->actingAs($user)->postJson(route('passkeys.store'))->assertOk()->json();
        Auth::logout();
        $this->flushSession();
        parse_str(parse_url($data['qr']['url'], PHP_URL_FRAGMENT), $fragment);

        return ['token' => $fragment['token'], 'code' => $data['code']];
    }

    /** @return array{token: string, code: string} */
    private function loginQr(User $user): array
    {
        $data = $this->postJson(route('qr.pair.store'), ['email' => $user->email])->assertOk()->json();
        parse_str(parse_url($data['qr']['url'], PHP_URL_FRAGMENT), $fragment);

        return ['token' => $fragment['token'], 'code' => $data['code']];
    }

    /** @param array{token: string, code: string} $qr */
    private function authorizeRegistration(User $user, array $qr): void
    {
        $this->postJson(route('passkeys.registration.authorize'), [...$qr, 'email' => $user->email, 'password' => 'correct-password'])->assertOk();
    }

    /** @param array{private: OpenSSLAsymmetricKey, id: string, public: string} $authenticator */
    private function registerPasskey(User $user, array &$authenticator): void
    {
        $authenticator['userHandle'] = (string) $user->id;
        $qr = $this->registrationQr($user);
        $this->authorizeRegistration($user, $qr);
        $options = $this->postJson(route('passkeys.registration.options'), ['token' => $qr['token']])->assertOk()->json();
        $this->postJson(route('passkeys.registration.store'), ['token' => $qr['token'], 'name' => 'Phone', 'credential' => $this->registrationCredential($options, $authenticator)])->assertOk();
        $this->flushSession();
    }

    /** @return array{private: OpenSSLAsymmetricKey, id: string, public: string} */
    private function authenticator(): array
    {
        $private = openssl_pkey_get_private(<<<'PEM'
-----BEGIN PRIVATE KEY-----
MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgaRf0QFHCWvmM1x5I
her7O8d9XOchx8zQ3/25MQCNyzWhRANCAAR69bqfDeTnh3dyZ4Q8dmxP8dUNqcWw
cCpDw4lORFue8oc7D2kY85BESLmRbD9C1ZRAE60iBl69YHgF0sj+rCng
-----END PRIVATE KEY-----
PEM);
        $details = openssl_pkey_get_details($private);
        $public = (new Encoder)->encode([
            1 => 2, 3 => -7, -1 => 1,
            -2 => ByteStringObject::create($details['ec']['x']), -3 => ByteStringObject::create($details['ec']['y']),
        ]);

        return ['private' => $private, 'id' => random_bytes(32), 'public' => $public];
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array{private: OpenSSLAsymmetricKey, id: string, public: string}  $authenticator
     */
    private function registrationCredential(array $options, array $authenticator, bool $withoutVerification = false): string
    {
        $authData = hash('sha256', 'localhost', true).chr($withoutVerification ? 0x41 : 0x45).pack('N', 0).str_repeat(chr(0), 16)
            .pack('n', strlen($authenticator['id'])).$authenticator['id'].$authenticator['public'];
        $attestation = (new Encoder)->encode(['fmt' => 'none', 'attStmt' => MapObject::create(), 'authData' => ByteStringObject::create($authData)]);

        return json_encode([
            'id' => $this->base64url($authenticator['id']), 'rawId' => $this->base64url($authenticator['id']), 'type' => 'public-key',
            'response' => [
                'clientDataJSON' => $this->base64url(json_encode(['type' => 'webauthn.create', 'challenge' => $options['challenge'], 'origin' => 'https://localhost', 'crossOrigin' => false])),
                'attestationObject' => $this->base64url($attestation), 'transports' => ['internal'],
            ], 'clientExtensionResults' => [],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array{private: OpenSSLAsymmetricKey, id: string, public: string}  $authenticator
     */
    private function assertion(array $options, array $authenticator, string $defect = ''): string
    {
        $client = json_encode([
            'type' => 'webauthn.get', 'challenge' => $defect === 'challenge' ? $this->base64url('wrong') : $options['challenge'],
            'origin' => $defect === 'origin' ? 'https://attacker.localhost' : 'https://localhost', 'crossOrigin' => false,
        ]);
        $authData = hash('sha256', 'localhost', true).chr($defect === 'verification' ? 0x01 : 0x05).pack('N', 1);
        openssl_sign($authData.hash('sha256', $client, true), $signature, $authenticator['private'], OPENSSL_ALGO_SHA256);

        return json_encode([
            'id' => $this->base64url($authenticator['id']), 'rawId' => $this->base64url($authenticator['id']), 'type' => 'public-key',
            'response' => [
                'clientDataJSON' => $this->base64url($client), 'authenticatorData' => $this->base64url($authData),
                'signature' => $this->base64url($defect === 'signature' ? 'wrong-signature' : $signature), 'userHandle' => $this->base64url($authenticator['userHandle']),
            ], 'clientExtensionResults' => [],
        ], JSON_THROW_ON_ERROR);
    }

    private function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
