<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QrAttendanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_check_in_and_check_out_are_logged_without_duplicate_scans(): void
    {
        $this->travelTo(now()->parse(config('event.event.startsAt'))->addHour());
        $scanner = User::factory()->approved()->create(['role' => 'vrijwilliger']);
        $student = User::factory()->approved()->create(['role' => 'student', 'attendance_token' => str_repeat('a', 64)]);
        $this->actingAs($scanner);

        $this->postJson(route('attendance.store'), ['token' => $student->attendance_token, 'action' => 'check-in'])
            ->assertOk()->assertJsonPath('isPresent', true);
        $this->postJson(route('attendance.store'), ['token' => $student->attendance_token, 'action' => 'check-in'])->assertOk();

        $this->assertDatabaseHas('users', ['id' => $student->id, 'is_present' => true]);
        $this->assertDatabaseCount('attendance_logs', 1);
        $this->assertDatabaseHas('attendance_logs', ['user_id' => $student->id, 'scanned_by' => $scanner->id, 'action' => 'check-in']);
        $this->postJson(route('attendance.store'), ['token' => $student->attendance_token, 'action' => 'check-out'])
            ->assertOk()->assertJsonPath('isPresent', false);
        $this->assertDatabaseCount('attendance_logs', 2);
        $this->assertDatabaseHas('users', ['id' => $student->id, 'is_present' => false]);
    }

    /** @return array<string, array{string, bool}> */
    public static function scannerRoles(): array
    {
        return ['admin' => ['admin', true], 'organisation' => ['organisatie', true], 'volunteer' => ['vrijwilliger', true],
            'student' => ['student', false], 'donor' => ['donateur', false], 'guest' => ['gast', false]];
    }

    #[DataProvider('scannerRoles')]
    public function test_only_designated_roles_can_scan_or_manage_accounts(string $role, bool $canScan): void
    {
        $user = User::factory()->approved()->create(['role' => $role]);

        $this->assertSame($canScan, Gate::forUser($user)->allows('scan-attendance'));
        $this->assertSame($role === 'admin', Gate::forUser($user)->allows('manage-qr-accounts'));
    }

    public function test_students_cannot_scan_their_own_attendance(): void
    {
        $student = User::factory()->approved()->create(['role' => 'student', 'attendance_token' => str_repeat('a', 64)]);

        $this->actingAs($student)->postJson(route('attendance.store'), ['token' => $student->attendance_token, 'action' => 'check-in'])->assertForbidden();

        $this->assertDatabaseCount('attendance_logs', 0);
        $this->assertDatabaseHas('users', ['id' => $student->id, 'is_present' => false]);
    }

    public function test_unauthenticated_scans_cannot_change_attendance(): void
    {
        $this->postJson(route('attendance.store'), ['token' => str_repeat('a', 64), 'action' => 'check-in'])->assertUnauthorized();

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_scanning_a_qr_page_without_confirming_does_not_change_attendance(): void
    {
        $scanner = User::factory()->approved()->create(['role' => 'admin']);

        $this->actingAs($scanner)->get(route('attendance.index'))->assertOk();

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_attendance_is_blocked_outside_the_event_window(): void
    {
        $this->travelTo(now()->parse(config('event.event.startsAt'))->subDay());
        $scanner = User::factory()->approved()->create(['role' => 'admin']);

        $this->actingAs($scanner)->postJson(route('attendance.store'), ['token' => str_repeat('a', 64), 'action' => 'check-in'])
            ->assertUnprocessable()->assertJsonPath('message', 'Aanwezigheid registreren kan alleen tijdens het evenement.');

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_an_invalid_action_does_not_create_a_log(): void
    {
        $scanner = User::factory()->approved()->create(['role' => 'admin']);

        $this->actingAs($scanner)->postJson(route('attendance.store'), ['token' => str_repeat('a', 64), 'action' => 'toggle'])
            ->assertUnprocessable()->assertInvalid(['action']);

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_a_login_qr_cannot_be_used_as_an_attendance_token(): void
    {
        $this->travelTo(now()->parse(config('event.event.startsAt'))->addHour());
        $scanner = User::factory()->approved()->create(['role' => 'admin']);
        $loginToken = str_repeat('a', 64);
        User::factory()->approved()->create(['login_token_hash' => hash('sha256', $loginToken)]);

        $this->actingAs($scanner)->postJson(route('attendance.store'), ['token' => $loginToken, 'action' => 'check-in'])->assertNotFound();

        $this->assertDatabaseCount('attendance_logs', 0);
    }
}
