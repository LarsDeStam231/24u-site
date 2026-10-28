<?php

namespace Tests\Feature;

use App\Mail\QrActivationMail;
use App\Models\User;
use App\QrCredentials;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QrAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_qr_login_has_its_own_page_and_links_back_to_password_login(): void
    {
        $this->get('/inloggen/qr')->assertOk()->assertViewIs('site')
            ->assertViewHas('pageData.page.name', 'qr')
            ->assertViewHas('pageData.qr.mode', 'login')
            ->assertViewHas('pageData.qr.endpoints.login', url('/inloggen/qr'))
            ->assertViewHas('pageData.qr.endpoints.loginPage', url('/inloggen/qr'))
            ->assertViewHas('pageData.links.passwordLogin', url('/inloggen'))
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $this->assertGuest();
    }

    public function test_registration_has_a_separate_public_vue_page_without_creating_an_account(): void
    {
        $response = $this->get(route('registration.create'));

        $response->assertOk()->assertViewIs('site')
            ->assertViewHas('pageData.page.name', 'registration')
            ->assertViewHas('pageData.page.submitUrl', route('registration.store'))
            ->assertViewHas('pageData.page.canonical', route('registration.create'))
            ->assertViewHas('pageData.links.login', route('login'));
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_uses_only_the_email_and_waits_for_approval(): void
    {
        $this->postJson(route('registration.store'), ['email' => 'P12345@example.com', 'role' => 'admin',
            'registration_status' => 'approved', 'login_token_hash' => str_repeat('a', 64)])
            ->assertAccepted();

        $this->assertDatabaseHas('users', ['email' => 'p12345@example.com', 'role' => 'gast',
            'registration_status' => 'pending', 'name' => null, 'login_token_hash' => null]);
        $this->assertGuest();
    }

    public function test_repeated_registration_does_not_reset_an_existing_account(): void
    {
        $user = User::factory()->approved()->create(['email' => 'student@example.com', 'role' => 'student']);

        $this->postJson(route('registration.store'), ['email' => $user->email])->assertAccepted();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'registration_status' => 'approved', 'role' => 'student']);
    }

    public function test_registration_rejects_an_invalid_email_address(): void
    {
        $this->postJson(route('registration.store'), ['email' => 'not-an-email'])
            ->assertUnprocessable()->assertInvalid(['email' => 'valid email address']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_invitation_only_emails_approved_accounts_and_does_not_reset_existing_codes(): void
    {
        Mail::fake();
        $user = User::factory()->approved()->create(['login_token_hash' => str_repeat('a', 64)]);
        $pending = User::factory()->create();

        $response = $this->postJson(route('qr.invite'), ['email' => $user->email])->assertAccepted();
        $this->postJson(route('qr.invite'), ['email' => $pending->email])->assertExactJson($response->json());
        $this->postJson(route('qr.invite'), ['email' => 'unknown@example.com'])->assertExactJson($response->json());

        Mail::assertSent(QrActivationMail::class, fn (QrActivationMail $mail): bool => $mail->hasTo($user->email));
        Mail::assertSentCount(1);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'login_token_hash' => str_repeat('a', 64)]);
    }

    public function test_activation_issues_only_attendance_and_donation_codes_once_without_logging_in(): void
    {
        Mail::fake();
        $user = User::factory()->approved()->unverified()->create(['role' => 'student', 'email' => 'p12345@example.com']);
        $token = $this->invitationToken($user);
        $this->get(route('qr.activate'))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertGuest();

        $response = $this->postJson(route('qr.activate.store'), ['token' => $token, 'name' => 'Sam'])
            ->assertOk()->assertJsonStructure(['codes' => ['attendance' => ['url', 'image'], 'donation']])->assertJsonMissingPath('codes.login');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Sam', 'student_number' => 'p12345',
            'login_token_hash' => null]);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertGuest();
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $response->json('codes.attendance.image'));
        $svg = base64_decode(substr($response->json('codes.attendance.image'), 26));
        $this->assertStringContainsString('<svg', $svg);
        $this->postJson(route('qr.activate.store'), ['token' => $token, 'name' => 'Other'])
            ->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Sam']);
    }

    public function test_reactivation_preserves_attendance_and_donation_codes_but_does_not_reenable_old_login_tokens(): void
    {
        Mail::fake();
        $oldToken = str_repeat('a', 64);
        $user = User::factory()->approved()->create(['role' => 'student', 'login_token_hash' => hash('sha256', $oldToken),
            'attendance_token' => str_repeat('b', 64), 'donation_token' => str_repeat('c', 64)]);
        $token = $this->invitationToken($user);

        $this->postJson(route('qr.activate.store'), ['token' => $token, 'name' => 'Sam'])->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'attendance_token' => str_repeat('b', 64), 'donation_token' => str_repeat('c', 64)]);
        $this->postJson(route('login.store'), ['token' => $oldToken])->assertGone();
    }

    public function test_activation_for_non_students_does_not_issue_a_fixed_login_qr(): void
    {
        Mail::fake();
        $user = User::factory()->approved()->create(['role' => 'gast']);
        $token = $this->invitationToken($user);

        $response = $this->postJson(route('qr.activate.store'), ['token' => $token, 'name' => 'Sam'])->assertOk();

        $response->assertJsonMissingPath('codes.login')->assertJsonStructure(['codes' => ['attendance']]);
        $this->assertGuest();
    }

    public function test_expired_activations_do_not_issue_credentials(): void
    {
        Mail::fake();
        $user = User::factory()->approved()->create();
        $token = $this->invitationToken($user);
        $this->travel(25)->hours();

        $this->postJson(route('qr.activate.store'), ['token' => $token, 'name' => 'Sam'])
            ->assertUnprocessable()->assertJsonPath('message', 'Deze activatielink is ongeldig of verlopen.');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'login_token_hash' => null, 'attendance_token' => null]);
        $this->assertGuest();
    }

    public function test_resending_an_invitation_invalidates_the_previous_activation_link(): void
    {
        Mail::fake();
        $user = User::factory()->approved()->create();
        $first = $this->invitationToken($user);
        $second = $this->invitationToken($user);

        $this->postJson(route('qr.activate.store'), ['token' => $first, 'name' => 'Sam'])->assertUnprocessable();
        $this->postJson(route('qr.activate.store'), ['token' => $second, 'name' => 'Sam'])->assertOk();
    }

    public function test_an_activation_link_cannot_be_used_after_the_account_email_changes(): void
    {
        Mail::fake();
        $user = User::factory()->approved()->create();
        $token = $this->invitationToken($user);
        $user->update(['email' => 'changed@example.com']);

        $this->postJson(route('qr.activate.store'), ['token' => $token, 'name' => 'Sam'])->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'login_token_hash' => null]);
    }

    public function test_retired_fixed_qr_tokens_cannot_start_a_session_but_password_login_still_works(): void
    {
        $token = str_repeat('a', 64);
        $user = User::factory()->approved()->create(['login_token_hash' => hash('sha256', $token)]);
        $this->get(route('qr.login'))->assertOk();
        $this->assertGuest();

        $this->postJson(route('login.store'), ['token' => $token])->assertGone();
        $this->assertGuest();
        $this->postJson(route('password.login.store'), ['email' => $user->email, 'password' => 'password'])->assertOk()->assertJsonPath('redirect', route('account.index'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('account.index'))->assertOk()->assertDontSee($user->login_token_hash);
        $this->postJson(route('logout'))->assertOk()->assertJsonPath('redirect', url('/inloggen'));
        $this->assertGuest();
    }

    /** @return array<string, array{string}> */
    public static function blockedStatuses(): array
    {
        return ['pending' => ['pending'], 'rejected' => ['rejected']];
    }

    #[DataProvider('blockedStatuses')]
    public function test_accounts_without_approval_cannot_log_in(string $status): void
    {
        $token = str_repeat('a', 64);
        User::factory()->create(['registration_status' => $status, 'login_token_hash' => hash('sha256', $token)]);

        $this->postJson(route('login.store'), ['token' => $token])->assertGone();

        $this->assertGuest();
    }

    public function test_other_qr_types_cannot_be_used_for_login(): void
    {
        $user = User::factory()->approved()->create(['attendance_token' => str_repeat('a', 64), 'donation_token' => str_repeat('b', 64)]);

        $this->postJson(route('login.store'), ['token' => $user->attendance_token])->assertGone();
        $this->postJson(route('login.store'), ['token' => $user->donation_token])->assertGone();

        $this->assertGuest();
    }

    public function test_legacy_student_qr_login_is_disabled_for_all_roles(): void
    {
        $token = str_repeat('a', 64);
        $user = User::factory()->approved()->create(['role' => 'gast', 'login_token_hash' => hash('sha256', $token)]);

        $this->postJson(route('students.login.store'), ['token' => $token])->assertGone();

        $this->assertGuest();
        $user->forceFill(['role' => 'student'])->save();
        $this->postJson(route('students.login.store'), ['token' => $token])->assertGone();
        $this->assertGuest();
    }

    public function test_replacing_a_login_token_also_blocks_old_sessions(): void
    {
        $user = User::factory()->approved()->create(['login_token_hash' => str_repeat('a', 64)]);
        $this->actingAs($user)->withSession(['qr_fingerprint' => str_repeat('a', 64)]);
        $this->get(route('account.index'))->assertForbidden();
        $user->forceFill(['login_token_hash' => str_repeat('b', 64)])->save();

        $this->get(route('account.index'))->assertForbidden();
        $this->postJson(route('logout'))->assertOk();

        $this->assertGuest();
    }

    public function test_guests_must_log_in_before_opening_an_account(): void
    {
        $this->get(route('account.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_approve_and_invite_a_pending_student(): void
    {
        Mail::fake();
        $admin = User::factory()->approved()->create(['role' => 'admin']);
        $student = User::factory()->create();

        $this->actingAs($admin)->patchJson(route('account.approve', $student), ['role' => 'student', 'status' => 'approved'])->assertOk();

        $this->assertDatabaseHas('users', ['id' => $student->id, 'role' => 'student', 'registration_status' => 'approved']);
        Mail::assertSent(QrActivationMail::class, fn (QrActivationMail $mail): bool => $mail->hasTo($student->email));
    }

    public function test_student_cannot_read_requests_or_approve_accounts(): void
    {
        Mail::fake();
        $student = User::factory()->approved()->create(['role' => 'student']);
        $pending = User::factory()->create();

        $this->actingAs($student)->getJson(route('account.requests'))->assertForbidden();
        $this->patchJson(route('account.approve', $pending), ['role' => 'admin', 'status' => 'approved'])->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $pending->id, 'registration_status' => 'pending']);
        Mail::assertNothingSent();
    }

    public function test_admin_can_invite_selected_existing_students_without_replacing_their_codes(): void
    {
        Mail::fake();
        $admin = User::factory()->approved()->create(['role' => 'admin']);
        $students = User::factory()->approved()->count(2)->create(['role' => 'student']);

        $this->actingAs($admin)->postJson(route('account.invite-selected'), ['ids' => $students->modelKeys()])->assertOk();

        Mail::assertSentCount(2);
        $this->assertDatabaseHas('users', ['id' => $students[0]->id, 'login_token_hash' => null]);
    }

    public function test_bulk_invites_reject_unapproved_accounts_before_sending_any_mail(): void
    {
        Mail::fake();
        $admin = User::factory()->approved()->create(['role' => 'admin']);
        $pending = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->postJson(route('account.invite-selected'), ['ids' => [$pending->id]])->assertUnprocessable();

        Mail::assertNothingSent();
    }

    public function test_public_donation_page_shows_no_private_information_or_fake_total(): void
    {
        $student = User::factory()->approved()->create(['role' => 'student', 'name' => '<script>unsafe</script>',
            'email' => 'private@example.com', 'student_number' => 'private-student', 'login_token_hash' => str_repeat('a', 64),
            'attendance_token' => str_repeat('b', 64), 'donation_token' => str_repeat('c', 64)]);

        $this->get(route('students.donation', $student->donation_token))->assertOk()
            ->assertViewHas('pageData.qr.studentName', $student->name)
            ->assertViewHas('pageData.qr.rankingAvailable', false)
            ->assertDontSee($student->email)->assertDontSee($student->student_number)
            ->assertDontSee($student->login_token_hash)->assertDontSee($student->attendance_token)
            ->assertDontSee('<script>unsafe</script>', false);

        $this->assertDatabaseCount('donations', 0);
    }

    public function test_a_donation_code_does_not_expose_an_unapproved_student(): void
    {
        $student = User::factory()->create(['role' => 'student', 'donation_token' => str_repeat('c', 64)]);

        $this->get(route('students.donation', $student->donation_token))->assertNotFound();
    }

    public function test_secret_qr_fields_are_hidden_when_a_user_is_serialized(): void
    {
        $user = User::factory()->create(['login_token_hash' => str_repeat('a', 64),
            'attendance_token' => str_repeat('b', 64), 'donation_token' => str_repeat('c', 64)]);

        $attributes = $user->toArray();

        $this->assertArrayNotHasKey('login_token_hash', $attributes);
        $this->assertArrayNotHasKey('attendance_token', $attributes);
        $this->assertArrayNotHasKey('donation_token', $attributes);
    }

    public function test_cli_can_approve_an_existing_first_admin_but_does_not_create_accounts(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->artisan('qr:invite', ['email' => $user->email, '--approve' => true, '--role' => 'admin'])->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'admin', 'registration_status' => 'approved']);
        Mail::assertSent(QrActivationMail::class);
        $this->artisan('qr:invite', ['email' => 'unknown@example.com'])->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_activation_mail_contains_the_private_link_and_expiry_notice(): void
    {
        $mail = new QrActivationMail(route('qr.activate').'#token=example');

        $mail->assertSeeInHtml('24 uur');
        $mail->assertSeeInHtml('#token=example');
        $mail->assertSeeInText('Een vaste privé-inlog-QR wordt niet meer uitgegeven.');
    }

    public function test_activation_is_refused_if_approval_has_been_withdrawn(): void
    {
        Mail::fake();
        $user = User::factory()->approved()->create();
        $token = $this->invitationToken($user);
        $user->forceFill(['registration_status' => 'rejected'])->save();

        $this->postJson(route('qr.activate.store'), ['token' => $token, 'name' => 'Sam'])->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'login_token_hash' => null]);
        $this->assertGuest();
    }

    public function test_login_attempts_with_the_same_token_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson(route('login.store'), ['token' => str_repeat('a', 64)])->assertGone();
        }

        $this->postJson(route('login.store'), ['token' => str_repeat('a', 64)])->assertTooManyRequests();

        $this->assertGuest();
    }

    public function test_invalid_approval_data_does_not_modify_or_invite_an_account(): void
    {
        Mail::fake();
        $admin = User::factory()->approved()->create(['role' => 'admin']);
        $pending = User::factory()->create();

        $this->actingAs($admin)->patchJson(route('account.approve', $pending), ['role' => 'superadmin', 'status' => 'approved'])
            ->assertUnprocessable()->assertInvalid(['role']);

        $this->assertDatabaseHas('users', ['id' => $pending->id, 'registration_status' => 'pending']);
        Mail::assertNothingSent();
    }

    public function test_rejection_immediately_blocks_an_existing_approved_account(): void
    {
        Mail::fake();
        $admin = User::factory()->approved()->create(['role' => 'admin']);
        $student = User::factory()->approved()->create(['role' => 'student']);

        $this->actingAs($admin)->patchJson(route('account.approve', $student), ['role' => 'student', 'status' => 'rejected'])->assertOk();

        $this->actingAs($student->fresh())->get(route('account.index'))->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_admin_account_listing_has_invitation_actions_and_hides_tokens(): void
    {
        $admin = User::factory()->approved()->create(['role' => 'admin']);
        $student = User::factory()->approved()->create(['role' => 'student', 'login_token_hash' => str_repeat('a', 64)]);

        $this->actingAs($admin)->getJson(route('account.requests'))->assertOk()
            ->assertJsonPath('data.1.inviteUrl', route('account.invite', $student))
            ->assertDontSee($student->login_token_hash);
    }

    private function invitationToken(User $user): string
    {
        $this->app->make(QrCredentials::class)->invite($user);

        return $this->tokenFromUrl(Mail::sent(QrActivationMail::class)->last()->activationUrl);
    }

    private function tokenFromUrl(string $url): string
    {
        parse_str(parse_url($url, PHP_URL_FRAGMENT), $fragment);

        return $fragment['token'];
    }
}
