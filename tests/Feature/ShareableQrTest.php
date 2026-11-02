<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShareableQrTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{string}> */
    public static function sharingRoles(): array
    {
        return ['student' => ['student'], 'organization' => ['organisatie'], 'volunteer' => ['vrijwilliger']];
    }

    #[DataProvider('sharingRoles')]
    public function test_participants_receive_only_public_website_and_foundation_links_in_shareable_codes(string $role): void
    {
        $user = User::factory()->approved()->create([
            'role' => $role, 'attendance_token' => str_repeat('a', 64),
            'donation_token' => str_repeat('b', 64), 'login_token_hash' => str_repeat('c', 64),
        ]);

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertOk()
            ->assertViewHas('pageData.qr.shareableCodes.website.url', route('home'))
            ->assertViewHas('pageData.qr.shareableCodes.foundationDonation.url', config('event.event.foundation.donateUrl'))
            ->assertViewHas('pageData.qr.codes.attendance.url', route('attendance.index').'#token='.$user->attendance_token);
        $qr = $response->viewData('pageData')['qr'];
        $this->assertSame(['website', 'foundationDonation'], array_keys($qr['shareableCodes']));
        $this->assertArrayNotHasKey('donation', $qr['codes']);
        $this->assertArrayNotHasKey('login', $qr['codes']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $qr['shareableCodes']['website']['image']);
        $this->assertStringNotContainsString($user->attendance_token, json_encode($qr['shareableCodes']));
        $this->assertStringNotContainsString($user->donation_token, json_encode($qr['shareableCodes']));
        $this->assertStringNotContainsString($user->login_token_hash, json_encode($qr['shareableCodes']));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'attendance_token' => $user->attendance_token, 'donation_token' => $user->donation_token]);
    }

    public function test_shareable_codes_are_available_before_personal_code_activation(): void
    {
        $student = User::factory()->approved()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('account.index'))->assertOk()
            ->assertViewHas('pageData.qr.codes', [])
            ->assertViewHas('pageData.qr.shareableCodes.website.url', route('home'));

        $this->assertDatabaseHas('users', ['id' => $student->id, 'attendance_token' => null, 'donation_token' => null]);
    }

    public function test_admin_attendance_codes_and_current_login_endpoints_are_preserved(): void
    {
        $admin = User::factory()->approved()->create(['role' => 'admin', 'attendance_token' => str_repeat('a', 64)]);

        $this->actingAs($admin)->get(route('account.index'))->assertOk()
            ->assertViewHas('pageData.qr.shareableCodes', [])
            ->assertViewHas('pageData.qr.codes.attendance.url', route('attendance.index').'#token='.$admin->attendance_token)
            ->assertViewHas('pageData.qr.endpoints.passkeys', route('passkeys.index'))
            ->assertViewHas('pageData.qr.endpoints.passwordLogin', route('password.login.store'));
    }

    public function test_existing_personal_student_donation_links_remain_accessible(): void
    {
        $student = User::factory()->approved()->create(['role' => 'student', 'donation_token' => str_repeat('b', 64)]);

        $this->get(route('students.donation', $student->donation_token))->assertOk()
            ->assertViewHas('pageData.qr.studentName', $student->name);
    }
}
