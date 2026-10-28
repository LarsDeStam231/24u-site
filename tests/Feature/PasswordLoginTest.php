<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordLoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeded_admin_can_log_in_without_manual_approval(): void
    {
        $this->seed(AdminSeeder::class);

        $this->postJson(route('password.login.store'), ['email' => 'admin@example.com', 'password' => 'password'])->assertOk();

        $this->assertDatabaseHas('users', ['email' => 'admin@example.com', 'role' => 'admin', 'registration_status' => 'approved']);
        $this->assertAuthenticatedAs(User::where('email', 'admin@example.com')->firstOrFail());
    }

    public function test_default_login_page_uses_email_and_password_and_links_to_qr_login(): void
    {
        $this->get('/inloggen')->assertOk()->assertViewIs('site')
            ->assertViewHas('pageData.page.name', 'password-login')
            ->assertViewHas('pageData.page.submitUrl', url('/inloggen'))
            ->assertViewHas('pageData.page.canonical', url('/inloggen'))
            ->assertViewHas('pageData.links.login', url('/inloggen'))
            ->assertViewHas('pageData.links.qrLogin', url('/inloggen/qr'))
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $this->assertGuest();
    }

    public function test_old_password_login_page_redirects_to_default_login(): void
    {
        $this->get('/inloggen/wachtwoord')->assertRedirect('/inloggen');

        $this->assertGuest();
    }

    public function test_approved_accounts_can_log_in_without_a_qr_code(): void
    {
        $user = User::factory()->approved()->create(['role' => 'admin', 'email' => 'admin@example.com', 'password' => 'test-password']);
        $this->withSession(['qr_fingerprint' => 'old-qr-session']);
        $oldSessionId = session()->getId();

        $this->postJson('/inloggen', ['email' => 'ADMIN@example.com', 'password' => 'test-password'])
            ->assertOk()->assertJsonPath('redirect', route('account.index'))->assertSessionMissing('qr_fingerprint');

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->get(route('account.index'))->assertOk();
    }

    /** @return array<string, array{string, ?string, string, string}> */
    public static function invalidCredentials(): array
    {
        return [
            'wrong password' => ['approved', 'correct-password', 'wrong-password', 'user@example.com'],
            'unknown account' => ['approved', 'correct-password', 'correct-password', 'unknown@example.com'],
            'pending account' => ['pending', 'correct-password', 'correct-password', 'user@example.com'],
            'rejected account' => ['rejected', 'correct-password', 'correct-password', 'user@example.com'],
            'no password configured' => ['approved', null, 'test-password', 'user@example.com'],
        ];
    }

    #[DataProvider('invalidCredentials')]
    public function test_invalid_or_unapproved_accounts_cannot_log_in(string $status, ?string $storedPassword, string $password, string $email): void
    {
        User::factory()->create(['email' => 'user@example.com', 'registration_status' => $status, 'password' => $storedPassword]);

        $this->postJson(route('password.login.store'), ['email' => $email, 'password' => $password])
            ->assertUnprocessable()->assertInvalid(['email' => 'De inloggegevens kloppen niet of je account is nog niet goedgekeurd.']);

        $this->assertGuest();
    }

    public function test_email_and_password_are_required(): void
    {
        $this->postJson(route('password.login.store'), [])
            ->assertUnprocessable()->assertInvalid(['email' => 'required', 'password' => 'required']);

        $this->assertGuest();
    }

    public function test_invalid_email_addresses_are_rejected(): void
    {
        $this->postJson(route('password.login.store'), ['email' => 'not-an-email', 'password' => 'test-password'])
            ->assertUnprocessable()->assertInvalid(['email' => 'valid email address']);

        $this->assertGuest();
    }

    public function test_non_string_email_input_is_rejected_without_breaking_the_rate_limiter(): void
    {
        $this->postJson(route('password.login.store'), ['email' => ['user@example.com'], 'password' => 'test-password'])
            ->assertUnprocessable()->assertInvalid(['email' => 'string']);

        $this->assertGuest();
    }

    public function test_login_attempts_are_limited_per_email_and_ip(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('password.login.store'), ['email' => 'user@example.com', 'password' => 'wrong-password'])->assertUnprocessable();
        }

        $this->postJson(route('password.login.store'), ['email' => 'USER@example.com', 'password' => 'wrong-password'])->assertTooManyRequests();

        $this->postJson(route('password.login.store'), ['email' => 'another@example.com', 'password' => 'wrong-password'])->assertUnprocessable();
        $this->assertGuest();
    }
}
