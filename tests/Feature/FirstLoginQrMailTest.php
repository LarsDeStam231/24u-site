<?php

namespace Tests\Feature;

use App\Mail\ShareableQrCodesMail;
use App\Models\User;
use App\QrImage;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use RuntimeException;
use Tests\TestCase;

class FirstLoginQrMailTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_qr_codes_are_emailed_on_first_login_not_during_password_setup(): void
    {
        Mail::fake();
        $this->freezeTime();
        $user = User::factory()->approved()->create(['role' => 'student', 'password' => null]);
        $token = Password::createToken($user);

        $this->postJson(route('password.update'), [
            'email' => $user->email, 'token' => $token,
            'password' => 'a-safe-password-123', 'password_confirmation' => 'a-safe-password-123',
        ])->assertOk();

        Mail::assertNothingSent();
        $user->refresh();
        $attendanceToken = $user->attendance_token;
        $donationToken = $user->donation_token;

        $this->postJson(route('password.login.store'), ['email' => $user->email, 'password' => 'a-safe-password-123'])
            ->assertOk()->assertJsonPath('redirect', route('account.index'));

        Mail::assertSent(ShareableQrCodesMail::class, function (ShareableQrCodesMail $mail) use ($user): bool {
            $this->assertSame(['website', 'foundationDonation'], array_keys($mail->codes));
            $this->assertSame(route('home'), $mail->codes['website']['url']);
            $this->assertSame(config('event.event.foundation.donateUrl'), $mail->codes['foundationDonation']['url']);
            foreach ($mail->codes as $code) {
                $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $code['image']);
            }
            $this->assertSame($user->fresh()->discord_code, $mail->discordCode);
            $this->assertMatchesRegularExpression('/^[A-Z2-9]{8}$/', $mail->discordCode);

            return $mail->hasTo($user->email);
        });
        $this->assertDatabaseHas('users', [
            'id' => $user->id, 'qr_codes_emailed_at' => now()->toDateTimeString(),
            'attendance_token' => $attendanceToken, 'donation_token' => $donationToken,
        ]);
        $this->get(route('account.index'))->assertOk()
            ->assertViewHas('pageData.qr.shareableCodes.website.url', route('home'))
            ->assertViewHas('pageData.qr.shareableCodes.foundationDonation.url', config('event.event.foundation.donateUrl'));
    }

    public function test_later_logins_and_password_resets_do_not_resend_the_qr_mail(): void
    {
        Mail::fake();
        $this->freezeTime();
        $user = User::factory()->approved()->create(['role' => 'gast', 'password' => 'existing-password']);
        $login = ['email' => $user->email, 'password' => 'existing-password'];
        $this->postJson(route('password.login.store'), $login)->assertOk();
        $sentAt = $user->fresh()->qr_codes_emailed_at;
        $this->travel(1)->hour();
        $this->postJson(route('logout'))->assertOk();
        $this->postJson(route('password.login.store'), $login)->assertOk();
        event(new Login('web', $user, false));
        $this->postJson(route('password.update'), [
            'email' => $user->email, 'token' => Password::createToken($user),
            'password' => 'new-safe-password-123', 'password_confirmation' => 'new-safe-password-123',
        ])->assertOk();
        $this->postJson(route('logout'))->assertOk();

        $this->postJson(route('password.login.store'), ['email' => $user->email, 'password' => 'new-safe-password-123'])->assertOk();

        Mail::assertSent(ShareableQrCodesMail::class, 1);
        $this->assertTrue($sentAt->equalTo($user->fresh()->qr_codes_emailed_at));
    }

    public function test_a_login_without_a_configured_password_does_not_send_qr_mail(): void
    {
        Mail::fake();
        $user = User::factory()->approved()->create(['password' => null]);

        Auth::login($user);

        $this->assertAuthenticatedAs($user);
        Mail::assertNothingSent();
        $this->assertNull($user->fresh()->qr_codes_emailed_at);
    }

    public function test_a_failed_mail_does_not_block_login_and_is_retried_on_the_next_login(): void
    {
        Exceptions::fake();
        $user = User::factory()->approved()->create(['password' => 'existing-password']);
        $transport = Mail::mailer()->getSymfonyTransport();
        $shouldFail = true;
        Event::listen(MessageSending::class, function () use (&$shouldFail): void {
            if ($shouldFail) {
                throw new RuntimeException('Mail service unavailable');
            }
        });
        $login = ['email' => $user->email, 'password' => 'existing-password'];

        $this->postJson(route('password.login.store'), $login)->assertOk()->assertJsonPath('redirect', route('account.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->qr_codes_emailed_at);
        $this->assertCount(0, $transport->messages());
        Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'Mail service unavailable');
        $shouldFail = false;
        $this->postJson(route('logout'))->assertOk();

        $this->postJson(route('password.login.store'), $login)->assertOk();

        $this->assertNotNull($user->fresh()->qr_codes_emailed_at);
        $this->assertCount(1, $transport->messages());
        $message = $transport->messages()->first()->getOriginalMessage();
        $this->assertSame(2, substr_count($message->getHtmlBody(), 'cid:'));
    }

    public function test_an_in_progress_qr_mail_is_not_sent_twice(): void
    {
        Mail::fake();
        $user = User::factory()->approved()->create(['password' => 'existing-password']);
        $lock = Cache::lock('qr:welcome-mail:'.$user->id, 120);
        $this->assertTrue($lock->get());

        try {
            $this->postJson(route('password.login.store'), ['email' => $user->email, 'password' => 'existing-password'])->assertOk();

            Mail::assertNothingSent();
            $this->assertNull($user->fresh()->qr_codes_emailed_at);
        } finally {
            $lock->release();
        }
    }

    public function test_mailed_website_qr_uses_the_configured_origin_not_the_request_host(): void
    {
        Mail::fake();
        config(['app.url' => 'https://24u.example']);
        $user = User::factory()->approved()->create(['password' => 'existing-password']);

        $this->postJson('http://attacker.example/inloggen', ['email' => $user->email, 'password' => 'existing-password'])->assertOk();

        Mail::assertSent(ShareableQrCodesMail::class, fn (ShareableQrCodesMail $mail): bool => $mail->codes['website']['url'] === 'https://24u.example');
    }

    public function test_qr_mail_contains_both_inline_images_downloads_and_plain_text_links(): void
    {
        $images = new QrImage;
        $codes = [
            'website' => ['url' => 'https://24u.example/', 'image' => $images->pngForUrl('https://24u.example/')],
            'foundationDonation' => ['url' => 'https://wensstichtingdrenthe.nl/doneren', 'image' => $images->pngForUrl('https://wensstichtingdrenthe.nl/doneren')],
        ];
        $mail = new ShareableQrCodesMail('<script>alert(1)</script>', $codes);

        $mail->assertHasSubject('Jouw QR-codes — DCTerra 24 Uur Live');
        $mail->assertSeeInHtml('Website 24 Uur Live');
        $mail->assertSeeInHtml('Wensstichting Drenthe');
        $mail->assertSeeInHtml('data:image/png;base64,');
        $mail->assertSeeInHtml('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false);
        $mail->assertDontSeeInHtml('<script>', escape: false);
        foreach ($codes as $code) {
            $mail->assertSeeInHtml($code['url']);
            $mail->assertSeeInText($code['url']);
        }
        $mail->assertHasAttachedData($codes['website']['image'], 'website-qr.png', ['mime' => 'image/png']);
        $mail->assertHasAttachedData($codes['foundationDonation']['image'], 'wensstichting-qr.png', ['mime' => 'image/png']);
    }
}
