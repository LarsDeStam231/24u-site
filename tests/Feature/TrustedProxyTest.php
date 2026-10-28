<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{string}> */
    public static function localProxies(): array
    {
        return ['IPv4 loopback' => ['127.0.0.1'], 'IPv6 loopback' => ['::1']];
    }

    #[DataProvider('localProxies')]
    public function test_local_https_proxy_generates_secure_login_endpoints_without_trusting_forwarded_hosts(string $proxy): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => $proxy])->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'attacker.example',
        ])->get('http://tunnel.example/inloggen')->assertOk()
            ->assertViewHas('pageData.page.optionsUrl', 'https://tunnel.example/inloggen/opties')
            ->assertViewHas('pageData.page.submitUrl', 'https://tunnel.example/inloggen')
            ->assertViewHas('pageData.page.mailUrl', 'https://tunnel.example/wachtwoord/e-mail')
            ->assertViewHas('pageData.page.pairCreateUrl', 'https://tunnel.example/computer-koppelen/nieuw')
            ->assertViewHas('pageData.page.pairConsumeUrl', 'https://tunnel.example/computer-koppelen/status')
            ->assertViewHas('pageData.links.account', 'https://tunnel.example/account');
    }

    public function test_untrusted_clients_cannot_change_the_protocol_with_forwarded_headers(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->withHeaders([
            'X-Forwarded-Proto' => 'https',
        ])->get('http://tunnel.example/inloggen')->assertOk()
            ->assertViewHas('pageData.page.optionsUrl', 'http://tunnel.example/inloggen/opties');
    }

    public function test_direct_local_http_access_remains_http_without_proxy_headers(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('http://site.test/inloggen')->assertOk()
            ->assertViewHas('pageData.page.optionsUrl', 'http://site.test/inloggen/opties');
    }

    public function test_email_lookup_and_password_login_use_the_existing_database_and_return_a_secure_redirect(): void
    {
        $user = User::factory()->approved()->create(['password' => 'test-password']);
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withHeaders(['X-Forwarded-Proto' => 'https']);

        $this->postJson('http://tunnel.example/inloggen/opties', ['email' => $user->email])->assertOk()
            ->assertExactJson(['hasPassword' => true, 'hasPasskeys' => false]);
        $this->postJson('http://tunnel.example/inloggen', ['email' => $user->email, 'password' => 'test-password'])->assertOk()
            ->assertJsonPath('redirect', 'https://tunnel.example/account');

        $this->assertAuthenticatedAs($user);
    }
}
