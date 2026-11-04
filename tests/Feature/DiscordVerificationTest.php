<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DiscordVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.discord.bot_api_key' => 'test-bot-key']);
    }

    public function test_bot_can_verify_a_code_and_link_the_discord_account(): void
    {
        $user = User::factory()->approved()->create(['name' => 'Jan Jansen', 'role' => 'student']);
        $user->forceFill(['discord_code' => 'ABCD2345'])->save();

        $this->withToken('test-bot-key')->postJson(route('api.discord.verify'), [
            'code' => ' abcd2345 ', 'discordUserId' => '123456789012345678',
        ])->assertOk()->assertJson(['name' => 'Jan Jansen', 'role' => 'student', 'alreadyLinked' => false]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'discord_user_id' => '123456789012345678']);
        $this->assertNotNull($user->fresh()->discord_verified_at);

        $this->withToken('test-bot-key')->postJson(route('api.discord.verify'), [
            'code' => 'ABCD2345', 'discordUserId' => '123456789012345678',
        ])->assertOk()->assertJsonPath('alreadyLinked', true);
    }

    public function test_a_code_cannot_be_claimed_by_a_second_discord_account(): void
    {
        $user = User::factory()->approved()->create();
        $user->forceFill(['discord_code' => 'ABCD2345', 'discord_user_id' => '111111111111111111'])->save();

        $this->withToken('test-bot-key')->postJson(route('api.discord.verify'), [
            'code' => 'ABCD2345', 'discordUserId' => '222222222222222222',
        ])->assertConflict();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'discord_user_id' => '111111111111111111']);
    }

    public function test_unknown_codes_and_unapproved_accounts_are_rejected(): void
    {
        $pending = User::factory()->create(['registration_status' => 'pending']);
        $pending->forceFill(['discord_code' => 'PEND2345'])->save();

        foreach (['PEND2345', 'NOPE2345'] as $code) {
            $this->withToken('test-bot-key')->postJson(route('api.discord.verify'), [
                'code' => $code, 'discordUserId' => '123456789012345678',
            ])->assertNotFound();
        }

        $this->assertNull($pending->fresh()->discord_user_id);
    }

    public function test_requests_without_the_bot_api_key_are_rejected(): void
    {
        $payload = ['code' => 'ABCD2345', 'discordUserId' => '123456789012345678'];

        $this->postJson(route('api.discord.verify'), $payload)->assertUnauthorized();
        $this->withToken('wrong-key')->postJson(route('api.discord.verify'), $payload)->assertUnauthorized();

        config(['services.discord.bot_api_key' => null]);
        $this->withToken('')->postJson(route('api.discord.verify'), $payload)->assertUnauthorized();
    }

    public function test_account_page_shows_a_persistent_discord_code(): void
    {
        $user = User::factory()->approved()->create();

        $code = $this->actingAs($user)->get(route('account.index'))->assertOk()
            ->viewData('pageData')['qr']['account']['discordCode'];

        $this->assertMatchesRegularExpression('/^[A-Z2-9]{8}$/', $code);
        $this->get(route('account.index'))->assertViewHas('pageData.qr.account.discordCode', $code);
    }
}
