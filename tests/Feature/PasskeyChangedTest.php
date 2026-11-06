<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasskeyChanged;
use Illuminate\Notifications\Channels\MailChannel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasskeyChangedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.name' => 'Laravel',
            'app.locale' => 'en',
            'mail.default' => 'array',
            'mail.from.name' => 'Laravel',
        ]);
    }

    /** @return array<string, array{string, string}> */
    public static function passkeyChanges(): array
    {
        return [
            'added' => ['toegevoegd', 'Er is een passkey toegevoegd aan jouw account: Privé telefoon.'],
            'removed' => ['verwijderd', 'Er is een passkey verwijderd van jouw account: Privé telefoon.'],
        ];
    }

    #[DataProvider('passkeyChanges')]
    public function test_passkey_emails_use_dutch_text_and_event_branding_despite_laravel_defaults(string $change, string $expectedDescription): void
    {
        $this->travelTo('2026-11-05 12:00:00');
        $user = User::factory()->make();

        $email = app(MailChannel::class)->send($user, new PasskeyChanged($change, 'Privé telefoon'))->getOriginalMessage();

        $this->assertSame('Passkey '.$change.' — DCTerra 24 Uur Live', $email->getSubject());
        $this->assertSame('DCTerra 24 Uur Live', $email->getFrom()[0]->getName());
        $this->assertSame(config('mail.from.address'), $email->getFrom()[0]->getAddress());
        $this->assertSame($user->email, $email->getTo()[0]->getAddress());
        foreach ([$email->getHtmlBody(), $email->getTextBody()] as $body) {
            $this->assertStringContainsString('Hallo!', $body);
            $this->assertStringContainsString($expectedDescription, $body);
            $this->assertStringContainsString('Heb je dit niet zelf gedaan? Wijzig je wachtwoord en verwijder onbekende passkeys.', $body);
            $this->assertStringContainsString('Met vriendelijke groet,', $body);
            $this->assertStringContainsString('© 2026 DCTerra 24 Uur Live. Alle rechten voorbehouden.', $body);
            $this->assertStringContainsString('Werkt de knop "Passkeys beheren" niet? Kopieer en plak deze link in je browser:', $body);
            $this->assertStringContainsString(route('passkeys.index'), $body);
            $this->assertStringNotContainsString('Laravel', $body);
        }
        $this->assertStringContainsString('lang="nl"', $email->getHtmlBody());
        $this->assertStringContainsString('href="'.route('passkeys.index').'"', $email->getHtmlBody());
    }

    public function test_the_logo_is_an_inline_png_even_with_a_custom_application_name(): void
    {
        config(['app.name' => 'DCTerra 24 Uur Live']);
        $user = User::factory()->make();

        $email = app(MailChannel::class)->send($user, new PasskeyChanged('toegevoegd', 'Privé telefoon'))->getOriginalMessage();

        $attachments = $email->getAttachments();
        $this->assertCount(1, $attachments);
        $this->assertSame('inline', $attachments[0]->getDisposition());
        $this->assertSame('image', $attachments[0]->getMediaType());
        $this->assertSame('png', $attachments[0]->getMediaSubtype());
        $this->assertStringContainsString('src="cid:'.$attachments[0]->getContentId().'"', $email->getHtmlBody());
        $this->assertStringNotContainsString('cdn.asp.events', $email->getHtmlBody());
        $this->assertStringNotContainsString('cid:', $email->getTextBody());
        $this->assertStringNotContainsString('<img', $email->getTextBody());
    }

    public function test_passkey_names_are_escaped_in_html_and_remain_readable_in_plain_text(): void
    {
        $user = User::factory()->make();
        $name = 'Privé & werk <script>alert(1)</script> [Klik](https://evil.test)';

        $email = app(MailChannel::class)->send($user, new PasskeyChanged('toegevoegd', $name))->getOriginalMessage();

        $this->assertStringContainsString('Privé &amp; werk &lt;script&gt;', $email->getHtmlBody());
        $this->assertStringNotContainsString('<script>', $email->getHtmlBody());
        $this->assertStringNotContainsString('href="https://evil.test"', $email->getHtmlBody());
        $this->assertStringContainsString($name, $email->getTextBody());
    }
}
