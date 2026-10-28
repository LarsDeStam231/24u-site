<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\LaravelPasskeys\Models\Passkey;
use Tests\TestCase;

class PasswordSetupTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{?string, bool, bool}> */
    public static function accountCredentials(): array
    {
        return [
            'password only' => ['existing-password', false, true],
            'no credentials' => [null, false, false],
            'passkey only' => [null, true, false],
            'both' => ['existing-password', true, true],
        ];
    }

    #[DataProvider('accountCredentials')]
    public function test_email_check_reports_available_methods_without_sending_mail(?string $password, bool $hasPasskey, bool $hasPassword): void
    {
        Notification::fake();
        $user = User::factory()->approved()->create(['password' => $password]);
        if ($hasPasskey) {
            $user->passkeys()->create(['name' => 'Phone', 'data' => Passkey::factory()->make()->data]);
        }

        $this->postJson(route('login.options'), ['email' => strtoupper($user->email)])->assertOk()
            ->assertExactJson(['hasPassword' => $hasPassword, 'hasPasskeys' => $hasPasskey]);

        $this->assertGuest();
        Notification::assertNothingSent();
    }

    public function test_unknown_and_unapproved_accounts_have_the_same_available_methods(): void
    {
        $pending = User::factory()->create(['password' => 'existing-password']);
        $pending->passkeys()->create(['name' => 'Phone', 'data' => Passkey::factory()->make()->data]);

        $this->postJson(route('login.options'), ['email' => $pending->email])->assertOk()
            ->assertExactJson(['hasPassword' => false, 'hasPasskeys' => false]);
        $this->postJson(route('login.options'), ['email' => 'unknown@example.com'])->assertOk()
            ->assertExactJson(['hasPassword' => false, 'hasPasskeys' => false]);
    }

    public function test_password_email_is_only_sent_when_requested_for_an_approved_account(): void
    {
        Notification::fake();
        $user = User::factory()->approved()->create(['password' => null]);
        $pending = User::factory()->create();

        $response = $this->postJson(route('password.email'), ['email' => $user->email])->assertAccepted();
        $this->postJson(route('password.email'), ['email' => $pending->email])->assertAccepted()->assertExactJson($response->json());
        $this->postJson(route('password.email'), ['email' => 'unknown@example.com'])->assertAccepted()->assertExactJson($response->json());

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $mail = $notification->toMail($user);
            $this->assertStringStartsWith(route('password.reset').'#token=', $mail->actionUrl);

            return true;
        });
        Notification::assertCount(1);
        $this->assertNull($user->fresh()->password);
        $this->assertGuest();
    }

    public function test_email_link_sets_a_hashed_password_once_and_creates_only_non_login_qr_codes(): void
    {
        $user = User::factory()->approved()->unverified()->create(['role' => 'student', 'password' => null, 'name' => null]);
        $token = Password::createToken($user);
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'a-safe-password-123', 'password_confirmation' => 'a-safe-password-123', 'name' => 'Sam'];

        $this->get(route('password.reset'))->assertOk()->assertViewHas('pageData.page.name', 'password-setup')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->postJson(route('password.update'), $data)->assertOk()->assertJsonPath('redirect', route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check($data['password'], $user->password));
        $this->assertNotSame($data['password'], $user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->attendance_token);
        $this->assertNotNull($user->donation_token);
        $this->assertNull($user->login_token_hash);
        $this->assertSame('Sam', $user->name);
        $this->assertGuest();
        $this->postJson(route('password.update'), $data)->assertUnprocessable()->assertInvalid(['token']);
    }

    public function test_expired_or_wrong_account_links_do_not_set_a_password(): void
    {
        $this->freezeTime();
        $user = User::factory()->approved()->create(['password' => null]);
        $other = User::factory()->approved()->create(['password' => null]);
        $token = Password::createToken($user);
        $data = ['email' => $other->email, 'token' => $token, 'password' => 'a-safe-password-123', 'password_confirmation' => 'a-safe-password-123'];

        $this->postJson(route('password.update'), $data)->assertUnprocessable();
        $this->travel(61)->minutes();
        $this->postJson(route('password.update'), [...$data, 'email' => $user->email])->assertUnprocessable();

        $this->assertNull($user->fresh()->password);
        $this->assertNull($other->fresh()->password);
    }

    public function test_revoked_approval_and_weak_passwords_cannot_complete_setup(): void
    {
        $user = User::factory()->approved()->create(['password' => null]);
        $token = Password::createToken($user);
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'short', 'password_confirmation' => 'short'];

        $this->postJson(route('password.update'), $data)->assertUnprocessable()->assertInvalid(['password']);
        $user->forceFill(['registration_status' => 'rejected'])->save();
        $this->postJson(route('password.update'), [...$data, 'password' => 'a-safe-password-123', 'password_confirmation' => 'a-safe-password-123'])
            ->assertUnprocessable();

        $this->assertNull($user->fresh()->password);
        $this->assertGuest();
    }

    public function test_a_multibyte_password_that_exceeds_the_hashing_limit_is_rejected_without_changing_the_account(): void
    {
        $user = User::factory()->approved()->create(['password' => null]);
        $token = Password::createToken($user);
        $password = str_repeat('🔒', 20);

        $this->postJson(route('password.update'), [
            'email' => $user->email, 'token' => $token, 'password' => $password, 'password_confirmation' => $password,
        ])->assertUnprocessable()->assertInvalid(['password']);

        $this->assertNull($user->fresh()->password);
    }

    public function test_password_mail_uses_the_configured_origin_not_an_untrusted_request_host(): void
    {
        Notification::fake();
        $user = User::factory()->approved()->create(['password' => null]);

        $this->postJson('http://attacker.example/wachtwoord/e-mail', ['email' => $user->email])->assertAccepted();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->assertStringStartsWith(rtrim(config('app.url'), '/').'/wachtwoord/instellen#', $notification->toMail($user)->actionUrl);

            return true;
        });
    }
}
